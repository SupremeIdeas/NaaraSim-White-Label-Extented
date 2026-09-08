<?php

namespace App\Jobs;

use App\Services\eSIM\CatalogueSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued catalogue sync for one provider (every external API call runs as a
 * Horizon-backed job — blueprint rule 1.1). Retries with backoff.
 */
class SyncEsimCatalogueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $provider)
    {
    }

    public function handle(CatalogueSyncService $sync): void
    {
        $sync->sync($this->provider);
    }
}
