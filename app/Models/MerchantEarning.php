<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A row in a merchant's reseller-earnings ledger (ROADMAP §Layer 3.4). */
class MerchantEarning extends Model
{
    public const ACCRUAL = 'accrual';

    public const HOLD = 'hold';

    public const RELEASE = 'release';

    /** A reversal of an earlier accrual (refund / lost chargeback). May take the balance below zero: that is a debt. */
    public const CLAWBACK = 'clawback';

    /** Money sent to another member (member-to-member transfer). Never an accrual, so platform margin maths is untouched. */
    public const TRANSFER_OUT = 'transfer_out';

    /** Money received from another member. Only the referral ledger receives (it exists for every user). */
    public const TRANSFER_IN = 'transfer_in';

    protected $fillable = [
        'merchant_id', 'type', 'amount', 'balance_after', 'currency',
        'reference', 'source_type', 'source_user_id', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
