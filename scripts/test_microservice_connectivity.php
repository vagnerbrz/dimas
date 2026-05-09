<?php

// Script simples para testar conectividade sem precisar do banco
// Lê diretamente das variáveis de ambiente

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "🔍 Testando conectividade com o micro serviço...\n";

// Ler configurações diretamente do .env
$url = env('PRINT_MICROSERVICE_URL');
$token = env('PRINT_MICROSERVICE_TOKEN');
$connection = env('PRINT_CONNECTION');

if ($connection !== 'microservice') {
    echo "❌ PRINT_CONNECTION não está configurado como 'microservice'\n";
    echo "Configure PRINT_CONNECTION=microservice no .env\n";
    exit(1);
}

if (!$url || !$token) {
    echo "❌ Configurações ausentes:\n";
    echo "PRINT_MICROSERVICE_URL: " . ($url ? 'OK' : '❌ Ausente') . "\n";
    echo "PRINT_MICROSERVICE_TOKEN: " . ($token ? 'OK' : '❌ Ausente') . "\n";
    exit(1);
}

echo "URL: $url\n";
echo "Token: " . substr($token, 0, 10) . "...\n\n";

try {
    // Teste health check
    echo "1. Testando health check...\n";
    $healthUrl = rtrim($url, '/print') . '/health';
    echo "   Fazendo GET para: $healthUrl\n";

    $response = Http::timeout(10)->get($healthUrl);

    if ($response->successful()) {
        $data = $response->json();
        if (($data['status'] ?? '') === 'ok') {
            echo "✅ Health check OK\n";
        } else {
            echo "⚠️  Health check retornou dados inesperados: " . json_encode($data) . "\n";
        }
    } else {
        echo "❌ Health check falhou: " . $response->status() . " - " . $response->body() . "\n";
        exit(1);
    }

    // Teste print (com dados de teste) - apenas testar conectividade HTTP
    echo "\n2. Testando endpoint de impressão (conectividade HTTP)...\n";
    $testContent = base64_encode("\x1b@\nTESTE DE IMPRESSAO\n\x1dV0");
    echo "   Enviando dados de teste (não tentará imprimir fisicamente)...\n";

    $response = Http::withToken($token)
        ->timeout(5)  // Timeout menor para teste rápido
        ->post($url, [
            'encoding' => 'base64',
            'content' => $testContent,
            'connection' => 'file',  // Usar file para não tentar rede
            'file_path' => '/tmp/test_receipt.txt',  // Arquivo de teste
        ]);

    if ($response->successful()) {
        $data = $response->json();
        if (($data['success'] ?? false) === true) {
            echo "✅ Teste de impressão enviado com sucesso\n";
            echo "   Resposta: " . json_encode($data) . "\n";
        } else {
            echo "⚠️  Impressão pode ter falhado, mas HTTP OK: " . json_encode($data) . "\n";
        }
    } else {
        echo "❌ Teste de impressão falhou: " . $response->status() . " - " . $response->body() . "\n";
        // Não sair, pois pode ser problema de impressora, não de conectividade
    }

} catch (Exception $e) {
    echo "❌ Erro de conectividade: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Conectividade OK! O Laravel pode se comunicar com o micro serviço.\n";
echo "Nota: O erro de impressão é normal neste teste (arquivo não existe).\n";
echo "Para produção, configure a impressora corretamente no micro serviço.\n";
echo "\nPróximos passos:\n";
echo "1. Configure um túnel reverso (ngrok/Cloudflare) para expor o micro serviço\n";
echo "2. Atualize PRINT_MICROSERVICE_URL no servidor hospedado\n";
echo "3. Configure PRINT_ENABLED=true para ativar impressão automática\n";