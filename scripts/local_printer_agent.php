<?php

/**
 * Agente local de impressao.
 *
 * Roda na maquina do restaurante (mesma rede da impressora). Consulta o
 * servidor hospedado, imprime os pedidos pendentes e confirma o resultado.
 * Toda conexao sai do restaurante: nao precisa de ngrok nem porta aberta.
 *
 * Uso: php scripts/local_printer_agent.php [API_URL] [API_TOKEN] [INTERVALO_SEGUNDOS]
 * Ou configure LOCAL_PRINT_API_URL, LOCAL_PRINT_API_TOKEN e LOCAL_PRINT_POLL_INTERVAL no .env.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderReceiptPrinter;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;

const AGENT_VERSION = '2.0';
const MAX_BACKOFF_SECONDS = 60;

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    fwrite(STDERR, "Nao foi possivel identificar o caminho do projeto.\n");
    exit(1);
}

$app = require $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// No restaurante a impressora vem sempre do .env local, nunca do banco.
config(['printing.prefer_env' => true]);

$apiUrl = $argv[1] ?? env('LOCAL_PRINT_API_URL');
$apiToken = $argv[2] ?? env('LOCAL_PRINT_API_TOKEN');
$interval = max(1, (int) ($argv[3] ?? env('LOCAL_PRINT_POLL_INTERVAL', 5)));
$localPrintConnection = (string) env('PRINT_CONNECTION', 'network');
$agentName = (string) (env('LOCAL_PRINT_AGENT_NAME') ?: gethostname() ?: 'agente-local');

if (!$apiUrl || !$apiToken) {
    fwrite(STDERR, "Uso: php scripts/local_printer_agent.php <REMOTE_API_URL> <API_TOKEN> [POLL_INTERVAL_SECONDS]\n");
    fwrite(STDERR, "Ou defina LOCAL_PRINT_API_URL e LOCAL_PRINT_API_TOKEN no .env local.\n");
    exit(1);
}

if (in_array($localPrintConnection, ['local', 'microservice'], true)) {
    fwrite(STDERR, "PRINT_CONNECTION={$localPrintConnection} nao e valido para o agente local.\n");
    fwrite(STDERR, "Configure o .env da maquina do restaurante com PRINT_CONNECTION=network ou PRINT_CONNECTION=windows.\n");
    exit(1);
}

// Garante uma unica instancia: dois agentes imprimiriam em dobro.
$lockPath = $basePath . '/storage/app/local_printer_agent.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ja existe um agente de impressao rodando nesta maquina. Encerrando.\n");
    exit(2);
}

$client = new Client([
    'base_uri' => rtrim($apiUrl, '/'),
    'timeout' => 20,
    'connect_timeout' => 10,
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $apiToken,
        'X-Agent-Name' => $agentName,
        'X-Agent-Version' => AGENT_VERSION,
    ],
]);

$printer = $app->make(OrderReceiptPrinter::class);

function logLine(string $message, bool $error = false): void
{
    fwrite($error ? STDERR : STDOUT, date('[Y-m-d H:i:s] ') . $message . PHP_EOL);
}

function modelFromPayload(string $class, array $data, array $relations = []): Model
{
    foreach ($relations as $relation) {
        unset($data[$relation]);
    }

    /** @var Model $model */
    $model = new $class();
    $model->setRawAttributes($data, true);
    $model->exists = true;

    return $model;
}

function hydrateOrder(array $data): Order
{
    /** @var Order $order */
    $order = modelFromPayload(Order::class, $data, ['customer', 'address', 'items']);

    if (!empty($data['created_at'])) {
        $order->created_at = new Carbon($data['created_at']);
    }

    if (!empty($data['updated_at'])) {
        $order->updated_at = new Carbon($data['updated_at']);
    }

    if (!empty($data['customer'])) {
        $order->setRelation('customer', modelFromPayload(Customer::class, $data['customer']));
    }

    if (!empty($data['address'])) {
        $order->setRelation('address', modelFromPayload(Address::class, $data['address']));
    }

    $items = collect($data['items'] ?? [])->map(function (array $itemData) {
        /** @var OrderItem $item */
        $item = modelFromPayload(OrderItem::class, $itemData, ['product']);

        if (!empty($itemData['product'])) {
            $item->setRelation('product', modelFromPayload(Product::class, $itemData['product']));
        }

        return $item;
    });

    $order->setRelation('items', $items);

    return $order;
}

/**
 * Confirmacoes que ainda nao chegaram ao servidor. Sao reenviadas a cada
 * ciclo; sem isso o job voltaria para a fila e o cupom sairia duplicado.
 *
 * @var array<int, array{type: string, message?: string}> $pendingAcks
 */
$pendingAcks = [];

function flushAcks(Client $client, array &$pendingAcks): void
{
    foreach ($pendingAcks as $jobId => $ack) {
        try {
            if ($ack['type'] === 'complete') {
                $client->post("/api/local-printer/jobs/{$jobId}/complete");
            } else {
                $client->post("/api/local-printer/jobs/{$jobId}/fail", [
                    'json' => ['error_message' => $ack['message'] ?? ''],
                ]);
            }
            unset($pendingAcks[$jobId]);
        } catch (Throwable $e) {
            logLine("Nao foi possivel confirmar o job {$jobId} (tentara de novo): {$e->getMessage()}", true);
        }
    }
}

logLine("Agente de impressao v" . AGENT_VERSION . " iniciado ({$agentName}). Servidor: {$apiUrl}. Intervalo: {$interval}s.");

$failures = 0;
$wasIdle = false;

while (true) {
    try {
        flushAcks($client, $pendingAcks);

        $response = $client->get('/api/local-printer/jobs');
        $jobs = json_decode((string) $response->getBody(), true) ?: [];

        if ($failures > 0) {
            logLine('Conexao com o servidor restabelecida.');
            $failures = 0;
        }

        if (count($jobs) === 0) {
            if (!$wasIdle) {
                logLine('Nenhum pedido na fila. Aguardando...');
                $wasIdle = true;
            }
        } else {
            $wasIdle = false;
        }

        foreach ($jobs as $job) {
            $jobId = (int) ($job['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            if (empty($job['order'])) {
                $pendingAcks[$jobId] = ['type' => 'fail', 'message' => 'Pedido nao encontrado no servidor.'];
                continue;
            }

            $order = hydrateOrder($job['order']);
            $label = ($job['reason'] ?? '') === 'reprint' ? 'Reimprimindo' : 'Imprimindo';
            logLine("{$label} pedido #{$order->id} (job {$jobId})...");

            try {
                $printer->print($order);
                $pendingAcks[$jobId] = ['type' => 'complete'];
                logLine("Pedido #{$order->id} impresso.");
            } catch (Throwable $e) {
                $pendingAcks[$jobId] = ['type' => 'fail', 'message' => $e->getMessage()];
                logLine("ERRO ao imprimir pedido #{$order->id}: {$e->getMessage()}", true);
            }
        }

        flushAcks($client, $pendingAcks);
    } catch (Throwable $e) {
        $failures++;
        $wait = min(MAX_BACKOFF_SECONDS, $interval * (2 ** min($failures - 1, 5)));
        logLine("Falha na comunicacao com o servidor ({$failures}x): {$e->getMessage()}. Nova tentativa em {$wait}s.", true);
        sleep($wait);
        continue;
    }

    sleep($interval);
}
