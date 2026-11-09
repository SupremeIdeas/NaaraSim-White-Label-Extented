<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Data-driven routing: country + product type -> ordered providers. */
class TopupRoute extends Model
{
    protected $fillable = ['country_iso', 'product_type', 'provider_code', 'priority', 'enabled', 'min_amount_usd', 'max_amount_usd'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'priority' => 'integer', 'min_amount_usd' => 'decimal:2', 'max_amount_usd' => 'decimal:2'];
    }
}
