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

    /** Money the provider/bank sent back AFTER we reported it paid (Addendum D-3.4). Final; reached only from `paid`. */
    public const RETURNED = 'returned';

    /** Funds are held but the provider float can't cover it yet; resumes automatically (Phase 4). */
    public const AWAITING_FUNDS = 'awaiting_funds';

    public const APPROVAL_ADMIN = 'admin';

    public const APPROVAL_SYSTEM = 'system';

    // Guardian review states (Addendum C §3)
    public const REVIEW_AUTO_PENDING = 'auto_pending';

    public const REVIEW_DEFERRED = 'deferred';

    public const REVIEW_MANUAL = 'manual_review';

    public const REVIEW_APPROVED_AUTO = 'approved_auto';

    public const REVIEW_APPROVED_MANUAL = 'approved_manual';

    public const REVIEW_REJECTED_AUTO = 'rejected_auto';

    /** Statuses that count as a payout the user has already "committed" (free-payout / open-cap math). */
    public const COMMITTED = [self::PAID, self::PROCESSING, self::APPROVED, self::AWAITING_FUNDS, self::PENDING];

    /** Statuses that are still open (not yet final). */
    public const OPEN = [self::PENDING, self::APPROVED, self::PROCESSING, self::AWAITING_FUNDS];

    protected $fillable = [
        'user_id', 'payee_type', 'payout_account_id', 'amount', 'credit_amount', 'currency',
        'source_bucket', 'status', 'provider', 'provider_ref', 'failure_reason',
        'approved_by', 'reference', 'settled_at',
        'usd_amount', 'fx_rate', 'platform_fee_usd', 'corridor_id', 'quote_expires_at', 'fx_locked_at',
        'approval_source', 'review_state', 'hold_reason', 'risk_score', 'next_check_at', 'evaluating_at',
        'destination_snapshot', 'destination_digest', 'provider_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'credit_amount' => 'decimal:2',
            'settled_at' => 'datetime',
            'usd_amount' => 'decimal:4',
            'fx_rate' => 'decimal:8',
            'platform_fee_usd' => 'decimal:4',
            'quote_expires_at' => 'datetime',
            'fx_locked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'evaluating_at' => 'datetime',
        ];
    }

    /** The id the provider sees: the derived provider reference, or the legacy internal one for older rows. */
    public function wireReference(): string
    {
        return filled($this->provider_reference) ? $this->provider_reference : $this->reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class, 'payout_account_id');
    }

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(PayoutCorridor::class);
    }

    public function providerCalls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PayoutProviderCall::class);
    }

    public function decisions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PayoutDecision::class);
    }

    /** A request that has been finalised one way or another. */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::PAID, self::FAILED, self::REVERSED, self::RETURNED], true);
    }
}
