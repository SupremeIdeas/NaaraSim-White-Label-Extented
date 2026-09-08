<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsOrder extends Model
{
    protected $fillable = [
        'user_id',
        'merchant_client_id',
        'provider',
        'service_id',
        'service_name',
        'country',
        'type',
        'getatext_id',
        'phone_number',
        'otp_code',
        'status',
        'provider_cost',
        'charged_to_user',
        'profit',
        'ordered_at',
        'completed_at',
    ];

    /**
     * Money-safety rule 1.2: provider cost and profit are private. The raw
     * supplier (`provider`) is masked too — users only ever see the public Model
     * (ProviderModels), never which third-party fulfilled the number.
     */
    protected $hidden = [
        'provider_cost',
        'profit',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'provider_cost' => 'decimal:4',
            'charged_to_user' => 'decimal:4',
            'profit' => 'decimal:4',
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
