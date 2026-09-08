<?php

namespace App\Console\Commands;

use App\Services\Staff\StaffCompensationService;
use Illuminate\Console\Command;

/**
 * Close the previous month's staff profit-share (NAARA-BUILD-23 §3). Runs on the
 * 1st of each month; the service computes the period profit once, applies each
 * active staff member's percentage to that single figure, and is idempotent per
 * profile+period (a re-run never double-pays).
 */
class StaffCompensationCloseCommand extends Command
{
    protected $signature = 'staff:compensation-close';

    protected $description = 'Accrue the previous month\'s staff profit-share to each active staff ledger';

    public function handle(StaffCompensationService $service): int
    {
        $result = $service->closeMonth();
        $this->info("Staff compensation {$result['period']}: profit \${$result['profit']}, credited {$result['credited']} staff, total \${$result['total']}.");

        return self::SUCCESS;
    }
}
