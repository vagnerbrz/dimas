<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\HumanConversation;
use App\Models\HumanMessage;
use App\Models\User;
use Illuminate\Support\Arr;

class HumanConversationService
{
    public function __construct(
        protected WppConnectService $wppConnectService,
        protected WhatsAppService $whatsAppService,
    ) {
    }

    public function openEscalatedConversation(
        string $phone,
        ?string $contactName = null,
        array $meta = [],
        int $minutes = 15
    ): HumanConversation {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $conversation = $this->findActiveConversationByPhone($phone);

        if (!$conversation) {
            $conversation = HumanConversation::create([
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'contact_name' => $customer->name,
                'status' => HumanConversation::STATUS_OPEN,
                'escalated_at' => now(),
                'last_message_at' => now(),
                'meta' => $this->normalizeMeta($meta),
            ]);

            $conversation->messages()->create([
                'direction' => HumanMessage::DIRECTION_SYSTEM,
                'message_type' => 'system',
                'body' => 'Atendimento humano iniciado.',
                'payload' => $this->normalizeMeta($meta),
                'sent_at' => now(),
            ]);
        } else {
            $conversation->update([
                'customer_id' => $customer->id,
                'contact_name' => $customer->name,
                'status' => $conversation->status === HumanConversation::STATUS_CLOSED
                    ? HumanConversation::STATUS_OPEN
                    : $conversation->status,
                'meta' => array_merge($conversation->meta ?? [], $this->normalizeMeta($meta)),
                'last_message_at' => now(),
                'closed_at' => null,
            ]);
        }

        $this->whatsAppService->suspendBotForContact($customer->phone, $customer->name, $minutes);

        return $conversation->fresh(['customer', 'assignedUser']);
    }

    public function findActiveConversationByPhone(string $phone): ?HumanConversation
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        return HumanConversation::query()
            ->where('phone', $normalizedPhone)
            ->whereIn('status', [HumanConversation::STATUS_OPEN, HumanConversation::STATUS_IN_PROGRESS])
            ->latest('last_message_at')
            ->first();
    }

    public function registerInboundMessage(
        string $phone,
        ?string $message,
        ?string $contactName = null,
        ?string $messageId = null,
        ?array $location = null,
        array $payload = []
    ): ?HumanConversation {
        $conversation = $this->findActiveConversationByPhone($phone);

        if (!$conversation) {
            return null;
        }

        $body = $this->buildInboundBody($message, $location);

        if ($body === '') {
            return $conversation;
        }

        $conversation->messages()->create([
            'direction' => HumanMessage::DIRECTION_INBOUND,
            'message_type' => $location ? 'location' : 'text',
            'body' => $body,
            'whatsapp_message_id' => $messageId ?: null,
            'payload' => $payload,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'contact_name' => $this->normalizeContactName($contactName) ?: $conversation->contact_name,
            'last_message_at' => now(),
        ]);

        return $conversation->fresh(['customer', 'assignedUser']);
    }

    public function registerManualOutboundMessage(
        string $phone,
        ?string $message,
        ?string $messageId = null,
        array $payload = []
    ): ?HumanConversation {
        $conversation = $this->findActiveConversationByPhone($phone);

        if (!$conversation) {
            return null;
        }

        $body = trim((string) $message);

        if ($body === '') {
            return $conversation;
        }

        $conversation->messages()->create([
            'direction' => HumanMessage::DIRECTION_OUTBOUND,
            'message_type' => 'text',
            'body' => $body,
            'whatsapp_message_id' => $messageId ?: null,
            'payload' => $payload,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        return $conversation;
    }

    public function assignConversation(HumanConversation $conversation, User $user): HumanConversation
    {
        $conversation->update([
            'assigned_user_id' => $user->id,
            'status' => HumanConversation::STATUS_IN_PROGRESS,
        ]);

        $conversation->messages()->create([
            'direction' => HumanMessage::DIRECTION_SYSTEM,
            'message_type' => 'system',
            'body' => $user->name . ' assumiu o atendimento.',
            'sender_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        return $conversation->fresh(['customer', 'assignedUser']);
    }

    public function sendMessage(HumanConversation $conversation, User $user, string $message): HumanMessage
    {
        $text = trim($message);

        if ($text === '') {
            throw new \RuntimeException('Mensagem vazia para envio.');
        }

        $this->whatsAppService->suspendBotForContact($conversation->phone, $conversation->contact_name, 30);
        $this->whatsAppService->markRecentBotOutbound($conversation->phone, $conversation->contact_name, 30);
        $this->wppConnectService->sendMessage($conversation->phone, $text);

        $conversation->update([
            'assigned_user_id' => $conversation->assigned_user_id ?: $user->id,
            'status' => HumanConversation::STATUS_IN_PROGRESS,
            'last_message_at' => now(),
        ]);

        return $conversation->messages()->create([
            'direction' => HumanMessage::DIRECTION_OUTBOUND,
            'message_type' => 'text',
            'body' => $text,
            'sender_user_id' => $user->id,
            'sent_at' => now(),
        ]);
    }

    public function closeConversation(HumanConversation $conversation, User $user, bool $releaseBot = true): HumanConversation
    {
        $conversation->update([
            'status' => HumanConversation::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $conversation->messages()->create([
            'direction' => HumanMessage::DIRECTION_SYSTEM,
            'message_type' => 'system',
            'body' => $user->name . ' encerrou o atendimento humano.',
            'sender_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        if ($releaseBot) {
            $this->whatsAppService->clearSuspension($conversation->phone, $conversation->contact_name);
        }

        return $conversation->fresh(['customer', 'assignedUser']);
    }

    public function reopenConversation(HumanConversation $conversation, User $user, int $minutes = 30): HumanConversation
    {
        $conversation->update([
            'status' => HumanConversation::STATUS_IN_PROGRESS,
            'closed_at' => null,
            'last_message_at' => now(),
            'assigned_user_id' => $conversation->assigned_user_id ?: $user->id,
        ]);

        $conversation->messages()->create([
            'direction' => HumanMessage::DIRECTION_SYSTEM,
            'message_type' => 'system',
            'body' => $user->name . ' reabriu o atendimento humano.',
            'sender_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $this->whatsAppService->suspendBotForContact($conversation->phone, $conversation->contact_name, $minutes);

        return $conversation->fresh(['customer', 'assignedUser']);
    }

    protected function buildInboundBody(?string $message, ?array $location = null): string
    {
        $text = trim((string) $message);

        if ($text !== '') {
            return $text;
        }

        if ($location) {
            $latitude = Arr::get($location, 'latitude');
            $longitude = Arr::get($location, 'longitude');

            return 'Localizacao compartilhada: ' . $latitude . ', ' . $longitude;
        }

        return '';
    }

    protected function findOrCreateCustomer(string $phone, ?string $contactName = null): Customer
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            throw new \RuntimeException('Telefone do cliente nao informado.');
        }

        $displayName = $this->normalizeContactName($contactName) ?: 'Cliente WhatsApp';

        $customer = Customer::firstOrCreate(
            ['phone' => $normalizedPhone],
            ['name' => $displayName, 'phone' => $normalizedPhone]
        );

        if ($displayName !== '' && $customer->name !== $displayName) {
            $customer->update(['name' => $displayName]);
        }

        return $customer;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?: '';
    }

    protected function normalizeContactName(?string $contactName): ?string
    {
        $contactName = trim((string) $contactName);

        return $contactName !== '' ? $contactName : null;
    }

    protected function normalizeMeta(array $meta): array
    {
        return array_filter($meta, static fn ($value) => $value !== null && $value !== '');
    }
}
