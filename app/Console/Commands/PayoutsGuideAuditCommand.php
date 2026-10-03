<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RailRegistrySeeder;
use Illuminate\Console\Command;

class PayoutsGuideAuditCommand extends Command
{
    protected $signature = 'payouts:guide-audit';

    protected $description = 'Re-check the payout rail registry against provider APIs; alert on drift and stale rows (monthly)';

    public function handle(RailRegistrySeeder $seeder): int
    {
        $r = $seeder->audit();
        $this->info('drift='.count($r['drift']).' stale='.$r['stale']);

        return self::SUCCESS;
    }
}
