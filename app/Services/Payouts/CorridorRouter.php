<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use Illuminate\Support\Collection;

/**
 * Decides which rails can pay a given country/currency (Global Payout Layer,
 * Phase 1). It only ever returns ENABLED corridors whose gateway is actually
 * configured (`available()`), ordered by priority. Lanes are never crossed: a
 * corridor names one provider + method, and fallback happens only between
 * corridors that pay the same country+currency.
 *
 * Health: a simple "is the gateway configured" check for now. The stuck-payout
 * watchdog (Phase 4) is what trips a rail that is configured but failing.
 */
class CorridorRouter
{
    public function __construct(private PayoutService $payouts) {}

    /**
     * @return Collection<int, PayoutCorridor> best first
     */
    public function optionsFor(string $country, ?string $currency = null, ?float $usd = null, ?string $method = null): Collection
    {
        $query = PayoutCorridor::query()->enabled()->where('country', strtoupper($country));
        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }
        if ($method !== null) {
            $query->where('method', $method);
        }

        return $query->orderBy('priority')->orderBy('id')->get()
            ->filter(fn (PayoutCorridor $c) => $this->payouts->gatewayFor($c->provider)?->available() === true)
            ->reject(fn (PayoutCorridor $c) => \App\Services\Payouts\Rail\RadarAlerts::isUnhealthy($c->provider)) // failure-rate breaker
            ->filter(fn (PayoutCorridor $c) => $usd === null || $c->allows($usd))
            ->values();
    }

    public function pick(string $country, ?string $currency = null, ?float $usd = null, ?string $method = null): ?PayoutCorridor
    {
        return $this->optionsFor($country, $currency, $usd, $method)->first();
    }

    /**
     * The corridor a saved payout account is paid through: same country, currency
     * and provider (an account is already bound to one rail), enabled. Gateway
     * availability is NOT required here — the account is already saved; whether
     * the rail is currently reachable is the send-time check's job.
     */
    public function corridorForAccount(PayoutAccount $account): ?PayoutCorridor
    {
        return PayoutCorridor::query()->enabled()
            ->where('country', strtoupper((string) $account->country))
            ->where('currency', strtoupper((string) $account->currency))
            ->where('provider', $account->provider)
            ->when($account->method, fn ($q, $m) => $q->where('method', $m))
            ->orderBy('priority')->orderBy('id')
            ->first();
    }
}
