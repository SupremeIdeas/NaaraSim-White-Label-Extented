<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A brand partner's own social handle (BUILD-6 §C.1.4). Claims reuse the single
 * social_follow_claims table (brand_partner_handle_id).
 */
class BrandPartnerHandle extends Model
{
    protected $fillable = [
        'brand_partner_id', 'platform', 'handle_label', 'handle_url',
        'credit_reward', 'verification', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'credit_reward' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
