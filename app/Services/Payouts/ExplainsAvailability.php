<?php

namespace App\Services\Payouts;

/**
 * Optional: a gateway that knows WHY `available()` is false says so, so Payout Health can show the real reason and the one-click fix
 * instead of a bare "not configured". Return one of the GatewayStatus::R_* reason codes, or null when it is available.
 */
interface ExplainsAvailability
{
    public function unavailableReason(): ?string;
}
