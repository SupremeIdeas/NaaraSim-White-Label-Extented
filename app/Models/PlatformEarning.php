<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prompt 21-EXT §5 — a row in the single global platform-earnings ledger
 * (white-label license sale proceeds). Deliberately shaped like
 * MerchantEarning but with no owning merchant_id — there is exactly one
 * running balance, read by PlatformEarningsService::balance(). Never merged
 * into or read by any general platform-profit reporting.
 */
class PlatformEarning extends Model
{
    public const ACCRUAL = 'accrual';

    public const HOLD = 'hold';

    public const RELEASE = 'release';

    protected $fillable = [
        'type', 'amount', 'balance_after', 'currency', 'reference', 'source_type', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }
}
