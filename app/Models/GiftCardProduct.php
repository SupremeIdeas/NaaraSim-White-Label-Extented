<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A normalized Naara Gift catalogue product (from Reloadly or Zendit). The
 * provider identity + cost_meta are PRIVATE (money-safety scrub rule 1.2) — the
 * storefront shows the brand, never the supplier. `is_primary` picks the
 * provider the storefront buys from per brand+country.
 */
class GiftCardProduct extends Model
{
    protected $fillable = [
        'provider', 'provider_product_id', 'brand_key', 'brand_name', 'country', 'currency',
        'denomination_type', 'fixed_denominations', 'min_amount', 'max_amount', 'logo_url',
        'brand_color', 'category', 'required_fields', 'redeem_instruction', 'cost_meta',
        'provider_enabled', 'admin_enabled', 'is_primary', 'featured', 'priceable',
    ];

    /** Never leak provider/cost to a user-facing serialization. */
    protected $hidden = ['provider', 'provider_product_id', 'cost_meta'];

    protected function casts(): array
    {
        return [
            'fixed_denominations' => 'array',
            'required_fields' => 'array',
            'cost_meta' => 'array',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'provider_enabled' => 'boolean',
            'admin_enabled' => 'boolean',
            'is_primary' => 'boolean',
            'featured' => 'boolean',
            'priceable' => 'boolean',
        ];
    }

    /**
     * Products the storefront may sell: primary provider, enabled both sides, and
     * PRICEABLE — a card we can't convert to a real USD cost is withheld so a
     * currency mismatch can never sell it below cost (money-safety rule 1.4).
     */
    public function scopeStorefront(Builder $q): Builder
    {
        return $q->where('is_primary', true)->where('admin_enabled', true)
            ->where('provider_enabled', true)->where('priceable', true);
    }

    public function isRange(): bool
    {
        return $this->denomination_type === 'RANGE';
    }
}
