<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RadarAlerts;
use Illuminate\Console\Command;

class PayoutsRadarAlertsCommand extends Command
{
    protected $signature = 'payouts:radar-alerts';

    protected $description = 'Raise Funding Radar alerts (float short, concentration, velocity, stale snapshot, failure rate)';

    public function handle(RadarAlerts $alerts): int
    {
        $this->info('raised='.count($alerts->run()));

        return self::SUCCESS;
    }
}
