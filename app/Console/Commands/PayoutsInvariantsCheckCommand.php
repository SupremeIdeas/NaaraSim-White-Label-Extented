<?php

namespace App\Console\Commands;

use App\Services\Payouts\Hardening\PayoutInvariants;
use Illuminate\Console\Command;

class PayoutsInvariantsCheckCommand extends Command
{
    protected $signature = 'payouts:invariants-check {--no-alert : Do not alert admins (dry run)}';

    protected $description = 'Read-only proof that the payout ledgers agree with the payout state machine (nightly)';

    public function handle(PayoutInvariants $checker): int
    {
        $run = $checker->run($this->option('no-alert') ? 'cli-dry' : 'schedule', ! $this->option('no-alert'));
        foreach ($run->results as $r) {
            $this->line(($r['ok'] ? '[ok]   ' : '[FAIL] ').$r['name'].($r['ok'] ? '' : ' ('.$r['count'].')'));
        }

        return $run->violation_count === 0 ? self::SUCCESS : self::FAILURE;
    }
}
