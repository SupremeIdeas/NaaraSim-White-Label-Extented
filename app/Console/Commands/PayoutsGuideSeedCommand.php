<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RailRegistrySeeder;
use Illuminate\Console\Command;

class PayoutsGuideSeedCommand extends Command
{
    protected $signature = 'payouts:guide-seed';

    protected $description = 'Seed/verify the payout rail registry (Paystack, Flutterwave, Stripe Connect). Never overwrites an admin override.';

    public function handle(RailRegistrySeeder $seeder): int
    {
        $r = $seeder->run();
        $this->info("seeded={$r['seeded']} drift=".count($r['drift']).($r['skipped'] ? ' skipped='.implode(',', $r['skipped']) : ''));
        foreach ($r['drift'] as $d) {
            $this->warn($d);
        }

        return self::SUCCESS;
    }
}
