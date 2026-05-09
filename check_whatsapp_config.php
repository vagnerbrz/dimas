<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;

echo "=== Verificando Configurações do WhatsApp ===\n\n";

// Verificar configurações existentes
$whatsappSettings = Setting::where('key', 'like', '%whatsapp%')->get();

if ($whatsappSettings->isEmpty()) {
    echo "❌ Nenhuma configuração do WhatsApp encontrada!\n";
    echo "As configurações necessárias são:\n";
    echo "1. whatsapp_server_url - URL do servidor WPPConnect (ex: http://localhost:21465)\n";
    echo "2. whatsapp_session_name - Nome da sessão (ex: dimas-novo)\n";
    echo "3. whatsapp_session_token - Token da sessão\n\n";

    echo "Deseja configurar agora? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 's') {
        echo "\n=== Configuração do WhatsApp ===\n";

        echo "URL do servidor WPPConnect [http://localhost:21465]: ";
        $handle = fopen("php://stdin", "r");
        $serverUrl = trim(fgets($handle));
        fclose($handle);
        $serverUrl = $serverUrl ?: 'http://localhost:21465';

        echo "Nome da sessão [dimas-novo]: ";
        $handle = fopen("php://stdin", "r");
        $sessionName = trim(fgets($handle));
        fclose($handle);
        $sessionName = $sessionName ?: 'dimas-novo';

        echo "Token da sessão: ";
        $handle = fopen("php://stdin", "r");
        $token = trim(fgets($handle));
        fclose($handle);

        if (empty($token)) {
            echo "❌ Token é obrigatório!\n";
            exit(1);
        }

        // Salvar configurações
        Setting::updateOrCreate(
            ['key' => 'whatsapp_server_url'],
            ['value' => $serverUrl]
        );

        Setting::updateOrCreate(
            ['key' => 'whatsapp_session_name'],
            ['value' => $sessionName]
        );

        Setting::updateOrCreate(
            ['key' => 'whatsapp_session_token'],
            ['value' => $token]
        );

        echo "\n✅ Configurações salvas com sucesso!\n";

        // Testar conexão
        echo "\n=== Testando conexão com WPPConnect ===\n";
        try {
            $service = new \App\Services\WppConnectService();
            $status = $service->getSessionStatus();
            echo "✅ Status da sessão: " . $status['status'] . "\n";

            // Verificar manualmente se está conectado
        $connectedStatuses = ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'];
        if (in_array($status['status'], $connectedStatuses, true)) {
                echo "✅ Sessão está CONECTADA e pronta para uso!\n";
            } else {
                echo "⚠️  Sessão NÃO está conectada. Status: " . $status['status'] . "\n";
                echo "Verifique se o WPPConnect está rodando e a sessão está ativa.\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao testar conexão: " . $e->getMessage() . "\n";
            echo "Verifique:\n";
            echo "1. O servidor WPPConnect está rodando em $serverUrl?\n";
            echo "2. A sessão '$sessionName' existe no WPPConnect?\n";
            echo "3. O token está correto?\n";
        }
    }
} else {
    echo "✅ Configurações encontradas:\n";
    foreach ($whatsappSettings as $setting) {
        $value = $setting->value;
        if ($setting->key === 'whatsapp_session_token') {
            $value = substr($value, 0, 10) . '...' . substr($value, -10);
        }
        echo "- {$setting->key}: {$value}\n";
    }

    echo "\n=== Testando conexão com WPPConnect ===\n";
    try {
        $service = new \App\Services\WppConnectService();
        $status = $service->getSessionStatus();
        echo "✅ Status da sessão: " . $status['status'] . "\n";

        // Verificar manualmente se está conectado
        $connectedStatuses = ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'];
        if (in_array($status['status'], $connectedStatuses, true)) {
            echo "✅ Sessão está CONECTADA e pronta para uso!\n";

            // Testar envio de mensagem de teste
            echo "\n=== Testando envio de mensagem ===\n";
            echo "Digite um número de telefone para teste (com DDD, ex: 5511999999999): ";
            $handle = fopen("php://stdin", "r");
            $testPhone = trim(fgets($handle));
            fclose($handle);

            if (!empty($testPhone)) {
                try {
                    $result = $service->sendMessage($testPhone, "✅ Teste do sistema Dimas - " . date('H:i:s'));
                    echo "✅ Mensagem de teste enviada com sucesso!\n";
                    echo "   Message ID: " . ($result['messageId'] ?? 'N/A') . "\n";
                } catch (Exception $e) {
                    echo "❌ Erro ao enviar mensagem: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "⚠️  Sessão NÃO está conectada. Status: " . $status['status'] . "\n";
            echo "Verifique se o WPPConnect está rodando e a sessão está ativa.\n";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao testar conexão: " . $e->getMessage() . "\n";

        // Oferecer para reconfigurar
        echo "\nDeseja reconfigurar as configurações do WhatsApp? (s/n): ";
        $handle = fopen("php://stdin", "r");
        $response = trim(fgets($handle));
        fclose($handle);

        if (strtolower($response) === 's') {
            // Limpar configurações existentes
            Setting::where('key', 'like', '%whatsapp%')->delete();
            echo "✅ Configurações antigas removidas. Execute novamente para configurar.\n";
        }
    }
}

echo "\n=== Fim da verificação ===\n";