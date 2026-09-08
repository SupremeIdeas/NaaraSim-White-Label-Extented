<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * A gateway that emits dispute/chargeback webhooks (BUILD-2 §7.2). Called only
 * after the webhook signature has been verified. Returns null when the request
 * is not a dispute event (so the normal payment path handles it).
 */
interface DisputeAwareGateway
{
    public function parseDispute(Request $request): ?DisputeEvent;
}
