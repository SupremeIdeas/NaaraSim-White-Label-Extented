<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reseller storefront (ROADMAP §Layer 3). Belongs to its owner user; its
 * customers are the users linked via merchant_id. The reseller margin is admin
 * controlled — a merchant can never price their own products.
 */
class Merchant extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const REJECTED = 'rejected';

    public const TIER_STANDARD = 'standard';

    public const TIER_V2 = 'v2';

    protected $fillable = [
        'owner_user_id', 'business_name', 'slug', 'logo_url', 'brand_color',
        'status', 'tier', 'upgraded_at', 'reseller_margin_pct', 'reason', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reseller_margin_pct' => 'decimal:3',
            'reviewed_at' => 'datetime',
            'upgraded_at' => 'datetime',
        ];
    }

    /** Merchant V2 — the client-management tier. */
    public function isV2(): bool
    {
        return $this->tier === self::TIER_V2;
    }

    public function clients(): HasMany
    {
        return $this->hasMany(MerchantClient::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MerchantInvoice::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** The customers who signed up under this merchant. */
    public function customers(): HasMany
    {
        return $this->hasMany(User::class, 'merchant_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
