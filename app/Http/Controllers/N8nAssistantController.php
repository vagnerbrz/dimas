<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesN8nRequests;
use App\Models\Setting;
use App\Models\Order;
use App\Services\N8nRestaurantAssistantService;
use App\Services\WhatsAppService;
use App\Services\WppConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nAssistantController extends Controller
{
    use AuthorizesN8nRequests;

    public function menu(Request $request, N8nRestaurantAssistantService $service): JsonResponse
    {
        $this->ensureAuthorized($request);

        return response()->json($service->getMenu());
    }

    public function createOrder(Request $request, N8nRestaurantAssistantService $service): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'customer.phone' => ['required', 'string'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'fulfillment_type' => ['required', 'in:' . implode(',', [Order::TYPE_COUNTER, Order::TYPE_DELIVERY])],
            'payment_method' => ['required', 'in:pix,debit,credit,cash'],
            'change_for' => ['nullable'],
            'assistant_notes' => ['nullable', 'string'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:50'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'max:10'],
            'address.zip_code' => ['nullable', 'string', 'max:20'],
            'address.reference' => ['nullable', 'string', 'max:255'],
            'address.latitude' => ['nullable'],
            'address.longitude' => ['nullable'],
        ]);

        return response()->json([
            'success' => true,
            'order' => $service->createOrder($validated),
        ]);
    }

    public function suspend(Request $request, N8nRestaurantAssistantService $service): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        return response()->json([
            'success' => true,
            'suspension' => $service->suspendConversation(
                $validated['phone'],
                $validated['name'] ?? null,
                (int) ($validated['minutes'] ?? 15),
            ),
        ]);
    }

    public function whatsappConfig(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $serverUrl = rtrim((string) Setting::get('whatsapp_server_url', 'http://localhost:21465'), '/');
        $sessionName = (string) Setting::get('whatsapp_session_name', 'dimas');
        $token = (string) Setting::get('whatsapp_session_token', '');

        if (str_contains($token, ':')) {
            [, $token] = explode(':', $token, 2);
        }

        if ($sessionName === '' || $token === '') {
            abort(503, 'Configuracao do WhatsApp indisponivel.');
        }

        return response()->json([
            'server_url' => $serverUrl,
            'session_name' => $sessionName,
            'token' => $token,
            'send_message_url' => $serverUrl . '/api/' . $sessionName . '/send-message',
            'send_voice_url' => $serverUrl . '/api/' . $sessionName . '/send-voice-base64',
        ]);
    }

    public function sendText(
        Request $request,
        WppConnectService $wppConnectService,
        WhatsAppService $whatsAppService
    ): JsonResponse {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'message' => ['required', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $wppConnectService->sendMessage($validated['phone'], $validated['message']);
        $whatsAppService->markRecentBotOutbound(
            $validated['phone'],
            $validated['contact_name'] ?? null,
        );

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }
}
