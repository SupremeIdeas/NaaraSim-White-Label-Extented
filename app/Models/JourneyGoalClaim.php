<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user having reached one goal for one period — see journey_goal_claims migration. */
class JourneyGoalClaim extends Model
{
    protected $fillable = [
        'journey_goal_id', 'user_id', 'period_key', 'achieved_value', 'credits_granted', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'achieved_value' => 'decimal:2',
            'credits_granted' => 'decimal:2',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(JourneyGoal::class, 'journey_goal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
