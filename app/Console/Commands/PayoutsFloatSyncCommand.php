<?php

namespace App\Console\Commands;

use App\Models\PayoutFloatBalance;
use App\Services\Payouts\FloatService;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\ReportsBalance;
use Illuminate\Console\Command;
use Throwable;

class PayoutsFloatSyncCommand extends Command
{
    protected $signature = 'payouts:float-sync';

    protected $description = 'Align tracked float with the balance the provider itself reports (only rails with auto-sync on)';

    public function handle(PayoutService $payouts, FloatService $float): int
    {
        $synced = 0;
        foreach (PayoutFloatBalance::where('auto_sync', true)->get() as $row) {
            $gateway = $payouts->gatewayFor($row->provider);
            if (! $gateway instanceof ReportsBalance) {
                continue;
            }
            try {
                $reported = $gateway->balances()[$row->currency] ?? null;
            } catch (Throwable $e) {
                $this->warn("{$row->provider}/{$row->currency}: ".$e->getMessage());

                continue; // a failed read proves nothing — leave the tracked balance alone
            }
            if ($reported !== null) {
                $float->syncTo($row, $reported);
                $synced++;
            }
        }
        $this->info("synced={$synced}");

        return self::SUCCESS;
    }
}
