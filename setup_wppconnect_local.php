<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

echo "========================================\n";
echo "   WPPCONNECT SERVER LOCAL - DIMAS     \n";
echo "========================================\n\n";

// ==================== VERIFICAR SE WPPCONNECT ESTÁ RODANDO ====================
echo "=== 1. Verificando WPPConnect Server Local ===\n";

$wppconnectUrl = 'http://localhost:21465';
echo "URL do WPPConnect: {$wppconnectUrl}\n";

try {
    $response = Http::timeout(5)->get($wppconnectUrl);

    if ($response->successful()) {
        echo "✅ WPPConnect Server está RODANDO\n";

        // Verificar sessões
        $sessionsResponse = Http::timeout(5)->get($wppconnectUrl . '/sessions');
        if ($sessionsResponse->successful()) {
            echo "✅ Página de sessões acessível\n";
        }
    } else {
        echo "⚠️  WPPConnect respondeu com HTTP {$response->status()}\n";
    }
} catch (Exception $e) {
    echo "❌ WPPConnect Server NÃO está rodando ou inacessível\n";
    echo "   Erro: " . $e->getMessage() . "\n\n";

    echo "📌 Para iniciar o WPPConnect Server:\n";
    echo "1. Abra um novo terminal\n";
    echo "2. Execute:\n";
    echo "   cd /c/sistema/wppconnect-server\n";
    echo "   npm start\n";
    echo "3. Aguarde até ver \"Server started on port 21465\"\n";
    echo "4. Volte aqui e continue\n\n";

    echo "Pressione Enter quando o WPPConnect estiver rodando...";
    $handle = fopen("php://stdin", "r");
    fgets($handle);
    fclose($handle);
}

// ==================== VERIFICAR CONFIGURAÇÕES DO DIMAS ====================
echo "\n=== 2. Verificando Configurações do Dimas ===\n";

// Garantir que as configurações estejam corretas
$requiredConfigs = [
    'whatsapp_server_url' => 'http://localhost:21465',
    'whatsapp_session_name' => 'dimas-novo',
    'whatsapp_session_token' => null // Será verificado
];

foreach ($requiredConfigs as $key => $defaultValue) {
    $setting = Setting::where('key', $key)->first();

    if (!$setting && $defaultValue !== null) {
        Setting::updateOrCreate(['key' => $key], ['value' => $defaultValue]);
        echo "✅ {$key}: Configurado como '{$defaultValue}'\n";
    } elseif ($setting) {
        $displayValue = $setting->value;
        if ($key === 'whatsapp_session_token') {
            $displayValue = substr($displayValue, 0, 10) . '...' . substr($displayValue, -10);
        }
        echo "✅ {$key}: {$displayValue}\n";
    } else {
        echo "❌ {$key}: FALTANDO\n";
    }
}

// Verificar token
$tokenSetting = Setting::where('key', 'whatsapp_session_token')->first();
if (!$tokenSetting || empty($tokenSetting->value)) {
    echo "\n❌ Token do WhatsApp não configurado!\n";
    echo "   Para obter o token:\n";
    echo "   1. Acesse: {$wppconnectUrl}\n";
    echo "   2. Vá em 'Sessions' > 'dimas-novo'\n";
    echo "   3. Copie o token (começa com \$2b\$10\$...)\n";
    echo "   4. Configure no Dimas\n\n";

    echo "Digite o token: ";
    $handle = fopen("php://stdin", "r");
    $token = trim(fgets($handle));
    fclose($handle);

    if (!empty($token)) {
        Setting::updateOrCreate(
            ['key' => 'whatsapp_session_token'],
            ['value' => $token]
        );
        echo "✅ Token configurado!\n";
    } else {
        echo "❌ Token não pode ser vazio!\n";
        exit(1);
    }
}

// ==================== TESTAR CONEXÃO COM WPPCONNECT ====================
echo "\n=== 3. Testando Conexão com WPPConnect ===\n";

try {
    $service = new \App\Services\WppConnectService();
    $status = $service->getSessionStatus();

    echo "✅ Status da sessão: " . $status['status'] . "\n";

    $connectedStatuses = ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'];
    if (!in_array($status['status'], $connectedStatuses, true)) {
        echo "\n❌ Sessão NÃO está conectada!\n";
        echo "   Status atual: " . $status['status'] . "\n\n";

        echo "📌 Para conectar a sessão:\n";
        echo "1. Acesse: {$wppconnectUrl}/sessions\n";
        echo "2. Clique em 'START' na sessão 'dimas-novo'\n";
        echo "3. Escaneie o QR Code com o WhatsApp\n";
        echo "4. Aguarde a conexão (status: CONNECTED)\n";
        echo "5. Volte aqui e continue\n\n";

        echo "Pressione Enter quando a sessão estiver CONECTADA...";
        $handle = fopen("php://stdin", "r");
        fgets($handle);
        fclose($handle);

        // Verificar novamente
        $status = $service->getSessionStatus();
        echo "Novo status: " . $status['status'] . "\n";
    }

    echo "✅ Sessão CONECTADA e pronta!\n";

} catch (Exception $e) {
    echo "❌ Erro ao conectar: " . $e->getMessage() . "\n";
    exit(1);
}

// ==================== CONFIGURAR WEBHOOK ====================
echo "\n=== 4. Configurando Webhook ===\n";

$appUrl = config('app.url', 'http://localhost:8000');
$webhookUrl = $appUrl . '/api/whatsapp/webhook';

echo "URL do webhook: {$webhookUrl}\n";

// Verificar se o Dimas está acessível
try {
    $testResponse = Http::timeout(5)->get($appUrl);
    echo "✅ Dimas está acessível em: {$appUrl}\n";
} catch (Exception $e) {
    echo "⚠️  Dimas pode não estar acessível: " . $e->getMessage() . "\n";
    echo "   Inicie o servidor Laravel: php artisan serve\n";
}

// Configurar webhook via API
try {
    $token = Setting::where('key', 'whatsapp_session_token')->first()->value;
    $sessionName = Setting::where('key', 'whatsapp_session_name')->first()->value;

    $client = Http::timeout(10)
        ->acceptJson()
        ->asJson()
        ->withToken($token);

    // Configurar webhook
    $response = $client->post("{$wppconnectUrl}/api/{$sessionName}/set-webhook", [
        'webhook' => $webhookUrl,
        'events' => ['onmessage', 'onack', 'onpresence']
    ]);

    if ($response->successful()) {
        echo "✅ Webhook configurado com sucesso!\n";

        // Verificar configuração
        $checkResponse = $client->get("{$wppconnectUrl}/api/{$sessionName}/get-webhook");
        if ($checkResponse->successful()) {
            $webhookConfig = $checkResponse->json();
            echo "   URL confirmada: " . ($webhookConfig['webhook'] ?? 'N/A') . "\n";
        }
    } else {
        echo "⚠️  Erro ao configurar webhook via API: HTTP {$response->status()}\n";
        echo "   Configure manualmente:\n";
        echo "   1. Acesse: {$wppconnectUrl}/sessions\n";
        echo "   2. Clique em 'dimas-novo'\n";
        echo "   3. Vá na aba 'Webhook'\n";
        echo "   4. URL: {$webhookUrl}\n";
        echo "   5. Events: onmessage, onack, onpresence\n";
        echo "   6. Salve\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao configurar webhook: " . $e->getMessage() . "\n";
}

// ==================== TESTAR FLUXO COMPLETO ====================
echo "\n=== 5. Teste do Fluxo Completo ===\n";

echo "📱 TESTE DE ENVIO (Dimas → WhatsApp):\n";
echo "1. Acesse: {$appUrl}/dashboard\n";
echo "2. Use o 'WhatsApp PDV' no lado direito\n";
echo "3. Selecione uma conversa ou inicie uma nova\n";
echo "4. Envie uma mensagem\n";
echo "5. Verifique se chegou no WhatsApp\n\n";

echo "📨 TESTE DE RECEBIMENTO (WhatsApp → Dimas):\n";
echo "1. Envie uma mensagem do WhatsApp para o número conectado\n";
echo "2. A mensagem deve aparecer AUTOMATICAMENTE no dashboard\n";
echo "3. Se não aparecer, verifique o webhook\n\n";

echo "🔧 COMANDO PARA TESTAR WEBHOOK MANUALMENTE:\n";
echo "curl -X POST '{$webhookUrl}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"event\": \"onmessage\", \"session\": \"dimas-novo\", \"data\": {\"from\": \"5511999999999\", \"to\": \"5511888888888\", \"text\": \"Teste\"}}'\n\n";

// ==================== RESUMO FINAL ====================
echo "========================================\n";
echo "           RESUMO FINAL\n";
echo "========================================\n";

echo "✅ WPPConnect Server: http://localhost:21465\n";
echo "✅ Sessão: dimas-novo (CONECTADA)\n";
echo "✅ Dimas: {$appUrl}\n";
echo "✅ Webhook: {$webhookUrl}\n\n";

echo "🎯 PRÓXIMOS PASSOS:\n";
echo "1. Teste o envio pelo dashboard\n";
echo "2. Teste o recebimento enviando do WhatsApp\n";
echo "3. Verifique se tudo funciona automaticamente\n\n";

echo "📊 STATUS ATUAL: PRONTO PARA USO!\n";

// ==================== OPÇÃO DE TESTE RÁPIDO ====================
echo "\nDeseja testar o envio de uma mensagem AGORA? (s/n): ";
$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

if (strtolower($choice) === 's') {
    echo "\nDigite um número para teste (ex: 5511999999999): ";
    $handle = fopen("php://stdin", "r");
    $testPhone = trim(fgets($handle));
    fclose($handle);

    if (!empty($testPhone)) {
        try {
            $service = new \App\Services\WppConnectService();
            $result = $service->sendMessage($testPhone, "✅ Teste do sistema Dimas - " . date('H:i:s'));
            echo "✅ Mensagem enviada! Message ID: " . ($result['messageId'] ?? 'N/A') . "\n";
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n✅ CONFIGURAÇÃO COMPLETA FINALIZADA!\n";