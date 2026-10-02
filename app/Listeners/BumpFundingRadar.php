<?php

namespace App\Listeners;

use App\Events\PayoutRequested;
use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Jobs\RadarBumpJob;
use App\Services\Payouts\Rail\RailEnrollmentService;

/** Only global-rail payouts move the radar; everything else is ignored cheaply. */
class BumpFundingRadar
{
    public function handle(PayoutRequested|PayoutSettled|PayoutReversed $event): void
    {
        $provider = $event->request->provider;
        if (RailEnrollmentService::isGlobal($provider)) {
            RadarBumpJob::dispatch($provider);
        }
    }
}
