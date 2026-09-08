<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Services\Partners\PartnerEarningsService;

/**
 * When a partner payout fails or is reversed by the PSP, return the exact
 * earnings that were held. Idempotent on the payout reference, so a repeated
 * reversal never double-credits the partner bucket.
 */
class ReturnPartnerEarnings
{
    public function __construct(private PartnerEarningsService $earnings)
    {
    }

    public function handle(PayoutReversed $event): void
    {
        $request = $event->request;

        if ($request->source_bucket !== 'partner_earnings' || ! $request->credit_amount) {
            return; // not a partner-earnings payout
        }

        $partner = $request->user?->partnerAccount;
        if ($partner === null) {
            return;
        }

        $this->earnings->release(
            $partner,
            (float) $request->credit_amount,
            'earn-release:'.$request->reference,
            'Payout returned',
        );
    }
}
