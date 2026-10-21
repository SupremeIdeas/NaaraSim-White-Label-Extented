<?php

namespace App\Services\Payouts\Hardening;

/**
 * Sandbox/live separation (Addendum D-3.16). Money must never move with the wrong class of key:
 *   - live needs somewhere real to run (APP_ENV=production or a real https website), and is chosen in Admin > Payout settings (Auto follows the website);
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
        $choice = self::choice();
        if ($choice === 'sandbox') {
            return 'sandbox';
        }

        // 'live' and 'auto' both need somewhere real to run: a production install, or a real https website. A laptop or test box never moves real money.
        return app()->environment('production') || self::onRealWebsite() ? 'live' : 'sandbox';
    }

    /**
     * What was chosen: the admin's Payout environment setting wins (so a stale PAYOUT_ENV line in an old .env file can never hold a live site in sandbox), then the
     * env value if it names a mode, otherwise auto.
     */
    public static function choice(): string
    {
        try {
            $set = \App\Models\Setting::getValue(\App\Support\PayoutSettings::ENV_MODE);
        } catch (\Throwable) {
            $set = null;
        }
        if (in_array($set, ['auto', 'live', 'sandbox'], true)) {
            return $set;
        }
        $cfg = config('payouts.env');

        return in_array($cfg, ['live', 'sandbox'], true) ? $cfg : 'auto';
    }

    /** A public https website, as opposed to localhost, a private IP or a test domain. */
    public static function onRealWebsite(): bool
    {
        $url = parse_url((string) config('app.url'));
        $host = strtolower((string) ($url['host'] ?? ''));
        if (($url['scheme'] ?? '') !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
            return false;
        }

        return ! preg_match('/\.(test|local|localhost|invalid|example|internal|lan)$/', $host);
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
