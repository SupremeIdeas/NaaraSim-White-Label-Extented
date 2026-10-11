<?php

namespace App\Listeners;

use App\Events\PayoutRequested;
use App\Notifications\PayoutRequestedNotice;
use App\Services\Payouts\PayoutAdmission;
use App\Support\PayoutSettings;

/** Email the payee when THEY ask for a withdrawal, with a "This wasn't me" link (Addendum D-3.5). */
class NotifyPayoutRequested
{
    public function handle(PayoutRequested $event): void
    {
        $r = $event->request;
        if (! PayoutSettings::notifyOnRequest() || ! in_array($r->source_bucket, PayoutAdmission::USER_BUCKETS, true)) {
            return;
        }
        $r->user?->notify(new PayoutRequestedNotice($r->id));
    }
}
