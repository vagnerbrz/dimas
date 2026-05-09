<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $phone;
    public $message;
    public $contactName;
    public $messageId;
    public $timestamp;
    public $isFromCustomer;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $phone,
        string $message,
        ?string $contactName,
        string $messageId,
        bool $isFromCustomer = true
    ) {
        $this->phone = $phone;
        $this->message = $message;
        $this->contactName = $contactName;
        $this->messageId = $messageId;
        $this->timestamp = now()->toISOString();
        $this->isFromCustomer = $isFromCustomer;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast para um canal público para todos os atendentes
        return [
            new Channel('whatsapp.messages'),
            new PrivateChannel('whatsapp.conversation.' . $this->phone),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'whatsapp.message.received';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'phone' => $this->phone,
            'message' => $this->message,
            'contact_name' => $this->contactName,
            'message_id' => $this->messageId,
            'timestamp' => $this->timestamp,
            'is_from_customer' => $this->isFromCustomer,
            'display_name' => $this->contactName ?: 'Cliente ' . substr($this->phone, -4),
        ];
    }
}