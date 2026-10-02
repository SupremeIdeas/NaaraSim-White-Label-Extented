<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only float ledger row — never updated or deleted. */
class PayoutFloatMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider', 'currency', 'type', 'amount', 'balance_after', 'payout_request_id', 'reference', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'balance_after' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('payout_float_movements is append-only.'));
        static::deleting(fn () => throw new \LogicException('payout_float_movements is append-only.'));
    }
}
