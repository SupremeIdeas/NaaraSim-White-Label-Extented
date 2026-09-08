<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An admin-defined "My Journey" achievement — see JourneyGoalService for the engine. */
class JourneyGoal extends Model
{
    public const PERIOD_LIFETIME = 'lifetime';

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_QUARTERLY = 'quarterly';

    public const PERIOD_YEARLY = 'yearly';

    public const PERIOD_CAMPAIGN = 'campaign';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_MERCHANT = 'merchant';

    public const AUDIENCE_MERCHANT_V2 = 'merchant_v2';

    protected $fillable = [
        'title', 'description', 'metric', 'target', 'period_type', 'starts_at', 'ends_at',
        'reward_credits', 'audience', 'icon', 'image_path', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'reward_credits' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function claims(): HasMany
    {
        return $this->hasMany(JourneyGoalClaim::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRecurring(): bool
    {
        return in_array($this->period_type, [self::PERIOD_MONTHLY, self::PERIOD_QUARTERLY, self::PERIOD_YEARLY], true);
    }
}
