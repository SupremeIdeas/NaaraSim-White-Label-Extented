<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RadarSnapshotter;
use Illuminate\Console\Command;

class PayoutsRadarSnapshotCommand extends Command
{
    protected $signature = 'payouts:radar-snapshot';

    protected $description = 'Write the Funding Radar exposure snapshots and refresh the live cache';

    public function handle(RadarSnapshotter $radar): int
    {
        $this->info('rows='.$radar->snapshot());

        return self::SUCCESS;
    }
}
