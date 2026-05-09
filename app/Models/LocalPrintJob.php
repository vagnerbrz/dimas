<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalPrintJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'attempts',
        'last_attempted_at',
        'error_message',
    ];

    public static function createIfNotExists(int $orderId): self
    {
        return self::firstOrCreate(
            ['order_id' => $orderId],
            ['status' => 'pending', 'attempts' => 0]
        );
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
