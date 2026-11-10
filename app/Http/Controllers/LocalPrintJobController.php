<?php

namespace App\Http\Controllers;

use App\Models\LocalPrintJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * API consumida pelo agente local (scripts/local_printer_agent.php).
 * O agente faz conexoes de saida; o servidor nunca chama o restaurante.
 */
class LocalPrintJobController extends Controller
{
    protected function authorizeRequest(Request $request): void
    {
        $token = trim((string) $request->header('X-LOCAL-PRINT-TOKEN'));

        if ($token === '') {
            $token = trim((string) $request->bearerToken());
        }

        $expected = (string) config('printing.local_api_token', '');

        if ($expected === '' || !hash_equals($expected, $token)) {
            abort(401, 'Unauthorized');
        }
    }

    protected function recordHeartbeat(Request $request): void
    {
        LocalPrintJob::recordHeartbeat(
            mb_substr((string) $request->header('X-Agent-Name', ''), 0, 100) ?: null,
            mb_substr((string) $request->header('X-Agent-Version', ''), 0, 20) ?: null,
        );
    }

    /**
     * Entrega ao agente os proximos jobs, ja reservados para ele.
     * Cada consulta tambem conta como sinal de vida do agente.
     */
    public function index(Request $request)
    {
        $this->authorizeRequest($request);
        $this->recordHeartbeat($request);

        $agentName = mb_substr((string) $request->header('X-Agent-Name', ''), 0, 100) ?: null;
        $jobs = LocalPrintJob::claimNext(10, $agentName);

        return response()->json($jobs->map(fn (LocalPrintJob $job) => [
            'id' => $job->id,
            'order_id' => $job->order_id,
            'attempts' => $job->attempts,
            'status' => $job->status,
            'reason' => $job->reason,
            'created_at' => $job->created_at?->toISOString(),
            'order' => $job->order?->toArray(),
        ])->values());
    }

    public function heartbeat(Request $request)
    {
        $this->authorizeRequest($request);
        $this->recordHeartbeat($request);

        return response()->json(['success' => true]);
    }

    public function complete(Request $request, LocalPrintJob $job)
    {
        $this->authorizeRequest($request);

        // Confirmacao repetida (o agente reenvia se a resposta se perder) e inofensiva.
        if ($job->status !== LocalPrintJob::STATUS_PRINTED) {
            $job->markPrinted();
        }

        return response()->json(['success' => true]);
    }

    public function fail(Request $request, LocalPrintJob $job)
    {
        $this->authorizeRequest($request);

        if ($job->status !== LocalPrintJob::STATUS_PRINTED) {
            $job->markFailed(trim((string) $request->input('error_message', 'Erro desconhecido')));
        }

        return response()->json(['success' => true]);
    }
}
