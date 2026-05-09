<?php

namespace App\Http\Controllers;

use App\Events\WhatsAppMessageReceived;
use App\Events\WhatsAppMessageSent;
use App\Services\HumanConversationService;
use App\Services\WhatsAppService;
use App\Services\WppConnectService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function webhook(
        Request $request,
        WhatsAppService $service,
        WppConnectService $wppConnect,
        HumanConversationService $humanConversationService
    ): JsonResponse
    {
        $event = strtolower($this->stringValue($request->input('event', '')));
        $phone = $this->resolveContactPhone($request, $event);
        $replyTarget = $this->resolveReplyTarget($request, $event);
        $messageId = $this->stringValue($request->input('id'));
        $message = $this->stringValue($request->input('body'));
        $interactionId = $this->extractInteractionId($request);
        $location = $this->extractLocation($request);
        $contactName = $this->extractContactName($request);
        $fromMe = $request->boolean('fromMe') || $event === 'onselfmessage';
        $customerContactName = $fromMe ? null : $contactName;

        Log::info('Webhook do WhatsApp recebido.', [
            'event' => $event,
            'phone' => $phone,
            'message_id' => $messageId,
            'from_me' => $fromMe,
            'has_message' => $message !== '',
            'interaction_id' => $interactionId,
            'has_location' => $location !== null,
            'contact_name' => $contactName,
            'reply_target' => $replyTarget,
        ]);

        if (!in_array($event, ['onmessage', 'onselfmessage'], true)) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'Unsupported event',
            ], 202);
        }

        if ($phone === '' || str_ends_with($phone, '@g.us') || $phone === 'status@broadcast') {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'Group, broadcast or missing peer',
            ], 202);
        }

        if ($fromMe) {
            if ($this->isDuplicateWebhook($event, $phone, $messageId, $interactionId, $message)) {
                Log::info('Webhook duplicado ignorado.', [
                    'event' => $event,
                    'phone' => $phone,
                    'message_id' => $messageId,
                ]);

                return response()->json([
                    'status' => 'ignored',
                    'reason' => 'Duplicate webhook',
                ], 202);
            }

            $selfMessageAnalysis = $this->classifySelfMessage($service, $phone, $customerContactName, $messageId);

            if ($selfMessageAnalysis['is_bot_message']) {
                Log::info('Mensagem propria classificada como envio do bot.', [
                    'phone' => $phone,
                    'message_id' => $messageId,
                    'classification' => $selfMessageAnalysis['classification'],
                    'reason' => $selfMessageAnalysis['reason'],
                ]);

                return response()->json([
                    'status' => 'ignored',
                    'reason' => 'Bot generated self message',
                ], 202);
            }

            $suspension = $service->suspendBotForContact($phone, $customerContactName, 5);
            $conversation = $humanConversationService->registerManualOutboundMessage(
                $phone,
                $message,
                $messageId,
                $request->all()
            );

            Log::info('Bot suspenso para atendimento humano.', [
                'phone' => $phone,
                'message_id' => $messageId,
                'classification' => $selfMessageAnalysis['classification'],
                'reason' => $selfMessageAnalysis['reason'],
                'until' => $suspension['until'],
                'conversation_id' => $conversation?->id,
            ]);

            return response()->json([
                'status' => 'suspended',
                'until' => $suspension['until'],
                'conversation_id' => $conversation?->id,
            ], 202);
        }

        if (($message === '' && !$interactionId && $location === null)) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'Missing phone, message or location',
            ], 202);
        }

        if ($this->isDuplicateWebhook($event, $phone, $messageId, $interactionId, $message)) {
            Log::info('Webhook duplicado ignorado.', [
                'event' => $event,
                'phone' => $phone,
                'message_id' => $messageId,
            ]);

            return response()->json([
                'status' => 'ignored',
                'reason' => 'Duplicate webhook',
            ], 202);
        }

        $humanConversation = $humanConversationService->registerInboundMessage(
            $phone,
            $message,
            $customerContactName,
            $messageId,
            $location,
            $request->all()
        );

        if ($humanConversation) {
            Log::info('Mensagem direcionada para atendimento humano ativo.', [
                'phone' => $phone,
                'message_id' => $messageId,
                'conversation_id' => $humanConversation->id,
            ]);

            return response()->json([
                'status' => 'human_attendance',
                'conversation_id' => $humanConversation->id,
            ], 202);
        }

        $suspension = $service->getSuspensionStatus($phone, $customerContactName);

        if ($suspension) {
            Log::info('Mensagem ignorada por suspensao ativa do bot.', [
                'phone' => $phone,
                'message_id' => $messageId,
                'interaction_id' => $interactionId,
                'has_location' => $location !== null,
                'until' => $suspension['until'],
            ]);

            return response()->json([
                'status' => 'ignored',
                'reason' => 'Bot suspended for human attendance',
                'until' => $suspension['until'],
            ], 202);
        }

        if (!config('services.whatsapp.legacy_bot_enabled', false)) {
            $this->safeDispatchEvent(new WhatsAppMessageReceived(
                $phone,
                $message,
                $customerContactName,
                $messageId,
                true
            ));

            $reply = $service->dailyMenuReply($phone, $customerContactName);

            try {
                if ($reply) {
                    $this->dispatchReply($service, $wppConnect, $replyTarget ?: $phone, $customerContactName, $reply);
                    $service->markDailyMenuSent($phone, $customerContactName);
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $sessionOffline = str_contains($message, 'Sessao do WhatsApp indisponivel para envio')
                    || str_contains($message, '"status":"Disconnected"')
                    || str_contains($message, 'A sess')
                    || str_contains($message, 'HTTP 404');
                $invalidRecipient = str_contains($message, 'Nao foi possivel validar o numero')
                    || str_contains($message, 'HTTP 400');

                Log::error('Erro ao enviar cardapio do dia pelo WhatsApp.', [
                    'message' => $message,
                    'phone' => $phone,
                    'event' => $event,
                    'session_offline' => $sessionOffline,
                    'invalid_recipient' => $invalidRecipient,
                ]);

                return response()->json([
                    'status' => $sessionOffline ? 'session_offline' : ($invalidRecipient ? 'invalid_recipient' : 'error'),
                    'message' => ($sessionOffline || $invalidRecipient) ? $message : 'Internal server error',
                ], ($sessionOffline || $invalidRecipient) ? 202 : 500);
            }

            Log::info('Fluxo minimo do WhatsApp processado para envio do cardapio do dia.', [
                'phone' => $phone,
                'message_id' => $messageId,
                'daily_menu_sent' => $reply !== null,
            ]);

            return response()->json([
                'status' => $reply ? 'daily_menu_sent' : 'ignored',
                'reason' => $reply
                    ? 'Daily menu sent to customer message'
                    : 'Daily menu already sent to this contact in the last 10 hours',
            ], 202);
        }

        try {
            $this->safeDispatchEvent(new WhatsAppMessageReceived(
                $phone,
                $message,
                $customerContactName,
                $messageId,
                true
            ));

            $reply = $service->processMessage($phone, $message, $customerContactName, $interactionId, $location);

            if ($reply) {
                $this->dispatchReply($service, $wppConnect, $replyTarget ?: $phone, $customerContactName, $reply);
            }

            return response()->json([
                'status' => 'processed',
            ]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $sessionOffline = str_contains($message, 'Sessao do WhatsApp indisponivel para envio')
                || str_contains($message, '"status":"Disconnected"')
                || str_contains($message, 'A sess')
                || str_contains($message, 'HTTP 404');
            $invalidRecipient = str_contains($message, 'Nao foi possivel validar o numero')
                || str_contains($message, 'HTTP 400');

            Log::error('Erro ao processar webhook do WhatsApp.', [
                'message' => $message,
                'phone' => $phone,
                'event' => $event,
                'session_offline' => $sessionOffline,
                'invalid_recipient' => $invalidRecipient,
            ]);

            return response()->json([
                'status' => $sessionOffline ? 'session_offline' : ($invalidRecipient ? 'invalid_recipient' : 'error'),
                'message' => ($sessionOffline || $invalidRecipient) ? $message : 'Internal server error',
            ], ($sessionOffline || $invalidRecipient) ? 202 : 500);
        }
    }

    protected function extractContactName(Request $request): ?string
    {
        $candidates = [
            $request->input('contactName'),
            $request->input('sender.pushname'),
            $request->input('sender.name'),
            $request->input('sender.shortName'),
            $request->input('sender.formattedName'),
            $request->input('notifyName'),
        ];

        foreach ($candidates as $candidate) {
            $name = $this->stringValue($candidate);

            if ($name === '') {
                continue;
            }

            if (in_array(mb_strtolower($name), ['voce', 'você', 'you'], true)) {
                continue;
            }

            return $name;
        }

        return null;
    }

    protected function resolveContactPhone(Request $request, string $event): string
    {
        $candidates = $event === 'onselfmessage'
            ? [
                $request->input('to'),
                $request->input('chatId'),
                $request->input('recipient.id'),
                $request->input('recipient._serialized'),
                $request->input('to.id'),
            ]
            : [
                $request->input('from'),
                $request->input('chatId'),
                $request->input('sender.id'),
                $request->input('sender._serialized'),
            ];

        foreach ($candidates as $candidate) {
            $value = $this->normalizeContactPhone($this->stringValue($candidate));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function resolveReplyTarget(Request $request, string $event): string
    {
        $candidates = $event === 'onselfmessage'
            ? [
                $request->input('to'),
                $request->input('chatId'),
                $request->input('recipient.id'),
                $request->input('recipient._serialized'),
                $request->input('to.id'),
            ]
            : [
                $request->input('from'),
                $request->input('chatId'),
                $request->input('sender.id'),
                $request->input('sender._serialized'),
            ];

        foreach ($candidates as $candidate) {
            $value = $this->normalizeReplyTarget($this->stringValue($candidate));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function normalizeContactPhone(string $candidate): string
    {
        $value = trim($candidate);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+@(?:g\.us|broadcast)$/', $value)) {
            return $value;
        }

        if ($value === 'status@broadcast') {
            return $value;
        }

        if (preg_match('/^(\d+)@(?:c\.us|s\.whatsapp\.net|lid)$/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(?:^|[^\d])(\d{12,15})(?:[^\d]|$)/', $value, $matches)) {
            return $matches[1];
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (strlen($digits) >= 12 && strlen($digits) <= 15) {
            return $digits;
        }

        return '';
    }

    protected function normalizeReplyTarget(string $candidate): string
    {
        $value = trim($candidate);

        if ($value === '') {
            return '';
        }

        if (
            preg_match('/^\d+@(?:c\.us|g\.us|lid|s\.whatsapp\.net|newsletter|broadcast)$/', $value) === 1
            || $value === 'status@broadcast'
        ) {
            return $value;
        }

        if (preg_match('/(\d+@(?:c\.us|g\.us|lid|s\.whatsapp\.net))/', $value, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    protected function extractInteractionId(Request $request): ?string
    {
        $candidates = [
            $request->input('selectedRowId'),
            $request->input('listResponse.singleSelectReply.selectedRowId'),
            $request->input('listResponse.selectedRowId'),
            $request->input('selectedButtonId'),
            $request->input('buttonId'),
            $request->input('dynamicReplyButtonId'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->stringValue($candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function extractLocation(Request $request): ?array
    {
        $candidates = [
            ['lat' => $request->input('lat'), 'lng' => $request->input('lng')],
            ['lat' => $request->input('latitude'), 'lng' => $request->input('longitude')],
            ['lat' => $request->input('location.latitude'), 'lng' => $request->input('location.longitude')],
            ['lat' => $request->input('location.lat'), 'lng' => $request->input('location.lng')],
            ['lat' => $request->input('body.latitude'), 'lng' => $request->input('body.longitude')],
            ['lat' => $request->input('body.lat'), 'lng' => $request->input('body.lng')],
            ['lat' => $request->input('message.location.latitude'), 'lng' => $request->input('message.location.longitude')],
            ['lat' => $request->input('message.location.lat'), 'lng' => $request->input('message.location.lng')],
        ];

        foreach ($candidates as $candidate) {
            $latitude = $this->floatValue($candidate['lat'] ?? null);
            $longitude = $this->floatValue($candidate['lng'] ?? null);

            if ($latitude === null || $longitude === null) {
                continue;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        return null;
    }

    protected function dispatchReply(
        WhatsAppService $service,
        WppConnectService $wppConnect,
        string $phone,
        ?string $contactName,
        array $reply
    ): void
    {
        $messageId = 'bot_' . time() . '_' . rand(1000, 9999);
        $messageText = '';

        if (($reply['type'] ?? 'text') === 'list') {
            $messageText = (string) ($reply['description'] ?? '') . ' [Lista de opções]';
            $wppConnect->sendListMessage(
                $phone,
                (string) ($reply['description'] ?? ''),
                (string) ($reply['buttonText'] ?? 'Ver opcoes'),
                $reply['sections'] ?? []
            );

            $service->markRecentBotOutbound($phone, $contactName);
        } else {
            $messageText = (string) ($reply['message'] ?? '');
            $wppConnect->sendMessage($phone, $messageText);
            $service->markRecentBotOutbound($phone, $contactName);
        }

        $this->safeDispatchEvent(new WhatsAppMessageSent($phone, $messageText, $messageId));
    }

    protected function safeDispatchEvent(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Evento do WhatsApp nao foi transmitido, mas o webhook vai continuar.', [
                'event' => $event::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function isDuplicateWebhook(
        string $event,
        string $phone,
        string $messageId,
        ?string $interactionId,
        string $message
    ): bool {
        if (!in_array($event, ['onmessage', 'onselfmessage'], true)) {
            return false;
        }

        $signature = implode('|', [
            $event,
            $phone,
            $messageId !== '' ? $messageId : md5(($interactionId ?? '') . '|' . $message),
        ]);

        $key = 'wa-webhook:' . sha1($signature);

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addSeconds(15));

        return false;
    }

    protected function classifySelfMessage(
        WhatsAppService $service,
        string $phone,
        ?string $contactName,
        string $messageId
    ): array {
        if ($messageId !== '') {
            $isBotPattern = preg_match('/(^|_)3EB[0-9A-F]+$/i', $messageId) === 1;
            $isManualPattern = preg_match('/(^|_)(A5|A4|A3)[0-9A-F]+$/i', $messageId) === 1;

            if ($isBotPattern) {
                return [
                    'is_bot_message' => true,
                    'classification' => 'bot_pattern',
                    'reason' => 'message_id_comeca_com_3EB',
                ];
            }

            if ($isManualPattern) {
                return [
                    'is_bot_message' => false,
                    'classification' => 'manual_pattern',
                    'reason' => 'message_id_comeca_com_A5_A4_ou_A3',
                ];
            }
        }

        if ($service->shouldIgnoreSelfMessage($phone, $contactName)) {
            return [
                'is_bot_message' => true,
                'classification' => 'recent_bot_window',
                'reason' => 'janela_de_envio_recente_do_bot_ativa',
            ];
        }

        return [
            'is_bot_message' => false,
            'classification' => 'manual_fallback',
            'reason' => 'nao_bateu_com_assinatura_do_bot',
        ];
    }

    protected function stringValue(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            foreach (['id', '_serialized', 'body', 'text', 'title', 'name', 'pushname', 'formattedName', 'value'] as $key) {
                $candidate = data_get($value, $key);
                $normalized = $this->stringValue($candidate);

                if ($normalized !== '') {
                    return $normalized;
                }
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? trim($encoded) : $default;
        }

        return $default;
    }

    protected function floatValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));

            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }

    protected function forwardToN8n(Request $request, string $phone, string $messageId): bool
    {
        $webhookUrl = trim((string) config('services.n8n.whatsapp_webhook_url', ''));

        if ($webhookUrl === '') {
            Log::warning('Webhook do n8n nao configurado para encaminhamento do WhatsApp.', [
                'phone' => $phone,
                'message_id' => $messageId,
            ]);

            return false;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->asJson()
                ->post($webhookUrl, $request->all());

            if ($response->successful() || in_array($response->status(), [200, 201, 202], true)) {
                return true;
            }

            Log::warning('n8n recusou o webhook encaminhado pelo Laravel.', [
                'phone' => $phone,
                'message_id' => $messageId,
                'status' => $response->status(),
                'body' => $response->body(),
                'webhook_url' => $webhookUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao encaminhar webhook do WhatsApp para o n8n.', [
                'phone' => $phone,
                'message_id' => $messageId,
                'webhook_url' => $webhookUrl,
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
