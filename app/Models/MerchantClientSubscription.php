<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's eSIM subscription lifecycle (Merchant V2 client control). Tracks
 * the plan/type, current provider order, validity countdown, and the auto-renew
 * earmark. Money always moves through WalletService; this is the record the
 * merchant manages and the alerts key off.
 */
class MerchantClientSubscription extends Model
{
    public const TYPE_DATA = 'data';

    public const TYPE_CONNECT = 'connect';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISABLED = 'disabled';

    /**
     * Upper bound on how many renewal cycles a merchant may PRE-FUND in one lock.
     * Beyond this, "keep it for life" (rolling indefinite renewal) is the path —
     * you can't literally earmark unlimited months up front.
     */
    public const MAX_RESERVE_CYCLES = 36;

    protected $fillable = [
        'merchant_id', 'merchant_client_id', 'esim_order_id', 'plan_id', 'esim_type',
        'status', 'activated_at', 'expires_at', 'auto_renew', 'renewal_price',
        'reserve_reference', 'reserved_cycles', 'renew_indefinitely', 'due_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
            'renewal_price' => 'decimal:4',
            'reserved_cycles' => 'integer',
            'renew_indefinitely' => 'boolean',
            'due_alerted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(MerchantClient::class, 'merchant_client_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EsimOrder::class, 'esim_order_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Whole days until expiry (negative once past). Null when no expiry known. */
    public function daysLeft(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return (int) floor(now()->diffInDays($this->expires_at, false));
    }

    public function isDueSoon(int $withinDays = 3): bool
    {
        $left = $this->daysLeft();

        return $this->isActive() && $left !== null && $left <= $withinDays;
    }
}
