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
