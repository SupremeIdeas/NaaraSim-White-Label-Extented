<?php

namespace App\Console\Commands;

use App\Services\Payouts\Rail\RailEnrollmentService;
use Illuminate\Console\Command;

class PayoutsRailBackfillCommand extends Command
{
    protected $signature = 'payouts:rail-backfill';

    protected $description = 'Create rail enrollments for users whose payout account is already on a global-rail provider (idempotent)';

    public function handle(RailEnrollmentService $service): int
    {
        $this->info('enrollments='.$service->backfill());

        return self::SUCCESS;
    }
}
