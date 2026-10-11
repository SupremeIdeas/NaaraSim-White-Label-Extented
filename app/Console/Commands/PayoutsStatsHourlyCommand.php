<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RadarSnapshotter;
use Illuminate\Console\Command;

class PayoutsStatsHourlyCommand extends Command
{
    protected $signature = 'payouts:stats-hourly';

    protected $description = 'Recompute the hourly withdrawal stats buckets (last 2 hours, idempotent)';

    public function handle(RadarSnapshotter $radar): int
    {
        $this->info('buckets='.$radar->statsHourly());

        return self::SUCCESS;
    }
}
