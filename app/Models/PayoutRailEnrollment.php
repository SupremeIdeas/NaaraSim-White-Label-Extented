<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user who chose a global payout rail (Funding Radar tracking record). */
class PayoutRailEnrollment extends Model
{
    public const SELECTED = 'selected';

    public const ONBOARDING = 'onboarding';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const DECLINED = 'declined';

    protected $fillable = [
        'user_id', 'provider', 'country', 'currency', 'status', 'stripe_connect_eligible', 'ineligible_reason',
        'payout_account_id', 'selected_at', 'activated_at', 'last_status_at',
    ];

    protected function casts(): array
    {
        return ['stripe_connect_eligible' => 'boolean', 'selected_at' => 'datetime', 'activated_at' => 'datetime', 'last_status_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class, 'payout_account_id');
    }
}
