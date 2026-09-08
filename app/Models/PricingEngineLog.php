<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingEngineLog extends Model
{
    protected $fillable = [
        'plan_id',
        'provider',
        'cost_price',
        'markup_used',
        'computed_retail',
        'final_retail',
        'guard_active',
        'guard_delta',
    ];

    /** Money-safety rule 1.2: raw cost is private. */
    protected $hidden = [
        'cost_price',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:4',
            'markup_used' => 'decimal:3',
            'computed_retail' => 'decimal:4',
            'final_retail' => 'decimal:4',
            'guard_delta' => 'decimal:4',
        ];
    }
}
