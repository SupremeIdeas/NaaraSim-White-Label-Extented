<?php

namespace App\Console\Commands;

use App\Jobs\SyncEsimCatalogueJob;
use App\Services\eSIM\CatalogueSyncService;
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

        foreach ($providers as $provider) {
            if ($this->option('now')) {
                $count = $sync->sync($provider);
                $this->info("Synced {$count} plans from {$provider}.");
            } else {
                SyncEsimCatalogueJob::dispatch($provider);
                $this->info("Queued catalogue sync for {$provider}.");
            }
        }

        return self::SUCCESS;
    }
}
