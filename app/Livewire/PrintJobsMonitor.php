<?php

namespace App\Livewire;

use App\Models\LocalPrintJob;
use App\Services\OrderReceiptPrinter;
use Livewire\Component;

/**
 * Pagina "Impressao": estado do agente local e historico da fila,
 * com reenvio de cupons que falharam.
 */
class PrintJobsMonitor extends Component
{
    public string $filter = 'all';

    public function retry(int $jobId): void
    {
        $job = LocalPrintJob::findOrFail($jobId);

        if (in_array($job->status, [LocalPrintJob::STATUS_FAILED, LocalPrintJob::STATUS_PRINTED], true)) {
            LocalPrintJob::enqueueReprint($job->order_id);
            session()->flash('print_message', "Pedido #{$job->order_id} enviado novamente para a impressora.");
        }
    }

    public function render(OrderReceiptPrinter $printer)
    {
        $query = LocalPrintJob::with('order.customer')->latest('id');

        if ($this->filter === 'failed') {
            $query->where('status', LocalPrintJob::STATUS_FAILED);
        } elseif ($this->filter === 'queued') {
            $query->whereIn('status', [LocalPrintJob::STATUS_PENDING, LocalPrintJob::STATUS_PRINTING]);
        }

        return view('livewire.print-jobs-monitor', [
            'localMode' => $printer->isLocalConnection(),
            'connection' => $printer->configuration()['print_connection'] ?? null,
            'status' => LocalPrintJob::agentStatus(),
            'jobs' => $query->limit(50)->get(),
            'failedCount' => LocalPrintJob::exhausted()->count(),
            'maxAttempts' => LocalPrintJob::maxAttempts(),
        ]);
    }
}
