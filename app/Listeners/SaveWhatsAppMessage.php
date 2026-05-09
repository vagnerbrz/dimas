<?php

namespace App\Listeners;

use App\Events\WhatsAppMessageReceived;
use App\Events\WhatsAppMessageSent;
use App\Models\WhatsAppChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SaveWhatsAppMessage
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(WhatsAppMessageReceived|WhatsAppMessageSent $event): void
    {
        WhatsAppChatMessage::create([
            'phone' => $event->phone,
            'message' => $event->message,
            'contact_name' => $event instanceof WhatsAppMessageReceived ? $event->contactName : 'Atendente',
            'message_id' => $event->messageId,
            'is_from_customer' => $event instanceof WhatsAppMessageReceived ? $event->isFromCustomer : false,
            'metadata' => [
                'event_type' => get_class($event),
                'timestamp' => $event->timestamp,
            ],
        ]);
    }
}