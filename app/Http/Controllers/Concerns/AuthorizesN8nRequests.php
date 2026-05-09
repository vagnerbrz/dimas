<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesN8nRequests
{
    protected function ensureAuthorized(Request $request): void
    {
        $expectedToken = (string) config('services.n8n.token', '');

        if ($expectedToken === '') {
            abort(503, 'N8N_SHARED_TOKEN nao configurado.');
        }

        $providedToken = (string) (
            $request->header('X-N8N-Token')
            ?: preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', ''))
        );

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            abort(401, 'Token invalido.');
        }
    }
}
