<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderReceiptPrinter;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    fwrite(STDERR, "Nao foi possivel identificar o caminho do projeto.\n");
    exit(1);
}

$app = require $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['printing.prefer_env' => true]);

$product = new Product([
    'name' => 'Teste de impressao',
    'price' => 1.00,
]);

$item = new OrderItem([
    'quantity' => 1,
    'unit_price' => 1.00,
    'subtotal' => 1.00,
]);
$item->setRelation('product', $product);

$order = new Order([
    'type' => Order::TYPE_COUNTER,
    'status' => 'pending',
    'total_amount' => 1.00,
    'delivery_fee' => 0,
    'payment_method' => 'pix',
    'observations' => 'Recibo de teste do agente local.',
]);
$order->setRawAttributes(array_merge($order->getAttributes(), ['id' => 999999]), true);
$order->created_at = Carbon::now();
$order->setRelation('customer', new Customer([
    'name' => 'Cliente Teste',
    'phone' => '(00) 00000-0000',
]));
$order->setRelation('items', collect([$item]));

try {
    $app->make(OrderReceiptPrinter::class)->print($order);
    fwrite(STDOUT, "Recibo de teste enviado para a impressora.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Falha ao imprimir recibo de teste: {$e->getMessage()}\n");
    exit(1);
}
