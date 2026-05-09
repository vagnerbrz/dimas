<?php

namespace App\Http\Controllers;

use App\Models\LocalPrintJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocalPrintJobController extends Controller
{
    protected function authorizeRequest(Request $request): void
    {
        $token = trim((string) $request->header('X-LOCAL-PRINT-TOKEN'));

        if ($token === '') {
            $authorization = (string) $request->header('Authorization');
            if (str_starts_with($authorization, 'Bearer ')) {
                $token = trim(substr($authorization, 7));
            }
        }

        $expected = (string) env('LOCAL_PRINT_API_TOKEN', '');

        if ($expected === '' || !hash_equals($expected, $token)) {
            abort(401, 'Unauthorized');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeRequest($request);

        $jobs = LocalPrintJob::pollable()
            ->with(['order.customer', 'order.address', 'order.items.product'])
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        return response()->json($jobs->map(function (LocalPrintJob $job) {
            return [
                'id' => $job->id,
                'order_id' => $job->order_id,
                'attempts' => $job->attempts,
                'status' => $job->status,
                'created_at' => $job->created_at?->toISOString(),
                'order' => $job->order?->toArray(),
            ];
        }));
    }

    public function complete(Request $request, LocalPrintJob $job)
    {
        $this->authorizeRequest($request);

        $job->update([
            'status' => LocalPrintJob::STATUS_PRINTED,
            'last_attempted_at' => now(),
            'error_message' => null,
        ]);

        return response()->json(['success' => true]);
    }

    public function fail(Request $request, LocalPrintJob $job)
    {
        $this->authorizeRequest($request);

        $job->increment('attempts');
        $job->update([
            'status' => LocalPrintJob::STATUS_FAILED,
            'last_attempted_at' => now(),
            'error_message' => trim((string) $request->input('error_message', '')), 
        ]);

        return response()->json(['success' => true]);
    }
}
