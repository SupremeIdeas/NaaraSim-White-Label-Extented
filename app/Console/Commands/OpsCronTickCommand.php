<?php

namespace App\Console\Commands;

use App\Support\CronPulse;
use App\Support\JobHeartbeats;
use Illuminate\Console\Command;

/**
 * The cron's own pulse. Scheduled every minute; it does nothing but exist, so the scheduler's heartbeat listener records a row each time `schedule:run` fires.
 * System health reads that row to turn green the moment a cron is added (see App\Support\CronPulse).
 */
class OpsCronTickCommand extends Command
{
    protected $signature = 'ops:cron-tick';

    protected $description = 'Heartbeat for the scheduler: proves the cron is firing (System health reads it)';

    public function handle(): int
    {
        JobHeartbeats::note('ops:cron-tick', 'cron fired');
        CronPulse::recordEnvironment();

        return self::SUCCESS;
    }
}
