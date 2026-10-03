<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The platform's pre-funded balance at one provider, in one currency. A row = float is tracked. */
class PayoutFloatBalance extends Model
{
    protected $fillable = ['provider', 'currency', 'balance', 'low_threshold', 'auto_sync', 'last_synced_at', 'notes'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:4', 'low_threshold' => 'decimal:4', 'auto_sync' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
