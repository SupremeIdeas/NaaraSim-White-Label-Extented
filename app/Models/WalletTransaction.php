<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'spent_by_user_id',
        'type',
        'amount',
        'currency',
        'paid_amount',
        'paid_currency',
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
            'paid_amount' => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Prompt 11 §3: who actually spent, when this was a group-plan purchase
     *  debited from the group owner's wallet (`user_id`) — null otherwise. */
    public function spentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spent_by_user_id');
    }
}
