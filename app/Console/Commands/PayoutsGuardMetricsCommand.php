<?php

namespace App\Console\Commands;

use App\Services\Payouts\Guardian\GuardianMetrics;
use Illuminate\Console\Command;

class PayoutsGuardMetricsCommand extends Command
{
    protected $signature = 'payouts:guard-metrics';

    protected $description = 'Roll up Payout Guardian decision rates, latency and breaker status';

    public function handle(GuardianMetrics $metrics): int
    {
        $m = $metrics->roll();
        $this->info('review='.$m['manual_review'].' deferred='.$m['deferred'].' breaker='.($m['breaker_active'] ? 'ON' : 'off'));

        return self::SUCCESS;
    }
}
