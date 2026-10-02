<?php

namespace App\Console\Commands;

use App\Services\Payouts\PayoutStuckWatchdog;
use Illuminate\Console\Command;

class PayoutsStuckWatchdogCommand extends Command
{
    protected $signature = 'payouts:stuck-watchdog';

    protected $description = 'Look up payouts stuck in processing past their rail SLA; alert on any that cannot be verified';

    public function handle(PayoutStuckWatchdog $watchdog): int
    {
        $s = $watchdog->run();
        $this->info("checked={$s['checked']} resolved={$s['resolved']} alerted={$s['alerted']}");

        return self::SUCCESS;
    }
}
