<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The billing record for a self-service brand listing (BUILD-9 §2.3).
 */
class BrandSubscription extends Model
{
    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'brand_partner_id', 'plan_id', 'status', 'started_at', 'cancelled_at',
        'next_billing_at', 'last_charged_at', 'grace_reminders_sent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'last_charged_at' => 'datetime',
        'grace_reminders_sent' => 'integer',
    ];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BrandSubscriptionPlan::class, 'plan_id');
    }
}
