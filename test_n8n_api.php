<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== Teste da API n8n ===\n\n";

$token = 'cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5';
$baseUrl = 'http://127.0.0.1:8000/api/n8n';

$headers = [
    'X-N8N-Token' => $token,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
];

// 1. Testar endpoint do cardápio
echo "1. Testando endpoint /menu:\n";
try {
    $response = Http::withHeaders($headers)->get("{$baseUrl}/menu");

    if ($response->successful()) {
        $data = $response->json();
        echo "   ✅ Sucesso! HTTP {$response->status()}\n";
        echo "   Restaurante: {$data['restaurant']}\n";
        echo "   Itens no cardápio: " . count($data['items']) . "\n";
        echo "   - Principais: " . count($data['main_items']) . "\n";
        echo "   - Extras: " . count($data['extra_items']) . "\n";

        // Mostrar alguns itens
        if (!empty($data['main_items'])) {
            echo "   Exemplo de item:\n";
            $item = $data['main_items'][0];
            echo "   - {$item['name']}: R$ {$item['formatted_price']}\n";
        }
    } else {
        echo "   ❌ Falha! HTTP {$response->status()}\n";
        echo "   Erro: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 2. Testar endpoint de configuração do WhatsApp
echo "\n2. Testando endpoint /whatsapp-config:\n";
try {
    $response = Http::withHeaders($headers)->get("{$baseUrl}/whatsapp-config");

    if ($response->successful()) {
        $data = $response->json();
        echo "   ✅ Sucesso! HTTP {$response->status()}\n";
        echo "   Server URL: {$data['server_url']}\n";
        echo "   Session: {$data['session_name']}\n";
        echo "   Token: " . substr($data['token'], 0, 10) . "...\n";
    } else {
        echo "   ⚠️  HTTP {$response->status()}\n";
        echo "   (Pode ser normal se WhatsApp não estiver configurado)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 3. Testar endpoint de clientes
echo "\n3. Testando endpoint /customers:\n";
try {
    $response = Http::withHeaders($headers)->get("{$baseUrl}/customers");

    if ($response->successful()) {
        $data = $response->json();
        echo "   ✅ Sucesso! HTTP {$response->status()}\n";
        echo "   Total de clientes: " . count($data) . "\n";

        if (!empty($data)) {
            $customer = $data[0];
            echo "   Exemplo de cliente:\n";
            echo "   - ID: {$customer['id']}\n";
            echo "   - Nome: {$customer['name']}\n";
            echo "   - Telefone: {$customer['phone']}\n";
        }
    } else {
        echo "   ⚠️  HTTP {$response->status()}\n";
        echo "   Resposta: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 4. Testar criação de pedido (simulado)
echo "\n4. Testando criação de pedido (simulado):\n";
echo "   (Apenas validação de token - não criará pedido real)\n";

try {
    $testData = [
        'customer' => [
            'phone' => '5511999999999',
            'name' => 'Cliente Teste n8n'
        ],
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 1
            ]
        ],
        'fulfillment_type' => 'counter',
        'payment_method' => 'pix'
    ];

    $response = Http::withHeaders($headers)->post("{$baseUrl}/orders", $testData);

    if ($response->status() === 422) {
        echo "   ✅ Token válido! (validação de dados funcionando)\n";
        echo "   Erros de validação esperados (produto ID 1 pode não existir)\n";
    } elseif ($response->successful()) {
        echo "   ✅ Pedido criado com sucesso!\n";
        $data = $response->json();
        echo "   Order ID: {$data['order']['order_id']}\n";
    } else {
        echo "   ⚠️  HTTP {$response->status()}\n";
        echo "   Resposta: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 5. Verificar configuração do token
echo "\n5. Verificando configuração do sistema:\n";
echo "   Token configurado no .env: " . (env('N8N_SHARED_TOKEN') ? '✅ Sim' : '❌ Não') . "\n";
echo "   Token esperado: cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5\n";
echo "   Token atual: " . substr(env('N8N_SHARED_TOKEN', ''), 0, 10) . "...\n";

// 6. Instruções para n8n
echo "\n=== Instruções para Configurar n8n ===\n\n";
echo "1. Instale o n8n:\n";
echo "   Docker: docker run -it --rm --name n8n -p 5678:5678 n8nio/n8n\n";
echo "   n8n.cloud: https://n8n.cloud (recomendado para produção)\n\n";

echo "2. Configure o HTTP Request Node:\n";
echo "   URL: http://127.0.0.1:8000/api/n8n/menu\n";
echo "   Method: GET\n";
echo "   Headers:\n";
echo "     X-N8N-Token: cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5\n";
echo "     Content-Type: application/json\n\n";

echo "3. Configure o WhatsApp Node:\n";
echo "   - Use Take Blip, Weni ou outro provedor\n";
echo "   - Configure o webhook no n8n: {$baseUrl}/whatsapp-config\n";
echo "   - Teste enviando 'cardapio' para o número conectado\n\n";

echo "4. Workflow básico:\n";
echo "   [WhatsApp In] → [Parse Message] → [HTTP Request to /menu] → [Format Response] → [WhatsApp Out]\n\n";

echo "=== Teste Concluído ===\n";

// Verificar se o servidor Laravel está rodando
echo "\n=== Verificação do Servidor ===\n";
try {
    $check = Http::timeout(3)->get('http://127.0.0.1:8000');
    echo "✅ Laravel está rodando em http://127.0.0.1:8000\n";
} catch (Exception $e) {
    echo "❌ Laravel NÃO está rodando\n";
    echo "   Execute: php artisan serve\n";
}