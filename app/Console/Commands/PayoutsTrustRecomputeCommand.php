<?php

namespace App\Console\Commands;

use App\Services\Payouts\Guardian\TrustRecomputer;
use Illuminate\Console\Command;

class PayoutsTrustRecomputeCommand extends Command
{
    protected $signature = 'payouts:trust-recompute';

    protected $description = 'Recompute payout trust tiers (new/trusted) from each payee\'s record';

    public function handle(TrustRecomputer $trust): int
    {
        $this->info('profiles='.$trust->run());

        return self::SUCCESS;
    }
}
