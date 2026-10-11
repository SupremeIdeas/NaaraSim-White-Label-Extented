<?php

namespace App\Services\Payouts\Hardening;

use App\Services\Payouts\Health\GatewayStatus;
use App\Services\Payouts\Health\GatewayStatusResolver;
use App\Services\Payouts\PayoutService;
use App\Support\JobHeartbeats;
use App\Support\PayoutSettings;

/**
 * "Why is (or isn't) my payout automatic?" — one plain-English checklist so nobody has to guess which of the
 * several switches is holding payouts back. Read-only. Each row says what it checks, whether it passes and, if
 * not, exactly what to change.
 */
class AutomationStatus
{
    public function __construct(private readonly PayoutService $payouts) {}

    /**
     * @return array{automatic: bool, rails: list<string>, checks: list<array{ok: bool, label: string, fix: ?string}>}
     */
    public function report(): array
    {
        $checks = [];
        $add = function (bool $ok, string $label, ?string $fix = null) use (&$checks) {
            $checks[] = ['ok' => $ok, 'label' => $label, 'fix' => $ok ? null : $fix];
        };

        $add(PayoutSettings::enabled(), 'Payouts are switched on', 'Turn on "Payouts enabled" in All payout settings.');
        $add(PayoutSettings::autopilot(), 'Settlement mode is Automatic', 'Set "Settlement mode" to Auto in All payout settings.');
        $add(PayoutSettings::autoApprovalEnabled(), 'Auto-approval is on', 'Turn on "Auto-approval" in All payout settings.');
        $add(! PayoutSettings::shadowMode(), 'Learning (shadow) mode is off', 'Turn off "Learning (shadow) mode" — while it is on a person approves everything.');

        // The provider list comes from the gateway registry (core rails AND updater-delivered ones), never a hand-kept array.
        $resolver = new GatewayStatusResolver($this->payouts);
        $rails = [];
        $why = [];
        foreach ($resolver->all() as $status) {
            if ($status->provider === 'manual_external') {
                continue; // the admin-pays-outside rail is never "automatic"
            }
            if (! $status->usable()) {
                if ($status->reason !== GatewayStatus::R_NOT_INSTALLED) {
                    $why[] = $status->adminMessage;
                }

                continue; // a rail that is not in play is simply not counted
            }
            $p = $status->provider;
            $on = PayoutSettings::providerAutoApprove($p);
            $add($on, $status->label.' pays out automatically', 'Turn on automatic approval for '.$status->label.' in Payouts -> Guardian.');
            $on && $rails[] = $p;
        }
        if ($rails === [] && ! collect($checks)->contains(fn ($c) => str_contains($c['label'], 'pays out automatically'))) {
            $add(false, 'At least one payout provider is ready', ($why !== [] ? implode(' ', array_slice($why, 0, 3)).' ' : '').'Add a provider key (e.g. Paystack) in Admin -> Provider Keys; only ready rails can pay.');
        }

        foreach (['payouts:guard-sweep' => 'The Guardian sweeper', 'payouts:guard-metrics' => 'The Guardian metrics job'] as $job => $label) {
            $interval = $job === 'payouts:guard-sweep' ? 60 : 3600;
            $last = JobHeartbeats::lastSuccessAt($job);
            $fresh = $last !== null && $last->gte(now()->subSeconds($interval * 3));
            $add($fresh || ! PayoutSettings::failClosedOnStaleHeartbeat(), $label.' is running', 'The scheduler (cron) is not running `php artisan schedule:run` every minute. Until it is, the Guardian sends everything to manual review on purpose.');
        }

        $automatic = collect($checks)->every(fn ($c) => $c['ok']) && $rails !== [];

        return ['automatic' => $automatic, 'rails' => $rails, 'checks' => $checks];
    }
}
