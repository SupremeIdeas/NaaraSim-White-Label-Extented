<?php

namespace App\Console\Commands;

use App\Services\Payouts\PayoutReconciler;
use Illuminate\Console\Command;

class PayoutsReconcileUnknownCommand extends Command
{
    protected $signature = 'payouts:reconcile-unknown';

    protected $description = 'Resolve payouts whose provider outcome is unknown (timeout/5xx/dead worker after submit) by asking the provider';

    public function handle(PayoutReconciler $reconciler): int
    {
        $s = $reconciler->reconcileDue();
        $this->info("resolved={$s['resolved']} waiting={$s['waiting']} manual={$s['manual']}");

        return self::SUCCESS;
    }
}
