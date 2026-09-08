<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row in a referrer's real (withdrawable) earnings ledger (NAARA-BUILD-22 §1).
 * Same shape as MerchantEarning — distinct from the NaaraCredit referral bonus.
 */
class ReferralEarning extends Model
{
    public const ACCRUAL = 'accrual';

    public const HOLD = 'hold';

    public const RELEASE = 'release';

    protected $fillable = [
        'user_id', 'type', 'amount', 'balance_after', 'currency',
        'reference', 'source_type', 'source_user_id', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
