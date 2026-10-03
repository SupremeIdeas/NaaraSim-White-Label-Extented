<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An admin-governed skin or accent: whether it exists for users, which one is the platform default, and (later) who may use it. */
class AppearancePreset extends Model
{
    public const SKIN = 'skin';

    public const ACCENT = 'accent';

    protected $fillable = ['kind', 'key', 'label', 'enabled', 'is_default', 'sort', 'access', 'trial_enabled', 'trial_minutes', 'min_plan_tier', 'unlockable_by_goal', 'free_from', 'free_until'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'is_default' => 'boolean', 'trial_enabled' => 'boolean', 'unlockable_by_goal' => 'boolean', 'free_from' => 'datetime', 'free_until' => 'datetime'];
    }
}
