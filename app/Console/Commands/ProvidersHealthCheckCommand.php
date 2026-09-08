<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Support\ProviderHealth;
use Illuminate\Console\Command;

/**
 * providers:health-check (blueprint Section 17.2 + BUILD-5 §2). Every 15 min
 * (scheduled) it probes EVERY active provider across both the eSIM and number
 * stacks — not just the three wallet-funded ones — caches the result for the
 * admin dashboard, and alerts on a provider that is DOWN (API erroring) or LOW
 * (wallet below its threshold). A provider going down no longer stays silent
 * until a customer files a support ticket.
 */
class ProvidersHealthCheckCommand extends Command
{
    protected $signature = 'providers:health-check';

    protected $description = 'Probe every provider (eSIM + numbers), cache health, and alert on down/low';

    public function handle(ProviderHealth $health): int
    {
        $results = $health->checkAll();

        foreach ($results as $provider => $info) {
            if (($info['status'] ?? '') === 'down') {
                AlertAdminJob::dispatch(
                    code: strtoupper($provider).'_provider_down',
                    message: "{$provider} provider is unreachable/erroring: ".($info['error'] ?? 'unknown error'),
                    context: ['provider' => $provider, 'stack' => $info['stack'] ?? null, 'error' => $info['error'] ?? null],
                    severity: 'critical',
                );
            } elseif (($info['status'] ?? '') === 'low') {
                AlertAdminJob::dispatch(
                    code: strtoupper($provider).'_low_balance',
                    message: "{$provider} wallet balance {$info['balance']} is below the alert threshold {$info['threshold']}.",
                    context: ['provider' => $provider, 'balance' => $info['balance'], 'threshold' => $info['threshold'] ?? null],
                    severity: 'warning',
                );
            }
        }

        $this->info('Provider health checked: '.collect($results)->map(fn ($h, $p) => "$p={$h['status']}")->implode(', '));

        return self::SUCCESS;
    }
}
