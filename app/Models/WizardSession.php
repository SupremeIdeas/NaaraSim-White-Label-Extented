<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's in-progress Wizard state (roadmap §7). Persisted so the floating
 * widget survives a minimise / top-up / page change. Never holds money or a
 * supplier name — only the public Model key, country, service and step.
 */
class WizardSession extends Model
{
    protected $fillable = [
        'user_id',
        'step',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
