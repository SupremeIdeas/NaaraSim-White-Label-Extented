<?php

namespace App\Console\Commands;

use App\Services\Payouts\Peer\EarningsTransferService;
use Illuminate\Console\Command;

class PayoutsPeerExpireCommand extends Command
{
    protected $signature = 'payouts:peer-expire';

    protected $description = 'Return member-to-member transfers nobody accepted in time to the sender';

    public function handle(EarningsTransferService $transfers): int
    {
        $this->info('returned='.$transfers->expireDue());

        return self::SUCCESS;
    }
}
