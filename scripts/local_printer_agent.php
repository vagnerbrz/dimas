<?php

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

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    fwrite(STDERR, "Nao foi possivel identificar o caminho do projeto.\n");
    exit(1);
}

$app = require $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['printing.prefer_env' => true]);

$apiUrl = $argv[1] ?? env('LOCAL_PRINT_API_URL');
$apiToken = $argv[2] ?? env('LOCAL_PRINT_API_TOKEN');
$interval = (int) ($argv[3] ?? env('LOCAL_PRINT_POLL_INTERVAL', 10));

if (!$apiUrl || !$apiToken) {
    fwrite(STDERR, "Uso: php scripts/local_printer_agent.php <REMOTE_API_URL> <API_TOKEN> [POLL_INTERVAL_SECONDS]\n");
    fwrite(STDERR, "Ou defina LOCAL_PRINT_API_URL e LOCAL_PRINT_API_TOKEN no .env local.\n");
    exit(1);
}

$apiUrl = rtrim($apiUrl, '/');
$client = new Client([
    'base_uri' => $apiUrl,
    'timeout' => 30,
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $apiToken,
    ],
]);

$printer = $app->make(OrderReceiptPrinter::class);

function hydrateOrder(array $data): Order
{
    $order = new Order($data);

    if (!empty($data['created_at'])) {
        $order->created_at = new Carbon($data['created_at']);
    }

    if (!empty($data['updated_at'])) {
        $order->updated_at = new Carbon($data['updated_at']);
    }

    if (!empty($data['customer'])) {
        $order->setRelation('customer', new Customer($data['customer']));
    }

    if (!empty($data['address'])) {
        $order->setRelation('address', new Address($data['address']));
    }

    $items = collect($data['items'] ?? [])->map(function (array $itemData) {
        $item = new OrderItem($itemData);

        if (!empty($itemData['product'])) {
            $item->setRelation('product', new Product($itemData['product']));
        }

        return $item;
    });

    $order->setRelation('items', $items);

    return $order;
}

function fetchJobs(Client $client): array
{
    $response = $client->get('/api/local-printer/jobs');
    return json_decode((string) $response->getBody(), true) ?: [];
}

function acknowledgeJob(Client $client, int $jobId): void
{
    $client->post("/api/local-printer/jobs/{$jobId}/complete");
}

function failJob(Client $client, int $jobId, string $message): void
{
    $client->post("/api/local-printer/jobs/{$jobId}/fail", [
        'json' => ['error_message' => $message],
    ]);
}

fwrite(STDOUT, "Iniciando agente local de impressao...\n");

while (true) {
    try {
        $jobs = fetchJobs($client);

        if (count($jobs) === 0) {
            fwrite(STDOUT, date('[Y-m-d H:i:s]') . " Nenhum job pendente. Aguardando {$interval}s...\n");
            sleep(max(1, $interval));
            continue;
        }

        foreach ($jobs as $job) {
            if (empty($job['order']) || empty($job['id'])) {
                continue;
            }

            $order = hydrateOrder($job['order']);
            $jobId = (int) $job['id'];

            fwrite(STDOUT, date('[Y-m-d H:i:s]') . " Imprimindo pedido ID {$order->id} (job {$jobId})...\n");

            try {
                $printer->print($order);
                acknowledgeJob($client, $jobId);
                fwrite(STDOUT, date('[Y-m-d H:i:s]') . " Pedido {$order->id} impresso com sucesso.\n");
            } catch (Throwable $e) {
                $message = $e->getMessage();
                failJob($client, $jobId, $message);
                fwrite(STDERR, date('[Y-m-d H:i:s]') . " Erro ao imprimir pedido {$order->id}: {$message}\n");
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, date('[Y-m-d H:i:s]') . " Falha na comunicacao com o servidor: {$e->getMessage()}\n");
    }

    sleep(max(1, $interval));
}
