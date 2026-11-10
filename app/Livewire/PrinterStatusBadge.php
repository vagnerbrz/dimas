<?php

namespace App\Livewire;

use App\Models\LocalPrintJob;
use App\Services\OrderReceiptPrinter;
use Livewire\Component;

/**
 * Indicador no topo do painel: agente de impressao online/offline
 * e quantidade de cupons que falharam e precisam de atencao.
 */
class PrinterStatusBadge extends Component
{
    public function render(OrderReceiptPrinter $printer)
    {
        $enabled = $printer->isLocalConnection();

        return view('livewire.printer-status-badge', [
            'enabled' => $enabled,
            'status' => $enabled ? LocalPrintJob::agentStatus() : null,
            'failedCount' => $enabled ? LocalPrintJob::exhausted()->count() : 0,
            'queuedCount' => $enabled ? LocalPrintJob::whereIn('status', [
                LocalPrintJob::STATUS_PENDING,
                LocalPrintJob::STATUS_PRINTING,
            ])->count() : 0,
        ]);
    }
}
