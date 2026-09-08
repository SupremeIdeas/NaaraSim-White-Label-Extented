<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLog extends Model
{
    protected $fillable = [
        'user_id',
        'naarasim_plan_id',
        'provider',
        'provider_cost',
        'charged_to_user',
        'profit',
        'profit_pct',
        'result',
    ];

    /** Money-safety rule 1.2: cost and profit are private. */
    protected $hidden = [
        'provider_cost',
        'profit',
    ];

    protected function casts(): array
    {
        return [
            'provider_cost' => 'decimal:4',
            'charged_to_user' => 'decimal:4',
            'profit' => 'decimal:4',
            'profit_pct' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
