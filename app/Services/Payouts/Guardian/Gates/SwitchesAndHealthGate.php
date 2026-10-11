<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;
use App\Services\Payouts\PayoutService;
use App\Support\PayoutSettings;

/** G1 — payouts are on, the corridor/provider can carry it, the country is not denied. */
class SwitchesAndHealthGate implements Gate
{
    public function __construct(private PayoutService $payouts) {}

    public function id(): string
    {
        return 'G1_switches_health';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        if (! PayoutSettings::enabled()) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'payouts_disabled');
        }

        if (\App\Services\Payouts\Hardening\PayoutFreeze::isFrozen((int) $ctx->request->user_id)) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'user_frozen');
        }

        $country = strtoupper((string) ($ctx->account?->country ?? ''));
        $denied = PayoutSettings::deniedCountries();
        foreach (array_filter([$country, strtoupper((string) $ctx->user?->country_code)]) as $c) {
            if (in_array($c, $denied, true)) {
                return GateResult::fail($this->id(), GateResult::HOLD, 'country_denied', ['country' => $c]);
            }
        }

        if ($ctx->user !== null && app(\App\Services\Payouts\Guardian\SanctionsScreening::class)->isHit($ctx->user, $ctx->account)) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'sanctions_hit');
        }

        if ($ctx->request->corridor_id !== null && ($ctx->corridor === null || ! $ctx->corridor->enabled)) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'corridor_disabled', ['corridor_id' => $ctx->request->corridor_id]);
        }

        if (! $this->payouts->knowsProvider($ctx->request->provider)) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'provider_unknown', ['provider' => $ctx->request->provider]);
        }
        $gateway = $this->payouts->gatewayFor($ctx->request->provider); // null = registered but not configured / down
        // Never redirect to another rail (the destination is bound to this one, and an in-flight request
        // must never move providers): park it and retry every 5 minutes (Addendum D-3.7).
        $unhealthy = $gateway !== null && \App\Services\Payouts\Rail\RadarAlerts::isUnhealthy((string) $ctx->request->provider);
        if ($gateway === null || $unhealthy) {
            $this->outageAlert($ctx);

            return GateResult::fail($this->id(), GateResult::DEFER, $unhealthy ? 'provider_unhealthy' : 'provider_unavailable', ['provider' => $ctx->request->provider], now()->addMinutes(5));
        }

        return GateResult::pass($this->id(), ['provider' => $gateway->name(), 'corridor' => $ctx->corridor?->id]);
    }

    /** A request stuck behind an outage for longer than the configured time raises one alert an hour. */
    private function outageAlert(GuardianContext $ctx): void
    {
        $waited = $ctx->request->created_at?->diffInMinutes(now()) ?? 0;
        if ($waited >= PayoutSettings::outageAlertMinutes()
            && \Illuminate\Support\Facades\Cache::add('payouts.outage_alert.'.$ctx->request->provider, 1, 3600)) {
            \App\Jobs\AlertAdminJob::dispatch(
                code: 'payout_provider_outage',
                message: "Payouts via {$ctx->request->provider} have been queued for over {$waited} minutes (provider unavailable or unhealthy). Users are told it's a temporary provider issue; consider notifying them.",
                context: ['provider' => $ctx->request->provider, 'waited_minutes' => $waited],
            );
        }
    }
}
