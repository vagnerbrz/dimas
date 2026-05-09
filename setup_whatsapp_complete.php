<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

echo "========================================\n";
echo "   CONFIGURAÇÃO COMPLETA DO WHATSAPP   \n";
echo "========================================\n\n";

// ==================== PARTE 1: VERIFICAR CONFIGURAÇÕES ====================
echo "=== PARTE 1: Verificando Configurações ===\n\n";

$settings = Setting::where('key', 'like', '%whatsapp%')->get();
$config = [];

foreach ($settings as $setting) {
    $config[$setting->key] = $setting->value;
    $displayValue = $setting->value;
    if ($setting->key === 'whatsapp_session_token') {
        $displayValue = substr($displayValue, 0, 10) . '...' . substr($displayValue, -10);
    }
    echo "✅ {$setting->key}: {$displayValue}\n";
}

// Verificar configurações obrigatórias
$required = [
    'whatsapp_server_url' => 'http://localhost:21465',
    'whatsapp_session_name' => 'dimas-novo',
    'whatsapp_session_token' => null
];

foreach ($required as $key => $default) {
    if (!isset($config[$key])) {
        if ($default) {
            Setting::updateOrCreate(['key' => $key], ['value' => $default]);
            $config[$key] = $default;
            echo "⚠️  {$key}: Configurado com valor padrão '{$default}'\n";
        } else {
            echo "❌ {$key}: FALTANDO - Configure manualmente\n";
            exit(1);
        }
    }
}

// ==================== PARTE 2: TESTAR CONEXÃO WPPCONNECT ====================
echo "\n=== PARTE 2: Testando Conexão com WPPConnect ===\n";

try {
    $service = new \App\Services\WppConnectService();

    // Usar reflexão para acessar métodos protegidos
    $reflection = new ReflectionClass($service);

    // Testar status da sessão
    $status = $service->getSessionStatus();
    echo "✅ Status da sessão: " . $status['status'] . "\n";

    // Verificar se está conectado
    $connectedStatuses = ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'];
    if (!in_array($status['status'], $connectedStatuses, true)) {
        echo "❌ Sessão NÃO está conectada. Status: " . $status['status'] . "\n";
        echo "   Execute no WPPConnect: \n";
        echo "   1. Acesse: {$config['whatsapp_server_url']}\n";
        echo "   2. Inicie a sessão '{$config['whatsapp_session_name']}'\n";
        echo "   3. Escaneie o QR Code com o WhatsApp\n";
        exit(1);
    }

    echo "✅ Sessão CONECTADA e pronta para uso!\n";

} catch (Exception $e) {
    echo "❌ Erro ao conectar com WPPConnect: " . $e->getMessage() . "\n";
    echo "   Verifique:\n";
    echo "   1. WPPConnect está rodando em {$config['whatsapp_server_url']}?\n";
    echo "   2. Token correto? Atual: " . substr($config['whatsapp_session_token'], 0, 20) . "...\n";
    exit(1);
}

// ==================== PARTE 3: CONFIGURAR WEBHOOK VIA API ====================
echo "\n=== PARTE 3: Configurando Webhook via API ===\n";

$webhookUrl = config('app.url', 'http://localhost:8000') . '/api/whatsapp/webhook';
echo "URL do webhook: {$webhookUrl}\n";

// Configurar webhook via API do WPPConnect
try {
    $client = Http::timeout(15)
        ->acceptJson()
        ->asJson()
        ->withToken($config['whatsapp_session_token']);

    $baseUrl = rtrim($config['whatsapp_server_url'], '/') . '/api';
    $sessionName = $config['whatsapp_session_name'];

    // Primeiro, verificar webhook atual
    $response = $client->get("{$baseUrl}/{$sessionName}/get-webhook");

    if ($response->successful()) {
        $currentWebhook = $response->json();
        echo "✅ Webhook atual: " . ($currentWebhook['webhook'] ?? 'Não configurado') . "\n";

        if (($currentWebhook['webhook'] ?? '') === $webhookUrl) {
            echo "✅ Webhook já está configurado corretamente!\n";
        } else {
            echo "⚠️  Webhook diferente do esperado. Reconfigurando...\n";
        }
    }

    // Configurar webhook
    echo "Configurando webhook...\n";
    $response = $client->post("{$baseUrl}/{$sessionName}/set-webhook", [
        'webhook' => $webhookUrl,
        'events' => ['onmessage', 'onack', 'onpresence']
    ]);

    if ($response->successful()) {
        echo "✅ Webhook configurado com sucesso!\n";
        echo "   URL: {$webhookUrl}\n";
        echo "   Events: onmessage, onack, onpresence\n";
    } else {
        echo "❌ Erro ao configurar webhook: HTTP {$response->status()}\n";
        echo "   Response: " . $response->body() . "\n";
        echo "   Configure manualmente no painel do WPPConnect.\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao configurar webhook via API: " . $e->getMessage() . "\n";
    echo "   Configure manualmente:\n";
    echo "   1. Acesse: {$config['whatsapp_server_url']}\n";
    echo "   2. Sessions > {$config['whatsapp_session_name']} > Webhook\n";
    echo "   3. URL: {$webhookUrl}\n";
    echo "   4. Events: onmessage, onack, onpresence\n";
}

// ==================== PARTE 4: TESTAR ENVIO DE MENSAGEM ====================
echo "\n=== PARTE 4: Testando Envio de Mensagem ===\n";

echo "Para testar o ENVIO (Dimas → WhatsApp):\n";
echo "1. Digite um número de telefone para teste (com DDD, ex: 5511999999999)\n";
echo "2. Ou pressione Enter para pular este teste\n";
echo "Número de telefone: ";

$handle = fopen("php://stdin", "r");
$testPhone = trim(fgets($handle));
fclose($handle);

if (!empty($testPhone)) {
    try {
        echo "Enviando mensagem de teste para {$testPhone}...\n";
        $result = $service->sendMessage($testPhone, "✅ Teste do sistema Dimas - " . date('H:i:s'));

        echo "✅ Mensagem enviada com sucesso!\n";
        echo "   Message ID: " . ($result['messageId'] ?? 'N/A') . "\n";
        echo "   Status: " . ($result['status'] ?? 'N/A') . "\n";

        // Verificar status da mensagem após 2 segundos
        sleep(2);
        if (isset($result['messageId'])) {
            try {
                $client = Http::timeout(15)
                    ->acceptJson()
                    ->asJson()
                    ->withToken($config['whatsapp_session_token']);

                $checkResponse = $client->post(
                    rtrim($config['whatsapp_server_url'], '/') . '/api/' . $config['whatsapp_session_name'] . '/check-message-status',
                    ['messageId' => $result['messageId']]
                );

                if ($checkResponse->successful()) {
                    $statusInfo = $checkResponse->json();
                    echo "   Status detalhado: " . json_encode($statusInfo) . "\n";
                }
            } catch (Exception $e) {
                // Ignorar erro de verificação
            }
        }

    } catch (Exception $e) {
        echo "❌ Erro ao enviar mensagem: " . $e->getMessage() . "\n";
        echo "   Possíveis causas:\n";
        echo "   1. Número inválido\n";
        echo "   2. Sessão não está realmente conectada\n";
        echo "   3. Problema de rede\n";
    }
} else {
    echo "⚠️  Teste de envio pulado.\n";
}

// ==================== PARTE 5: TESTAR WEBHOOK (RECEBIMENTO) ====================
echo "\n=== PARTE 5: Testando Recebimento (Webhook) ===\n";

echo "Para testar o RECEBIMENTO (WhatsApp → Dimas):\n";
echo "1. Envie uma mensagem do WhatsApp para o número conectado\n";
echo "2. A mensagem deve aparecer automaticamente no dashboard\n";
echo "3. Verifique em: http://127.0.0.1:8000/dashboard\n\n";

echo "Para testar manualmente o webhook, execute:\n";
echo "curl -X POST '{$webhookUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"event\": \"onmessage\", \"session\": \"{$config['whatsapp_session_name']}\", \"data\": {\"from\": \"5511999999999\", \"to\": \"5511888888888\", \"text\": \"Teste webhook\"}}'\n\n";

// ==================== PARTE 6: VERIFICAR LOGS E STATUS ====================
echo "\n=== PARTE 6: Status Final e Verificações ===\n";

// Verificar se o Laravel está rodando
echo "1. Servidor Laravel:\n";
echo "   - URL: " . config('app.url', 'http://localhost:8000') . "\n";
echo "   - Verifique se está acessível\n\n";

// Verificar configurações do webhook novamente
echo "2. Configuração do Webhook:\n";
try {
    $client = Http::timeout(10)
        ->acceptJson()
        ->withToken($config['whatsapp_session_token']);

    $response = $client->get(rtrim($config['whatsapp_server_url'], '/') . '/api/' . $config['whatsapp_session_name'] . '/get-webhook');

    if ($response->successful()) {
        $webhookConfig = $response->json();
        echo "   ✅ Configurado: Sim\n";
        echo "   ✅ URL: " . ($webhookConfig['webhook'] ?? 'N/A') . "\n";
        echo "   ✅ Events: " . json_encode($webhookConfig['events'] ?? []) . "\n";

        if (($webhookConfig['webhook'] ?? '') !== $webhookUrl) {
            echo "   ⚠️  ATENÇÃO: URL do webhook diferente do esperado!\n";
            echo "      Esperado: {$webhookUrl}\n";
            echo "      Configurado: " . ($webhookConfig['webhook'] ?? 'N/A') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Não foi possível verificar webhook: " . $e->getMessage() . "\n";
}

// ==================== PARTE 7: RESUMO FINAL ====================
echo "\n========================================\n";
echo "           RESUMO FINAL\n";
echo "========================================\n";

echo "✅ CONFIGURAÇÕES:\n";
echo "   - Sessão: {$config['whatsapp_session_name']}\n";
echo "   - Status: CONECTADA\n";
echo "   - Servidor: {$config['whatsapp_server_url']}\n";
echo "   - Webhook: {$webhookUrl}\n\n";

echo "🎯 PRÓXIMOS PASSOS:\n";
echo "1. Envie uma mensagem do WhatsApp para testar recebimento\n";
echo "2. Verifique no dashboard se a mensagem aparece\n";
echo "3. Use o WhatsApp PDV para enviar mensagens\n\n";

echo "🔧 SOLUÇÃO DE PROBLEMAS:\n";
echo "Se mensagens NÃO aparecem no dashboard:\n";
echo "1. Verifique webhook no WPPConnect: {$config['whatsapp_server_url']}\n";
echo "2. Teste webhook manualmente com o comando curl acima\n";
echo "3. Verifique logs do Laravel: storage/logs/laravel.log\n";
echo "4. Verifique logs do WPPConnect\n\n";

echo "📞 SUPORTE:\n";
echo "Para mais ajuda, verifique:\n";
echo "- Logs do Laravel: tail -f storage/logs/laravel.log\n";
echo "- Logs do WPPConnect: console do navegador\n";
echo "- Status da sessão: {$config['whatsapp_server_url']}/sessions\n";

echo "\n✅ CONFIGURAÇÃO COMPLETA FINALIZADA!\n";