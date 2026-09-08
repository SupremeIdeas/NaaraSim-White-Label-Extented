<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable entry in a Developer API client's prepaid ledger (ROADMAP
 * §Layer 2). Written in the same atomic transaction as the balance change, with
 * balance_before / balance_after for audit.
 */
class ApiWalletTransaction extends Model
{
    protected $fillable = [
        'api_client_id',
        'type',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }
}
