<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A brand subscription pricing tier (BUILD-9 §2.2). The guaranteed followers per
 * handle/month is the transparency mechanism the priority engine (§6) enforces.
 */
class BrandSubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'price_usd_per_month', 'handles_included',
        'guaranteed_followers_per_handle_per_month', 'video_previews_allowed',
        'credit_reward_per_follow', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_usd_per_month' => 'decimal:2',
        'handles_included' => 'integer',
        'guaranteed_followers_per_handle_per_month' => 'integer',
        'video_previews_allowed' => 'integer',
        'credit_reward_per_follow' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Effective cost per guaranteed follower per handle (for the comparison table). */
    public function costPerGuaranteedFollower(): ?float
    {
        $g = (int) $this->guaranteed_followers_per_handle_per_month * max(1, (int) $this->handles_included);

        return $g > 0 ? round((float) $this->price_usd_per_month / $g, 3) : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price_usd_per_month');
    }
}
