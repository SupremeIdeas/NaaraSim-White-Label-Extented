<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One withdrawal moving through the payout engine (ROADMAP §Layer 0.2).
 * Lifecycle: pending → (approved) → processing → paid | failed | reversed.
 */
class PayoutRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const PROCESSING = 'processing';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REVERSED = 'reversed';

    protected $fillable = [
        'user_id', 'payee_type', 'payout_account_id', 'amount', 'credit_amount', 'currency',
        'source_bucket', 'status', 'provider', 'provider_ref', 'failure_reason',
        'approved_by', 'reference', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'credit_amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class, 'payout_account_id');
    }

    /** A request that has been finalised one way or another. */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::PAID, self::FAILED, self::REVERSED], true);
    }
}
