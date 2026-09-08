<?php

namespace App\Console\Commands;

use App\Services\Payouts\PayoutService;
use Illuminate\Console\Command;

/**
 * Recompute the volume-based payout-gateway ranking (BUILD-4 §8.4). Runs on a
 * daily cadence rather than on every page load: it just busts the cache and
 * warms it once, so the "Recommended — fast payout" badge reflects recent real
 * inbound volume without a per-request query.
 */
class PayoutsRankCommand extends Command
{
    protected $signature = 'payouts:rank';

    protected $description = 'Recompute the inbound-volume ranking of payout gateways (cached)';

    public function handle(PayoutService $payouts): int
    {
        $payouts->flushRanking();
        $ranked = $payouts->rankedGateways();  // warms the cache
        $recommended = $payouts->recommendedGateway();

        foreach ($ranked as $name => $vol) {
            $this->line(sprintf('  %-14s %s', $name, number_format($vol, 2)));
        }
        $this->info('Recommended payout gateway: '.($recommended ?? 'none (insufficient inbound volume)'));

        return self::SUCCESS;
    }
}
