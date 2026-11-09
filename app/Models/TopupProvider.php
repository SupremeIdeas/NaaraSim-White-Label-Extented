<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A top-up supplier (Zendit, VTpass, Reloadly, Ding): admin switch, float and health. */
class TopupProvider extends Model
{
    protected $fillable = ['code', 'name', 'enabled', 'currency', 'min_float', 'last_balance', 'last_balance_at', 'last_error', 'low_float_alerted', 'last_synced_at', 'meta'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean', 'low_float_alerted' => 'boolean',
            'min_float' => 'decimal:2', 'last_balance' => 'decimal:2',
            'last_balance_at' => 'datetime', 'last_synced_at' => 'datetime', 'meta' => 'array',
        ];
    }
}
