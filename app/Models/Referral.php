<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_MERCHANT = 'merchant';

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'type',
        'reward_pct',
        'rewarded',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_pct' => 'decimal:3',
            'rewarded' => 'boolean',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}
