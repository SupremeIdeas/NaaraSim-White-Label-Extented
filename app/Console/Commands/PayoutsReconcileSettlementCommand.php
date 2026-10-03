<?php

namespace App\Console\Commands;

use App\Services\Payouts\Hardening\SettlementReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PayoutsReconcileSettlementCommand extends Command
{
    protected $signature = 'payouts:reconcile-settlement {provider} {file : CSV with reference,amount,currency[,fee,status]} {--from= : YYYY-MM-DD} {--to= : YYYY-MM-DD}';

    protected $description = 'Match a provider statement CSV against our payouts; flag differences, never auto-fix';

    public function handle(SettlementReconciler $reconciler): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error('Cannot read '.$file);

            return self::FAILURE;
        }
        $run = $reconciler->reconcileCsv(
            $this->argument('provider'), (string) file_get_contents($file),
            Carbon::parse($this->option('from') ?: now()->subDays(7)), Carbon::parse($this->option('to') ?: now()),
        );
        $this->info("Run #{$run->id}: {$run->matched} matched, {$run->flagged} to review.");

        return $run->flagged === 0 ? self::SUCCESS : self::FAILURE;
    }
}
