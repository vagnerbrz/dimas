<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HumanConversation extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'customer_id',
        'assigned_user_id',
        'phone',
        'contact_name',
        'status',
        'escalated_at',
        'last_message_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'escalated_at' => 'datetime',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HumanMessage::class)->orderBy('created_at');
    }
}
