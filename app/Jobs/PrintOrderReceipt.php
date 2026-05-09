<?php

namespace App\Jobs;

use App\Models\LocalPrintJob;
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
            $printConfig = $printer->configuration();

            Log::info('Iniciando job de impressao automatica.', [
                'order_id' => $order->id,
                'print_connection' => $printConfig['print_connection'] ?? null,
                'print_enabled' => $printConfig['print_enabled'] ?? null,
            ]);

            if ($printer->isLocalConnection()) {
                LocalPrintJob::createIfNotExists($order->id);
                Log::info('Pedido adicionado a fila de impressao local.', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            $printer->print($order);
            Log::info('Job de impressao automatica concluido.', [
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao imprimir pedido via ESC/POS.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
