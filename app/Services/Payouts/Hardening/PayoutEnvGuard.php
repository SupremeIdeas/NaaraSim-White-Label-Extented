<?php

namespace App\Services\Payouts\Hardening;

/**
 * Sandbox/live separation (Addendum D-3.16). Money must never move with the wrong class of key:
 *   - PAYOUT_ENV=live is only honoured when APP_ENV=production;
 *   - a LIVE provider key is refused unless PAYOUT_ENV=live (a production key on a laptop or staging box);
 *   - a TEST key is refused when PAYOUT_ENV=live (a live deploy silently running on sandbox).
 * Providers whose keys carry no test/live marker pass; real keys are added last, one corridor at a time.
 */
class PayoutEnvGuard
{
    /** provider => config key holding its secret */
    private const KEYS = ['paystack' => 'services.paystack.secret_key', 'stripe' => 'services.stripe.secret_key'];

    public static function mode(): string
    {
        return config('payouts.env') === 'live' && app()->environment('production') ? 'live' : 'sandbox';
    }

    public static function allows(string $provider): bool
    {
        $configKey = self::KEYS[$provider] ?? null;
        if ($configKey === null) {
            return true;
        }
        $key = (string) config($configKey);
        if ($key === '') {
            return true; // not configured: gatewayFor() already treats that as unavailable
        }

        $isLive = str_starts_with($key, 'sk_live') || str_starts_with($key, 'rk_live');
        $isTest = str_starts_with($key, 'sk_test') || str_starts_with($key, 'rk_test');

        return match (self::mode()) {
            'live' => ! $isTest,
            default => ! $isLive,
        };
    }
}
