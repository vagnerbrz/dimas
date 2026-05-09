<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'customer_id',
        'state',
        'temporary_data',
    ];

    protected $casts = [
        'temporary_data' => 'array',
    ];
}
