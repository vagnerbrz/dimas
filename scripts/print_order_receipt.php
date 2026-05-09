<?php

use App\Models\Order;
use App\Services\OrderReceiptPrinter;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orderId = (int) ($argv[1] ?? 0);

if ($orderId <= 0) {
    fwrite(STDERR, 'Informe o ID do pedido.' . PHP_EOL);
    exit(1);
}

$order = Order::with(['customer', 'address', 'items.product'])->find($orderId);

if (!$order) {
    fwrite(STDERR, "Pedido {$orderId} nao encontrado." . PHP_EOL);
    exit(1);
}

$app->make(OrderReceiptPrinter::class)->print($order);

echo "Pedido {$orderId} enviado para impressao." . PHP_EOL;
