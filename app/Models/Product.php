<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'is_active',
        'is_daily_special',
        'is_additional_offer',
        'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_daily_special' => 'boolean',
        'is_additional_offer' => 'boolean',
        'price' => 'decimal:2',
    ];
}
