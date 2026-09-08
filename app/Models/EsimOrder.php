<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsimOrder extends Model
{
    protected $fillable = [
        'user_id',
        'merchant_client_id',
        'plan_id',
        'provider',
        'provider_order_ref',
        'iccid',
        'bundle_name',
        'qr_code_url',
        'lpa_string',
        'status',
        'activated_at',
        'expires_at',
        'data_remaining_mb',
        'price_charged',
        'wholesale_cost',
        'currency',
    ];

    /**
     * Money-safety rule 1.2: wholesale_cost is private. The raw supplier
     * (`provider`) is masked too — users only ever see the public Model
     * (ProviderModels · Naara Data), never which eSIM provider fulfilled it.
     */
    protected $hidden = [
        'wholesale_cost',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'data_remaining_mb' => 'integer',
            'price_charged' => 'decimal:4',
            'wholesale_cost' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class, 'plan_id');
    }

    public function merchantClient(): BelongsTo
    {
        return $this->belongsTo(MerchantClient::class, 'merchant_client_id');
    }

    /** Ready to deliver/install once the activation code (LPA) or QR is present. */
    public function isDeliverable(): bool
    {
        return filled($this->lpa_string) || filled($this->qr_code_url);
    }
}
