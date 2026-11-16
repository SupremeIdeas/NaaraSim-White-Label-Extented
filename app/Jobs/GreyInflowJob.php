<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Grey told us a deposit landed in our Grey wallet (`transaction.received`). Re-read the wallet float and release every payout that was waiting
 * for funds, right now, instead of at the next scheduled run. Safe to run any number of times (both steps are idempotent), unique for a minute.
 */
class GreyInflowJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 60;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        Artisan::call('payouts:float-sync');
        Artisan::call('payouts:float-retry');
    }
}
