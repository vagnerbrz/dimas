<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $phone;
    public $message;
    public $messageId;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $phone,
        string $message,
        string $messageId
    ) {
        $this->phone = $phone;
        $this->message = $message;
        $this->messageId = $messageId;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
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
        return 'whatsapp.message.sent';
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
            'message_id' => $this->messageId,
            'timestamp' => $this->timestamp,
            'is_from_customer' => false,
            'display_name' => 'Atendente',
        ];
    }
}