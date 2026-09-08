<?php

namespace App\Console\Commands;

use App\Models\EsimUsageSnapshot;
use Illuminate\Console\Command;

/**
 * Connectivity Analytics blueprint Part A §2.7 — snapshots accumulate fast
 * (every active eSIM x every sync interval), so this keeps the table bounded.
 * Scheduled weekly; retention is config('esim.usage_snapshot_retention_days').
 */
class PruneEsimUsageSnapshots extends Command
{
    protected $signature = 'esim:prune-usage-snapshots';

    protected $description = 'Delete usage snapshots older than the configured retention window';

    public function handle(): int
    {
        $days = (int) config('esim.usage_snapshot_retention_days', 90);
        $deleted = EsimUsageSnapshot::where('captured_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} usage snapshot(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
