<?php

namespace App\Services\Payouts\Health;

use App\Services\Payouts\ExplainsAvailability;
use App\Services\Payouts\Extensions\PayoutRailExtensions;
use App\Services\Payouts\Hardening\PayoutEnvGuard;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\Rail\RadarAlerts;
use App\Support\PayoutSettings;

/**
 * The single place that answers "is this payout gateway usable, and if not, why?". Replaces the hand-kept provider lists and the bare
 * `gatewayFor() === null` that collapsed six different causes into "not configured" / "temporarily unavailable" (Engineering v3, Phase 2.0b).
 * The provider list comes from the gateway registry (core + updater-delivered rails) plus the planned-rail catalogue, never a literal array.
 */
class GatewayStatusResolver
{
    public function __construct(private readonly PayoutService $payouts)
    {
    }

    /** @return list<string> every payout provider we know of: registered gateways first, then rails not installed yet */
    public function providers(): array
    {
        $names = $this->payouts->providerNames();
        foreach (array_keys((array) config('payouts.planned_rails', [])) as $planned) {
            if (! in_array($planned, $names, true)) {
                $names[] = $planned;
            }
        }

        return $names;
    }

    /** @return list<GatewayStatus> */
    public function all(): array
    {
        return array_map(fn (string $p) => $this->statusOf($p), $this->providers());
    }

    public function statusOf(string $provider): GatewayStatus
    {
        $label = ucfirst(str_replace('_', ' ', $provider));
        $core = PayoutRailExtensions::isCore($provider);
        $gateway = $this->payouts->gateway($provider);

        if (! $core) {
            $planned = (array) (config('payouts.planned_rails', [])[$provider] ?? []);
            $label = (string) ($planned['label'] ?? $label);
            if (in_array($provider, PayoutSettings::disabledExtensions(), true)) {
                return $this->make($provider, $label, GatewayStatus::S_OFF, GatewayStatus::R_DISABLED, "{$label} is switched off in Payout settings (Rail extensions).", 'Switch it on in Payout settings, Rail extensions.', false, false);
            }
            if ($gateway === null) {
                $problem = collect(PayoutRailExtensions::problems())->firstWhere('slug', $provider);
                if ($problem !== null && $problem['message'] !== 'Switched off by an admin.') {
                    return $this->make($provider, $label, GatewayStatus::S_BLOCKED, GatewayStatus::R_EXT_PROBLEM, "{$label} is installed but cannot load: ".$problem['message'], 'Reinstall the rail from the updater, or ask support with this message.', false, false);
                }

                return $this->make($provider, $label, GatewayStatus::S_NOT_CONFIGURED, GatewayStatus::R_NOT_INSTALLED, "{$label} is not installed on this site yet.".(isset($planned['note']) ? ' '.$planned['note'] : ''), 'Install the rail from the updater when it is released for your site.', false, false);
            }
        }
        if ($gateway === null) {
            return $this->make($provider, $label, GatewayStatus::S_NOT_CONFIGURED, GatewayStatus::R_UNAVAILABLE, "{$label} is not available on this site.", null, false, $core);
        }

        if (! $gateway->available()) {
            $why = $gateway instanceof ExplainsAvailability ? $gateway->unavailableReason() : null;
            if ($why === GatewayStatus::R_ENV_MISMATCH) {
                $mode = PayoutEnvGuard::mode();

                return $this->make($provider, $label, GatewayStatus::S_BLOCKED, GatewayStatus::R_ENV_MISMATCH, "{$label} has its keys, but they belong to the other environment (payouts are in ".strtoupper($mode).' mode).', $mode === 'live' ? 'Use the live keys and the live URL, or switch Payout mode back to sandbox.' : 'Use the sandbox keys and URL, or switch Payout mode to live (only on the production server).', true, $core);
            }

            return $this->make($provider, $label, GatewayStatus::S_NOT_CONFIGURED, GatewayStatus::R_NO_KEY, "{$label} has no credentials saved.", 'Add its keys in Admin, Provider Keys.', true, $core);
        }

        if (! PayoutEnvGuard::allows($provider)) {
            $mode = PayoutEnvGuard::mode();

            return $this->make($provider, $label, GatewayStatus::S_BLOCKED, GatewayStatus::R_ENV_MISMATCH, $mode === 'live' ? "{$label} has a TEST key, but payouts are in LIVE mode." : "{$label} has a LIVE key, but payouts are in SANDBOX mode.", $mode === 'live' ? 'Replace it with the live key, or switch Payout mode to sandbox.' : 'Use the test key here, or switch Payout mode to live on the production server.', true, $core);
        }
        if (RadarAlerts::isUnhealthy($provider)) {
            return $this->make($provider, $label, GatewayStatus::S_FAILING, GatewayStatus::R_BREAKER, "{$label} is paused: its recent payout failure rate tripped the safety breaker.", 'Open Payouts, Health to see the failures. It re-opens by itself once it recovers.', true, $core);
        }

        return $this->make($provider, $label, GatewayStatus::S_CONFIGURED, GatewayStatus::R_OK, "{$label} is ready.", null, true, $core);
    }

    private function make(string $provider, string $label, string $state, string $reason, string $message, ?string $fix, bool $installed, bool $core): GatewayStatus
    {
        return new GatewayStatus($provider, $label, $state, $reason, $message, $fix, $installed, $core);
    }
}
