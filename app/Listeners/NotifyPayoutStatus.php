<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Notifications\PayoutStatusNotification;

/** Delivered / returned → tell the payee (once per event; the notification reads live status text). */
class NotifyPayoutStatus
{
    public function handle(PayoutSettled|PayoutReversed $event): void
    {
        $request = $event->request;
        if ($request->user !== null) {
            $request->user->notify(new PayoutStatusNotification($request->id));
        }
    }
}
