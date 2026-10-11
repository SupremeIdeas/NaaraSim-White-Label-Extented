<?php

namespace App\Console\Commands;

use App\Models\PayoutFloatBalance;
use App\Services\Payouts\FloatService;
use App\Services\Payouts\Guardian\GuardianSweeper;
use Illuminate\Console\Command;

class PayoutsFloatRetryCommand extends Command
{
    protected $signature = 'payouts:float-retry';

    protected $description = 'Resume payouts waiting for funds (oldest first) and re-evaluate float-deferred ones';

    public function handle(GuardianSweeper $sweeper, FloatService $float): int
    {
        $released = PayoutFloatBalance::all()->sum(fn ($row) => $float->releaseAwaiting($row->provider, $row->currency));
        $this->info('released='.$released.' claimed='.$sweeper->sweep(50, floatOnly: true));

        return self::SUCCESS;
    }
}
