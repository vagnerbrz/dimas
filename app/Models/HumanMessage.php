<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HumanMessage extends Model
{
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_SYSTEM = 'system';

    protected $fillable = [
        'human_conversation_id',
        'sender_user_id',
        'direction',
        'message_type',
        'body',
        'whatsapp_message_id',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(HumanConversation::class, 'human_conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
