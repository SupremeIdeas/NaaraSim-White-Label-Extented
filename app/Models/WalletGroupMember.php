<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One invitee's membership in a WalletGroup (Prompt 11 §3). A row exists the
 * moment the owner invites someone; `accepted_at` null means the invite is
 * still pending — a pending member may never spend from the owner's wallet.
 * A null spend cap means uncapped for that currency.
 */
class WalletGroupMember extends Model
{
    protected $fillable = [
        'wallet_group_id',
        'user_id',
        'spend_cap_usd',
        'spend_cap_ngn',
        'invited_at',
        'accepted_at',
        'toast_shown_at',
    ];

    protected function casts(): array
    {
        return [
            'spend_cap_usd' => 'decimal:4',
            'spend_cap_ngn' => 'decimal:4',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'toast_shown_at' => 'datetime',
        ];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->accepted_at !== null;
    }

    /** The cap for a currency, or null (uncapped) — throws on an unsupported one. */
    public function capFor(string $currency): ?float
    {
        return match (strtoupper($currency)) {
            'USD' => $this->spend_cap_usd !== null ? (float) $this->spend_cap_usd : null,
            'NGN' => $this->spend_cap_ngn !== null ? (float) $this->spend_cap_ngn : null,
            default => throw new \InvalidArgumentException("Unsupported wallet-group currency [{$currency}]."),
        };
    }
}
