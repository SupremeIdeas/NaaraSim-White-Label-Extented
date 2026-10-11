<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RadarSnapshotter;
use Illuminate\Console\Command;

class PayoutsRadarPruneCommand extends Command
{
    protected $signature = 'payouts:radar-prune';

    protected $description = 'Roll up old Funding Radar snapshots (hourly after 14 days, daily after 90)';

    public function handle(RadarSnapshotter $radar): int
    {
        $this->info('removed='.$radar->prune());

        return self::SUCCESS;
    }
}
