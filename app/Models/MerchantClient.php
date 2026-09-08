<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client a Merchant V2 manages on behalf of. The client never logs in — the
 * merchant is the sole operator. Holds many eSIM / number orders over time.
 */
class MerchantClient extends Model
{
    protected $fillable = [
        'merchant_id', 'name', 'contact', 'whatsapp', 'email', 'device', 'device_os', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MerchantClientSubscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MerchantInvoice::class);
    }

    /** The current (most recent, non-disabled) eSIM subscription, if any. */
    public function activeSubscription(): ?MerchantClientSubscription
    {
        return $this->subscriptions()
            ->where('status', '!=', MerchantClientSubscription::STATUS_DISABLED)
            ->latest('id')->first();
    }

    /** A wa.me deep link with a prebuilt message (renewal reminder / invoice). */
    public function whatsappLink(string $message): ?string
    {
        $number = preg_replace('/[^0-9]/', '', (string) $this->whatsapp);
        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function esimOrders(): HasMany
    {
        return $this->hasMany(EsimOrder::class);
    }

    public function smsOrders(): HasMany
    {
        return $this->hasMany(SmsOrder::class);
    }
}
