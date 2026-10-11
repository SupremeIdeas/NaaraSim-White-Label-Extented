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
        'user_id', 'type', 'method', 'corridor_id', 'country', 'currency', 'bank_code', 'bank_name',
        'account_number', 'lookup_hash', 'account_name', 'payee_kyc_name', 'provider', 'provider_recipient_ref',
        'provider_status', 'details',
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
            // Per-country destination fields (IBAN / SWIFT / sort code / branch …) — encrypted at rest.
            'details' => 'encrypted:array',
            // The destination itself (bank number / PayPal email / crypto address / Stripe id).
            'account_number' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // Keep the blind index in step with the encrypted number, so the number never
        // has to be queried (or decrypted) to detect a reused destination.
        static::saving(function (self $account) {
            if ($account->isDirty('account_number') || empty($account->lookup_hash)) {
                $account->lookup_hash = static::lookupHashFor((string) $account->account_number);
            }
        });

        // Global-rail accounts keep their Funding Radar enrollment in step (tracking only).
        static::saved(function (self $account) {
            if (\App\Services\Payouts\Rail\RailEnrollmentService::isGlobal($account->provider)) {
                app(\App\Services\Payouts\Rail\RailEnrollmentService::class)->sync($account);
            }
        });

        // One row per (provider, destination, user): lets the Guardian see the same
        // destination used by several users (gate G6) without reading the number.
        static::saved(function (self $account) {
            if (filled($account->lookup_hash) && ($account->wasRecentlyCreated || $account->wasChanged(['lookup_hash', 'provider']))) {
                PayoutAccountFingerprint::updateOrCreate(
                    ['provider' => $account->provider, 'fingerprint' => $account->lookup_hash, 'user_id' => $account->user_id],
                    ['payout_account_id' => $account->id],
                );
            }
        });
    }

    /**
     * Deterministic blind index of a destination: HMAC-SHA256 keyed with the app key,
     * over the number with whitespace/case normalised. Equal destinations collide,
     * nothing else can be recovered from it.
     */
    public static function lookupHashFor(string $accountNumber): string
    {
        $normalised = strtolower(preg_replace('/\s+/', '', $accountNumber));

        return hash_hmac('sha256', $normalised, (string) (config('payouts.fp_key') ?: config('app.key')));
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
