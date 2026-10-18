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

    /**
     * One storefront card per brand + country: a provider that lists one offer per denomination (Zendit) must not show the same brand
     * four times. The card is the lowest-id row; the other denominations are its siblings (see siblings()).
     */
    public function scopeStorefrontCards(Builder $q): Builder
    {
        return $q->storefront()->whereIn('id', function ($sub) {
            $sub->from('gift_card_products')->selectRaw('MIN(id)')
                ->where('is_primary', true)->where('admin_enabled', true)->where('provider_enabled', true)->where('priceable', true)
                ->groupBy('brand_key', 'country', 'provider', 'denomination_type');
        });
    }

    /** The other FIXED-denomination offers of the same brand, country and provider (this row included). */
    public function siblings(): Builder
    {
        return static::query()->storefront()->where('brand_key', $this->brand_key)->where('country', $this->country)
            ->where('provider', $this->provider)->where('denomination_type', $this->denomination_type);
    }

    public function isRange(): bool
    {
        return $this->denomination_type === 'RANGE';
    }
}
