<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_COUNTER = 'counter';
    public const TYPE_TABLE = 'table';

    protected $fillable = [
        'customer_id',
        'address_id',
        'type',
        'status',
        'total_amount',
        'delivery_fee',
        'delivery_distance_km',
        'payment_method',
        'change_for',
        'observations',
    ];

    protected $dates = ['deleted_at'];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_DELIVERY => 'Delivery',
            self::TYPE_COUNTER => 'Balcao',
            self::TYPE_TABLE => 'Mesa',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_DELIVERY => 'Delivery (Entrega)',
            self::TYPE_COUNTER => 'Balcao (Retirada)',
            self::TYPE_TABLE => 'Mesa (Consumo no local)',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function isDelivery(): bool
    {
        return $this->type === self::TYPE_DELIVERY;
    }

    public function isCounter(): bool
    {
        return $this->type === self::TYPE_COUNTER;
    }

    public function isTable(): bool
    {
        return $this->type === self::TYPE_TABLE;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryLocationUrl(): ?string
    {
        if (!$this->isDelivery() || !$this->address) {
            return null;
        }

        return $this->address->googleMapsUrl();
    }
}
