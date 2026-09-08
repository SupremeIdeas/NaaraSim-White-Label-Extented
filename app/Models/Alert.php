<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A login notice pop-up (admin-composed, targeted, frequency-capped). */
class Alert extends Model
{
    public const AUDIENCES = ['all', 'new', 'old'];
    public const TRIGGERS = ['any', 'first_registration'];

    protected $fillable = [
        'title', 'body', 'cta_label', 'cta_url', 'coupon_code', 'audience',
        'new_days', 'trigger', 'max_views', 'starts_at', 'ends_at', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'new_days' => 'integer',
            'max_views' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function views()
    {
        return $this->hasMany(AlertView::class);
    }

    public function isLive(): bool
    {
        $now = now();

        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte($now))
            && (! $this->ends_at || $this->ends_at->gte($now));
    }
}
