<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;

echo "=== Configuração do Servidor WPPConnect ===\n\n";

// Verificar configuração atual
$currentUrl = Setting::where('key', 'whatsapp_server_url')->first();
if ($currentUrl) {
    echo "URL atual: " . $currentUrl->value . "\n";
} else {
    echo "URL não configurada (usando padrão: http://localhost:21465)\n";
}

echo "\nOnde está rodando o servidor WPPConnect?\n";
echo "1. Localhost (http://localhost:21465) - Padrão\n";
echo "2. Outro endereço\n";
echo "3. Testar conexão com URL atual\n";
echo "Escolha (1-3): ";

$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

$serverUrl = 'http://localhost:21465';

if ($choice === '2') {
    echo "Digite a URL completa (ex: http://192.168.1.100:21465): ";
    $handle = fopen("php://stdin", "r");
    $serverUrl = trim(fgets($handle));
    fclose($handle);

    if (empty($serverUrl)) {
        echo "❌ URL não pode ser vazia!\n";
        exit(1);
    }

    // Salvar configuração
    Setting::updateOrCreate(
        ['key' => 'whatsapp_server_url'],
        ['value' => $serverUrl]
    );

    echo "✅ URL configurada: $serverUrl\n";
} elseif ($choice === '1') {
    // Usar localhost padrão
    Setting::updateOrCreate(
        ['key' => 'whatsapp_server_url'],
        ['value' => $serverUrl]
    );
    echo "✅ URL configurada como localhost padrão\n";
}

// Testar conexão
echo "\n=== Testando conexão com WPPConnect ===\n";

try {
    $service = new \App\Services\WppConnectService();

    // Verificar URL que será usada
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('serverUrl');
    $method->setAccessible(true);
    $actualUrl = $method->invoke($service);

    echo "URL que será usada: $actualUrl\n";

    $status = $service->getSessionStatus();
    echo "✅ Status da sessão: " . $status['status'] . "\n";

    // Verificar manualmente se está conectado
    $connectedStatuses = ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'];
    if (in_array($status['status'], $connectedStatuses, true)) {
        echo "✅ Sessão está CONECTADA e pronta para uso!\n";

        // Testar webhook
        echo "\n=== Verificando webhook ===\n";
        echo "O webhook do Dimas está configurado no WPPConnect?\n";
        echo "URL do webhook deve ser: http://SEU_IP:8000/api/whatsapp/webhook\n";
        echo "Verifique no painel do WPPConnect se o webhook está configurado.\n";
    } else {
        echo "⚠️  Sessão NÃO está conectada. Status: " . $status['status'] . "\n";
        echo "Verifique:\n";
        echo "1. O WPPConnect está rodando?\n";
        echo "2. A sessão 'dimas-novo' está ativa no WPPConnect?\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar conexão: " . $e->getMessage() . "\n";

    echo "\n=== Solução de Problemas ===\n";
    echo "1. Verifique se o servidor WPPConnect está rodando:\n";
    echo "   - Acesse: $serverUrl\n";
    echo "   - Deve mostrar a interface do WPPConnect\n";
    echo "\n2. Verifique se a sessão 'dimas-novo' existe:\n";
    echo "   - Acesse: $serverUrl/sessions\n";
    echo "   - Deve listar a sessão 'dimas-novo'\n";
    echo "\n3. Verifique o token:\n";
    echo "   - Token atual: " . substr(Setting::where('key', 'whatsapp_session_token')->first()->value ?? '', 0, 20) . "...\n";
    echo "   - Confira no painel do WPPConnect se o token está correto\n";
}

echo "\n=== Configuração Completa ===\n";
$settings = Setting::where('key', 'like', '%whatsapp%')->get();
foreach ($settings as $setting) {
    $value = $setting->value;
    if ($setting->key === 'whatsapp_session_token') {
        $value = substr($value, 0, 10) . '...' . substr($value, -10);
    }
    echo "- {$setting->key}: {$value}\n";
}