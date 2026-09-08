<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWallet extends Model
{
    protected $fillable = [
        'user_id',
        'ngn_balance',
        'usd_balance',
        'reserved_usd',
        'total_deposits',
        'total_spent',
        'naara_credits',
        'withdrawable_credits',
        'last_checkin_at',
    ];

    protected function casts(): array
    {
        return [
            'ngn_balance' => 'decimal:2',
            'usd_balance' => 'decimal:4',
            'reserved_usd' => 'decimal:4',
            'total_deposits' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'naara_credits' => 'decimal:2',
            'withdrawable_credits' => 'decimal:2',
            'last_checkin_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
