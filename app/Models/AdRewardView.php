<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A postback-verified rewarded-ad completion (loyalty module). Created only when
 * the ad network's server confirms a real view; unique on the network's txn id
 * so a replayed postback can never double-credit. payout_usd is admin-only.
 */
class AdRewardView extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'external_txn_id', 'credits', 'payout_usd', 'status', 'ip',
    ];

    /** Money-safety: the network payout is never exposed to users. */
    protected $hidden = ['payout_usd'];

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:2',
            'payout_usd' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
