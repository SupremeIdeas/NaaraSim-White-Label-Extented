<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verified destination for money leaving the platform (ROADMAP §Layer 0.1).
 * The account_name is resolved from the PSP and read-only; the raw
 * account_number is masked everywhere it's shown so a full number is never
 * echoed back to the UI.
 */
class PayoutAccount extends Model
{
    protected $fillable = [
        'user_id', 'type', 'country', 'currency', 'bank_code', 'bank_name',
        'account_number', 'account_name', 'provider', 'provider_recipient_ref',
        'is_verified', 'is_default', 'details_submitted', 'charges_enabled', 'payouts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_default' => 'boolean',
            'details_submitted' => 'boolean',
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Masked account number for display — never the full number. */
    public function getMaskedNumberAttribute(): string
    {
        $n = (string) $this->account_number;
        $len = strlen($n);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', min($len - 4, 6)).substr($n, -4);
    }
}
