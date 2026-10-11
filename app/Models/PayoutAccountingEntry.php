<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of the append-only payout accounting ledger (Addendum D-3.11). Never updated, never deleted. */
class PayoutAccountingEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_group', 'payout_request_id', 'float_movement_id', 'account_code', 'direction', 'amount_usd',
        'currency', 'amount_local', 'fx_rate', 'occurred_at', 'memo', 'posting_key',
    ];

    protected function casts(): array
    {
        return ['amount_usd' => 'decimal:4', 'amount_local' => 'decimal:4', 'fx_rate' => 'decimal:8', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('The payout accounting ledger is append-only.'));
        static::deleting(fn () => throw new \LogicException('The payout accounting ledger is append-only.'));
    }
}
