<?php

namespace App\Jobs;

use App\Services\Payouts\Rail\FundingRadar;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes one rail's live Funding Radar numbers right after a payout is requested,
 * settled or reversed, so the dashboard moves within seconds. The 5-minute snapshot
 * remains the source of truth and corrects any drift. Read-only on the money tables.
 */
class RadarBumpJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 5;

    public function __construct(public string $provider)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->provider;
    }

    public function handle(FundingRadar $radar): void
    {
        $radar->refreshCache($this->provider);
    }
}
