<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Discount coupon (Module 31). A coupon only ever reduces the RETAIL price and
 * every application is clamped by CouponEngine so the charged price never
 * falls to or below provider cost + minimum profit (money-safety rule 4).
 */
class Coupon extends Model
{
    protected $fillable = [
        'code',
        'percent_off',
        'applies_to',
        'max_redemptions',
        'per_user_limit',
        'times_redeemed',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percent_off' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Live right now: active, inside its window, redemptions remaining. */
    public function isRedeemable(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_redemptions === null || $this->times_redeemed < $this->max_redemptions);
    }

    /** Does this coupon cover the given product? (esim | number) */
    public function covers(string $product): bool
    {
        return $this->applies_to === 'all' || $this->applies_to === $product;
    }
}
