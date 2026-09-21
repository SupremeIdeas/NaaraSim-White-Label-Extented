<?php

namespace App\Console\Commands;

use App\Jobs\SyncEsimCatalogueJob;
use App\Services\eSIM\CatalogueSyncService;
use App\Support\JobHeartbeats;
use App\Support\ProviderModels;
use Illuminate\Console\Command;

/**
 * Sync eSIM catalogues into esim_plans (retail recomputed via PricingEngine).
 * By default dispatches queued jobs (Horizon); --now runs inline for a quick
 * admin/manual sync.
 */
class SyncEsimCatalogueCommand extends Command
{
    protected $signature = 'esim:sync {provider? : esimgo|airalo|quibity|zendit|oneglobal|montymobile|gigs (all if omitted)} {--now : run synchronously instead of queueing}';

    protected $description = 'Sync eSIM provider catalogues and recompute retail pricing';

    public function handle(CatalogueSyncService $sync): int
    {
        $providers = $this->argument('provider')
            ? [$this->argument('provider')]
            : ['esimgo', 'airalo', 'quibity', 'zendit', 'oneglobal', 'montymobile', 'gigs'];

        $configured = [];
        $skipped = [];

        foreach ($providers as $provider) {
            if ($this->option('now')) {
                $count = $sync->sync($provider);
                $this->info("Synced {$count} plans from {$provider}.");
            } else {
                SyncEsimCatalogueJob::dispatch($provider);
                $this->info("Queued catalogue sync for {$provider}.");
            }

            if (ProviderModels::providerConfigured($provider)) {
                $configured[] = $provider;
            } else {
                $skipped[] = $provider;
            }
        }

        // Tier 4 #10 Phase B2.5 — a real "proof of work" detail attached to
        // this scheduled command's next heartbeat row, so the System Health
        // hero shows exactly what happened, not just a bare "OK".
        JobHeartbeats::note('esim:sync', match (true) {
            $skipped === [] => implode(', ', $configured).' synced.',
            $configured === [] => 'All '.count($skipped).' provider(s) skipped — not configured.',
            default => implode(', ', $configured).' synced; '.implode(', ', $skipped).' skipped (not configured).',
        });

        return self::SUCCESS;
    }
}
