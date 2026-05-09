<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\OrderReceiptPrinter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PrintDiagnoseCommand extends Command
{
    protected $signature = 'print:diagnose {--send-test : Envia uma impressao de teste pelo microservico configurado}';

    protected $description = 'Mostra a configuracao efetiva da impressao automatica e testa o microservico.';

    public function handle(OrderReceiptPrinter $printer): int
    {
        $config = $printer->configuration();

        $this->info('Configuracao efetiva de impressao');
        $this->line('PRINT_PREFER_ENV: ' . (config('printing.prefer_env') ? 'true' : 'false'));
        $this->line('QUEUE_CONNECTION: ' . config('queue.default'));
        $this->newLine();

        foreach (OrderReceiptPrinter::SETTING_KEYS as $key) {
            $value = (string) ($config[$key] ?? '');

            if (str_contains($key, 'token') && $value !== '') {
                $value = substr($value, 0, 8) . '...';
            }

            $this->line($key . ': ' . ($value !== '' ? $value : '(vazio)'));
        }

        $this->newLine();
        $this->showDatabaseOverrides();

        if (!$this->option('send-test')) {
            $this->newLine();
            $this->comment('Use --send-test para enviar uma impressao de teste pelo microservico.');
            return self::SUCCESS;
        }

        return $this->sendMicroserviceTest($config);
    }

    protected function showDatabaseOverrides(): void
    {
        try {
            $settings = Setting::query()
                ->whereIn('key', OrderReceiptPrinter::SETTING_KEYS)
                ->pluck('value', 'key');
        } catch (\Throwable $e) {
            $this->warn('Nao foi possivel ler a tabela settings: ' . $e->getMessage());
            return;
        }

        if ($settings->isEmpty()) {
            $this->line('Tabela settings: sem overrides de impressao.');
            return;
        }

        $this->line('Overrides na tabela settings:');

        foreach ($settings as $key => $value) {
            $display = (string) $value;

            if (str_contains((string) $key, 'token') && $display !== '') {
                $display = substr($display, 0, 8) . '...';
            }

            $this->line($key . ': ' . ($display !== '' ? $display : '(vazio)'));
        }
    }

    protected function sendMicroserviceTest(array $config): int
    {
        if (($config['print_connection'] ?? '') !== 'microservice') {
            $this->error('print_connection precisa estar como microservice para este teste.');
            return self::FAILURE;
        }

        $url = trim((string) ($config['print_microservice_url'] ?? ''));
        $token = trim((string) ($config['print_microservice_token'] ?? ''));

        if ($url === '' || $token === '') {
            $this->error('print_microservice_url e print_microservice_token sao obrigatorios.');
            return self::FAILURE;
        }

        $healthUrl = preg_replace('#/print/?$#', '/health', $url) ?: $url;
        $this->line('Testando health: ' . $healthUrl);

        $health = Http::timeout(15)->get($healthUrl);

        if (!$health->successful()) {
            $this->error('Health falhou: HTTP ' . $health->status() . ' - ' . $health->body());
            return self::FAILURE;
        }

        $this->info('Health OK.');
        $this->line('Enviando impressao de teste: ' . $url);

        $content = "\x1b@" .
            "TESTE MICROSERVICO DIMAS\n" .
            now()->format('Y-m-d H:i:s') . "\n\n\n" .
            "\x1dV0";

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($url, [
                'encoding' => 'base64',
                'content' => base64_encode($content),
                'connection' => 'network',
                'host' => $config['print_host'] ?? null,
                'port' => (int) ($config['print_port'] ?? 9100),
            ]);

        if (!$response->successful()) {
            $this->error('Impressao de teste falhou: HTTP ' . $response->status() . ' - ' . $response->body());
            return self::FAILURE;
        }

        $this->info('Impressao de teste enviada com sucesso.');
        $this->line($response->body());

        return self::SUCCESS;
    }
}
