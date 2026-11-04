<?php

namespace App\Jobs;

use App\Services\Updater\WhiteLabelUpdateClient;
use App\Support\Activation\LicenseState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One signed check-in with the original platform, run off the request cycle. Same call the scheduled heartbeat makes. */
class RefreshLicenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function handle(WhiteLabelUpdateClient $client): void
    {
        if (LicenseState::isActivated()) {
            $client->refreshEntitlement();
        }
    }
}
