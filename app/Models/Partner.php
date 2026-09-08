<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A partner in the profit-sharing program (new). Belongs to its owner user and
 * earns an admin-set share of PLATFORM-WIDE profit each period — unlike a
 * Merchant, whose earnings come from their own referred sales. The profit-share
 * percentage is admin-confidential and never surfaced to the partner.
 */
class Partner extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const CADENCE_WEEKLY = 'weekly';

    public const CADENCE_MONTHLY = 'monthly';

    public const MODE_MANUAL = 'manual';

    public const MODE_AUTO = 'auto';

    protected $fillable = [
        'owner_user_id', 'status', 'profit_share_pct', 'payout_cadence',
        'payout_mode', 'last_period_end', 'reason', 'reviewed_by', 'reviewed_at',
    ];

    /**
     * Money-safety: the profit-share percentage is an admin-confidential figure —
     * it is never serialised to a partner-facing payload.
     */
    protected $hidden = ['profit_share_pct'];

    protected function casts(): array
    {
        return [
            'profit_share_pct' => 'decimal:3',
            'last_period_end' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PartnerEarning::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
