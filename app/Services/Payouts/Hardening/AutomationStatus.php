<?php

namespace App\Services\Payouts\Hardening;

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

        $rails = [];
        foreach (['paystack', 'flutterwave', 'stripe', 'paypal', 'cryptomus'] as $p) {
            if (! $this->payouts->knowsProvider($p)) {
                continue;
            }
            $configured = $this->payouts->gatewayFor($p) !== null;
            if (! $configured) {
                continue; // a rail with no keys is simply not in play
            }
            $on = PayoutSettings::providerAutoApprove($p);
            $add($on, ucfirst($p).' pays out automatically', 'Turn on automatic approval for '.ucfirst($p).' in Payouts -> Guardian.');
            $on && $rails[] = $p;
        }
        if ($rails === [] && ! collect($checks)->contains(fn ($c) => str_contains($c['label'], 'pays out automatically'))) {
            $add(false, 'At least one payout provider is configured', 'Add a provider key (e.g. Paystack) in Admin -> API keys; only configured rails can pay.');
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
