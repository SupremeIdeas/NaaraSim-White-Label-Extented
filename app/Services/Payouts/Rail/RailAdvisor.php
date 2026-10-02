<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use App\Support\PayoutSettings;
use App\Models\Setting;

/**
 * Which payout rail should this person use? (Rail Guide §3.) Pure, deterministic and
 * explainable: every option carries `reasons[]` for the on-screen "Why this?" line.
 *
 * States: recommended | available | coming_soon | not_supported | blocked | global_fallback
 *         | unavailable (provider unhealthy) | disabled (global while a fast rail exists)
 *
 * Honest-copy rule: the history signal is "the rail you already use with us", never a
 * claim that it is technically required to be paid out through it.
 */
class RailAdvisor
{
    private const FAST_RAILS = ['paystack', 'flutterwave', 'stripe_connect'];

    private const OPTIONAL_RAILS = ['paypal', 'cryptomus'];

    private const STATIC_ORDER = ['paystack', 'flutterwave', 'stripe_connect'];

    public function __construct(
        private PayoutRailRegistry $registry,
        private RailHistory $history,
        private PayoutService $payouts,
    ) {}

    /**
     * @return array{country: string, verdict: string, options: list<array<string, mixed>>, current: ?array<string, mixed>}
     */
    public function advise(User $user, ?string $country = null, ?string $currency = null): array
    {
        $country = strtoupper($country ?: (string) $user->country_code ?: 'ZZ');
        $states = $this->registry->railsFor($country, array_merge(self::FAST_RAILS, self::OPTIONAL_RAILS, ['global']));

        if (in_array($country, PayoutSettings::deniedCountries(), true)) {
            return ['country' => $country, 'verdict' => 'blocked', 'options' => [], 'current' => $this->current($user)];
        }

        // 1. Fast rails the country has AND we have switched on (+ admin-enabled optional rails).
        $declinedStripe = PayoutAccount::where('user_id', $user->id)->where('provider', 'stripe')->whereIn('provider_status', ['declined', 'rejected'])->exists();
        $fast = [];
        foreach (array_merge(self::FAST_RAILS, self::OPTIONAL_RAILS) as $rail) {
            if ($states[$rail] !== 'available') {
                continue;
            }
            if ($rail === 'stripe_connect' && $declinedStripe) {
                continue; // an earlier declined Connect onboarding removes Stripe and re-runs the scoring
            }
            $fast[] = $rail;
        }

        $healthy = [];
        $unhealthy = [];
        foreach ($fast as $rail) {
            ($this->providerHealthy($rail) ? $healthy[] = $rail : $unhealthy[] = $rail);
        }

        $options = [];

        if ($healthy !== []) {
            $counts = $this->history->counts($user);
            $ranking = array_keys($this->payouts->rankedGateways());   // platform volume order (provider names)
            $order = $this->rank($healthy, $counts, $ranking);

            foreach ($order as $i => $rail) {
                $reasons = ['supported_in_country'];
                ($counts[$rail] ?? 0) > 0 && $reasons[] = 'you_paid_with_it_'.$counts[$rail].'_times';
                $i === 0 && ($counts[$rail] ?? 0) === 0 && $this->platformTop($rail, $ranking) && $reasons[] = 'highest_platform_volume';
                $options[] = $this->option($rail, $i === 0 ? 'recommended' : 'available', $reasons, $counts[$rail] ?? 0);
            }
            foreach ($unhealthy as $rail) {
                $options[] = $this->option($rail, 'unavailable', ['temporarily_unavailable']);
            }

            // Global is never an equal choice while a fast rail exists.
            $allow = (bool) Setting::getValue('payouts.global_rail.allow_when_local_available', false);
            if (in_array($states['global'], ['available'], true)) {
                $options[] = $this->option('global', $allow ? 'available' : 'disabled', ['slower_option', $allow ? 'admin_allows_global' : 'fast_rail_available']);
            }
            $verdict = 'fast_available';
        } elseif ($states['global'] === 'available') {
            $options[] = $this->option('global', 'global_fallback', ['no_fast_rail_in_country', 'global_payout_available']);
            $verdict = $unhealthy !== [] ? 'fast_unavailable_global' : 'global_only';
            foreach ($unhealthy as $rail) {
                $options[] = $this->option($rail, 'unavailable', ['temporarily_unavailable']);
            }
        } else {
            // A rail that IS enabled here but unhealthy/unconfigured is "temporarily unavailable",
            // never "not available in your country" — the two read very differently to a user.
            $verdict = $unhealthy !== [] ? 'fast_unavailable' : 'none_available';
            foreach (array_unique(array_merge(self::FAST_RAILS, $unhealthy, ['global'])) as $rail) {
                $options[] = in_array($rail, $unhealthy, true)
                    ? $this->option($rail, 'unavailable', ['temporarily_unavailable'])
                    : $this->option($rail, $states[$rail] === 'coming_soon' ? 'coming_soon' : 'not_supported', [$states[$rail]]);
            }
        }

        // Rails that exist but are not available here are listed (greyed) for transparency.
        if ($verdict === 'fast_available' || $verdict === 'global_only') {
            foreach (self::FAST_RAILS as $rail) {
                if (! in_array($rail, array_column($options, 'rail'), true)) {
                    $options[] = $this->option($rail, $states[$rail] === 'coming_soon' ? 'coming_soon' : 'not_supported', [$states[$rail]]);
                }
            }
        }

        return ['country' => $country, 'verdict' => $verdict, 'options' => $options, 'current' => $this->current($user)];
    }

    /** Healthy = the payout gateway is configured AND the failure-rate breaker has not pulled it. */
    public function providerHealthy(string $rail): bool
    {
        foreach (PayoutRailRegistry::providersFor($rail) as $provider) {
            if (RadarAlerts::isUnhealthy($provider) || $this->payouts->gatewayFor($provider) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deterministic order: the user's own funding history first, then platform ranking,
     * then the static order paystack, flutterwave, stripe_connect, others.
     *
     * @param  list<string>  $rails
     * @param  array<string, int>  $counts
     * @param  list<string>  $ranking provider names, best first
     * @return list<string>
     */
    private function rank(array $rails, array $counts, array $ranking): array
    {
        $static = array_flip(array_merge(self::STATIC_ORDER, self::OPTIONAL_RAILS));
        $platform = array_flip(array_map(fn ($p) => PayoutRailRegistry::railForProvider($p), $ranking));

        usort($rails, fn ($a, $b) => [-($counts[$a] ?? 0), $platform[$a] ?? 99, $static[$a] ?? 99] <=> [-($counts[$b] ?? 0), $platform[$b] ?? 99, $static[$b] ?? 99]);

        return $rails;
    }

    private function platformTop(string $rail, array $ranking): bool
    {
        return isset($ranking[0]) && PayoutRailRegistry::railForProvider($ranking[0]) === $rail;
    }

    /** @param list<string> $reasons */
    private function option(string $rail, string $state, array $reasons, int $history = 0): array
    {
        return ['rail' => $rail, 'state' => $state, 'reasons' => $reasons, 'you_use_it' => $history > 0, 'badges' => array_values(array_filter([
            $state === 'recommended' ? 'recommended' : null, $history > 0 ? 'you_use_this' : null, $state === 'coming_soon' ? 'coming_soon' : null,
        ]))];
    }

    /** The rail the user already has an account on, so the guide can say "Your current rail" / "Switch". */
    private function current(User $user): ?array
    {
        $account = PayoutAccount::where('user_id', $user->id)->where('is_verified', true)->latest('id')->first();

        return $account ? ['rail' => PayoutRailRegistry::railForProvider($account->provider), 'provider' => $account->provider, 'country' => $account->country] : null;
    }
}
