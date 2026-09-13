<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualNumber extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'phone_number',
        'sid',
        'capabilities',
        'monthly_cost',
        'monthly_retail',
        'status',
        'auto_renew',
        'next_billing_date',
        'renewal_notice_sent_at',
        'port_out_requested_at',
        'provisioned_at',
        'expires_at',
    ];

    /**
     * Money-safety rule 1.2: monthly_cost is private. Supplier masking: the raw
     * provider (Twilio/Telnyx) is never serialised — users see only Naara Line.
     */
    protected $hidden = [
        'monthly_cost',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'monthly_cost' => 'decimal:4',
            'monthly_retail' => 'decimal:4',
            'auto_renew' => 'boolean',
            'next_billing_date' => 'date',
            'renewal_notice_sent_at' => 'datetime',
            'port_out_requested_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Display-shape compatibility with the SmsOrder rows "My Lines" otherwise
     * renders in the same list (App\Support\ConnectivityHub merges both into
     * the naara_line group) — a permanent line has no service/OTP, but the
     * shared card partial expects these attributes to exist.
     */
    public function getServiceNameAttribute(): string
    {
        return 'Permanent line';
    }

    public function getTypeAttribute(): string
    {
        return 'permanent';
    }

    /**
     * Whether this line can send MMS (media attachments). Carriers only deliver
     * MMS reliably on US/Canada (+1) numbers, so we gate attachments to those —
     * an honest capability check rather than letting a media send silently drop.
     */
    public function supportsMms(): bool
    {
        $caps = (array) $this->capabilities;
        if (array_key_exists('mms', $caps)) {
            return (bool) $caps['mms'];
        }

        return $this->isUsCanada();
    }

    /**
     * Only US/Canada (+1) numbers can be ported out via our providers
     * (Prompt 11 audit): porting an African/other mobile number into a
     * VoIP/CPaaS carrier isn't available to individuals, so we never offer a
     * port-out we can't honestly facilitate.
     */
    public function isUsCanada(): bool
    {
        return str_starts_with(ltrim((string) $this->phone_number, ' '), '+1');
    }
}
