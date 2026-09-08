<?php

namespace App\Console\Commands;

use App\Jobs\CaptureEsimUsageSnapshotJob;
use App\Models\EsimOrder;
use Illuminate\Console\Command;

/**
 * Connectivity Analytics blueprint Part A §2.3 — the scheduled poll that fills
 * esim_usage_snapshots. Only active orders with an ICCID are worth polling
 * (skip pending/expired/cancelled/failed — no point, and it respects provider
 * rate limits). Chunked with a short delay between batches so a provider with
 * thousands of active eSIMs never gets hammered in one burst.
 */
class SyncEsimUsageSnapshots extends Command
{
    protected $signature = 'esim:sync-usage {--chunk=20 : orders dispatched per burst} {--delay=2 : seconds between bursts}';

    protected $description = 'Poll active eSIM orders for a fresh usage reading and record a snapshot';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $delaySeconds = max(0, (int) $this->option('delay'));

        $total = 0;
        $delay = 0;

        EsimOrder::query()
            ->where('status', 'active')
            ->whereNotNull('iccid')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($orders) use (&$total, &$delay, $delaySeconds) {
                foreach ($orders as $order) {
                    CaptureEsimUsageSnapshotJob::dispatch($order->id)
                        ->onQueue('usage-sync')
                        ->delay(now()->addSeconds($delay));
                    $total++;
                }
                $delay += $delaySeconds;
            });

        $this->info("Queued usage snapshots for {$total} active eSIM(s).");

        return self::SUCCESS;
    }
}
