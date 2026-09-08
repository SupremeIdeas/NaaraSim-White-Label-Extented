<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A local-currency top-up with the USD wallet credit locked at initiation
 * (owner request). The wallet is credited usd_amount on webhook success —
 * decoupled from whatever currency the gateway reports back.
 */
class TopUpIntent extends Model
{
    protected $fillable = [
        'user_id', 'gateway', 'reference', 'charge_amount', 'charge_currency',
        'usd_amount', 'rate_usd_to_local', 'status', 'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'charge_amount' => 'decimal:4',
            'usd_amount' => 'decimal:4',
            'rate_usd_to_local' => 'decimal:8',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
