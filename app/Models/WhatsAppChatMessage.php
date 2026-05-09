<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppChatMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_chat_messages';

    protected $fillable = [
        'phone',
        'message',
        'contact_name',
        'message_id',
        'is_from_customer',
        'metadata',
        'read_at',
    ];

    protected $casts = [
        'is_from_customer' => 'boolean',
        'metadata' => 'array',
        'read_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'phone', 'phone');
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->where('is_from_customer', true);
    }

    public function scopeFromCustomer($query)
    {
        return $query->where('is_from_customer', true);
    }

    public function scopeToCustomer($query)
    {
        return $query->where('is_from_customer', false);
    }

    public function scopeForPhone($query, $phone)
    {
        return $query->where('phone', $phone);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}