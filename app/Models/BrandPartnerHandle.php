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
        'last_post_image_path', 'last_post_caption', 'last_post_url', 'last_post_at',
    ];

    protected $casts = [
        'credit_reward' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'last_post_at' => 'datetime',
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

    /** Whether this handle has a curated "last post" teaser to show. */
    public function hasLastPost(): bool
    {
        return $this->last_post_image_path !== null || $this->last_post_caption !== null;
    }

    /**
     * The NaaraCredit reward actually paid for following this handle (owner
     * request, 2026-09-22): admin sets the rate per SUBSCRIPTION PLAN TIER, so
     * a brand on a richer plan can justify a richer reward — this is no
     * longer a flat number typed per handle. Falls back to this handle's own
     * `credit_reward` when the brand has no plan assigned, or its plan
     * leaves the rate unset, so existing/unplanned brands keep working
     * exactly as before.
     */
    public function effectiveCreditReward(): float
    {
        $planRate = $this->brandPartner?->plan?->credit_reward_per_follow;

        return (float) ($planRate ?? $this->credit_reward);
    }
}
