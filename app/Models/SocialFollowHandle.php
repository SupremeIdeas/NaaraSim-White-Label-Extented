<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The platform's own official social handle (BUILD-6 §C). credit_reward is the
 * surprise amount — never exposed to the user before they follow.
 */
class SocialFollowHandle extends Model
{
    protected $fillable = [
        'platform', 'handle_label', 'handle_url', 'credit_reward',
        'verification', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'credit_reward' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
