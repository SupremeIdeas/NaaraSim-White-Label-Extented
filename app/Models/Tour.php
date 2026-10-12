<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'auto_start' => 'boolean', 'quick_enabled' => 'boolean', 'is_customized' => 'boolean'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TourStep::class)->orderBy('position');
    }
}
