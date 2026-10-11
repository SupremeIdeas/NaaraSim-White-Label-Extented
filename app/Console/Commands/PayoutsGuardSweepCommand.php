<?php

namespace App\Console\Commands;

use App\Services\Payouts\Guardian\GuardianSweeper;
use Illuminate\Console\Command;

class PayoutsGuardSweepCommand extends Command
{
    protected $signature = 'payouts:guard-sweep';

    protected $description = 'Claim due auto_pending / deferred payouts and queue a Payout Guardian evaluation for each';

    public function handle(GuardianSweeper $sweeper): int
    {
        $this->info('claimed='.$sweeper->sweep());

        return self::SUCCESS;
    }
}
