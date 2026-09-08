<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderWalletLog extends Model
{
    protected $fillable = [
        'provider',
        'event',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }
}
