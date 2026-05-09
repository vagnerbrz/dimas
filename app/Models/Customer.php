<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
    ];

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function whatsapp_session()
    {
        return $this->hasOne(WhatsAppSession::class);
    }

    public function humanConversations()
    {
        return $this->hasMany(HumanConversation::class);
    }
}
