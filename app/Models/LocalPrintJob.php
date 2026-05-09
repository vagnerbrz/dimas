<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalPrintJob extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_FAILED = 'failed';

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
            ['status' => self::STATUS_PENDING, 'attempts' => 0]
        );
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePollable($query)
    {
        $maxAttempts = max(1, (int) config('printing.local_max_attempts', 10));
        $retryAfterSeconds = max(1, (int) config('printing.local_retry_after_seconds', 30));

        return $query->where(function ($query) use ($maxAttempts, $retryAfterSeconds) {
            $query->where('status', self::STATUS_PENDING)
                ->orWhere(function ($query) use ($maxAttempts, $retryAfterSeconds) {
                    $query->where('status', self::STATUS_FAILED)
                        ->where('attempts', '<', $maxAttempts)
                        ->where(function ($query) use ($retryAfterSeconds) {
                            $query->whereNull('last_attempted_at')
                                ->orWhere('last_attempted_at', '<=', now()->subSeconds($retryAfterSeconds));
                        });
                });
        });
    }
}
