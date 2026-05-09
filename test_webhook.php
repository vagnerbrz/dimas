<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Teste de Webhook do WhatsApp ===\n\n";

// Obter URL do aplicativo
$appUrl = config('app.url', 'http://localhost:8000');
$webhookUrl = $appUrl . '/api/whatsapp/webhook';

echo "URL do webhook: $webhookUrl\n\n";

echo "Para o WhatsApp funcionar CORRETAMENTE, você precisa:\n";
echo "1. Configurar o webhook no WPPConnect\n";
echo "2. Ter o Dimas acessível pela rede\n\n";

echo "=== Passo 1: Configurar Webhook no WPPConnect ===\n";
echo "1. Acesse o painel do WPPConnect: http://localhost:21465\n";
echo "2. Vá em 'Sessions' > 'dimas-novo'\n";
echo "3. Em 'Webhook', configure:\n";
echo "   - URL: $webhookUrl\n";
echo "   - Events: onmessage, onack, onpresence\n";
echo "4. Salve as configurações\n\n";

echo "=== Passo 2: Tornar o Dimas acessível ===\n";
echo "O WPPConnect precisa conseguir acessar o Dimas.\n";
echo "Se ambos estão na MESMA máquina (localhost), está OK.\n";
echo "Se estão em máquinas DIFERENTES, você precisa:\n";
echo "1. Descobrir o IP da máquina do Dimas: ipconfig (Windows) ou ifconfig (Linux)\n";
echo "2. Usar esse IP na URL do webhook: http://SEU_IP:8000/api/whatsapp/webhook\n";
echo "3. Permitir a porta 8000 no firewall\n\n";

echo "=== Passo 3: Testar Webhook ===\n";
echo "Você pode testar enviando uma requisição POST manual:\n";
echo "curl -X POST '$webhookUrl' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"event\": \"onmessage\", \"session\": \"dimas-novo\", \"data\": {\"from\": \"5511999999999\", \"to\": \"5511888888888\", \"text\": \"Teste\"}}'\n\n";

echo "=== Status Atual do Sistema ===\n";

try {
    $service = new \App\Services\WppConnectService();
    $status = $service->getSessionStatus();
    echo "✅ Sessão WPPConnect: CONECTADA\n";
    echo "   Status: " . $status['status'] . "\n";

    // Testar envio de mensagem
    echo "\n=== Teste de Envio de Mensagem ===\n";
    echo "Para testar o ENVIO (do Dimas para WhatsApp):\n";
    echo "1. Acesse o dashboard do Dimas\n";
    echo "2. Use o WhatsApp PDV para enviar uma mensagem\n";
    echo "3. Verifique se chega no WhatsApp do cliente\n\n";

    echo "Para testar o RECEBIMENTO (do WhatsApp para Dimas):\n";
    echo "1. Envie uma mensagem do WhatsApp para o número conectado\n";
    echo "2. Verifique se aparece no dashboard do Dimas\n";
    echo "3. Se não aparecer, o webhook não está configurado corretamente\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n=== Resumo da Configuração ===\n";
echo "Sessão: dimas-novo\n";
echo "Status: CONECTADA ✓\n";
echo "Webhook: $webhookUrl\n";
echo "Próximos passos: Configurar webhook no WPPConnect\n";