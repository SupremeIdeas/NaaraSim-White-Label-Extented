<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Normalised top-up product snapshot. Cost fields are PRIVATE ($hidden): they are
 * read only by QuoteService and never reach a user-facing payload.
 */
class TopupProduct extends Model
{
    protected $fillable = [
        'provider_code', 'provider_sku', 'country_iso', 'operator_name', 'operator_code', 'type', 'name', 'validity_text',
        'data_mb', 'data_unlimited', 'is_fixed', 'min_amount', 'max_amount', 'fixed_amount', 'currency', 'cost_native', 'cost_ratio', 'cost_currency',
        'active', 'synced_at', 'meta',
    ];

    protected $hidden = ['provider_code', 'provider_sku', 'cost_native', 'cost_ratio', 'cost_currency', 'meta'];

    protected function casts(): array
    {
        return [
            'is_fixed' => 'boolean', 'active' => 'boolean', 'data_unlimited' => 'boolean',
            'min_amount' => 'decimal:2', 'max_amount' => 'decimal:2', 'fixed_amount' => 'decimal:2',
            'cost_native' => 'decimal:4', 'cost_ratio' => 'decimal:8', 'synced_at' => 'datetime', 'meta' => 'array',
        ];
    }
}
