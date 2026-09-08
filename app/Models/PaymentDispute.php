<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDispute extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_WON = 'won';   // we kept the money

    public const STATUS_LOST = 'lost'; // charged back

    protected $fillable = [
        'user_id', 'gateway', 'provider_dispute_id', 'reference',
        'amount', 'currency', 'status', 'frozen_amount', 'resolved_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'frozen_amount' => 'decimal:4',
            'resolved_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
