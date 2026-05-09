<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'is_primary',
        'reference',
        'latitude',
        'longitude',
        'last_delivery_fee',
        'last_delivery_fee_updated_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function coordinates(): ?array
    {
        $latitude = $this->latitude !== null ? (float) $this->latitude : null;
        $longitude = $this->longitude !== null ? (float) $this->longitude : null;

        if ($latitude !== null && $longitude !== null) {
            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        $reference = (string) ($this->reference ?? '');

        if (preg_match('/\((-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)\)/', $reference, $matches) !== 1) {
            return null;
        }

        return [
            'latitude' => (float) $matches[1],
            'longitude' => (float) $matches[2],
        ];
    }

    public function googleMapsUrl(): ?string
    {
        $coordinates = $this->coordinates();

        if ($coordinates === null) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query=' .
            $coordinates['latitude'] . ',' . $coordinates['longitude'];
    }
}
