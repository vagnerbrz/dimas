<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderReceiptPrinter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintOrderReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId)
    {
    }

    public function handle(OrderReceiptPrinter $printer): void
    {
        $order = Order::with(['customer', 'address', 'items.product'])->find($this->orderId);

        if (!$order) {
            Log::warning('Impressao ignorada: pedido nao encontrado.', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        try {
            $printer->print($order);
        } catch (\Throwable $e) {
            Log::error('Falha ao imprimir pedido via ESC/POS.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
