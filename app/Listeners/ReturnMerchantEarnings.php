<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Services\Merchants\MerchantEarningsService;

/**
 * When a merchant-earnings withdrawal fails or is reversed by the PSP, return the
 * exact earnings that were held (ROADMAP §Layer 3.4). Idempotent on the payout
 * reference, so a repeated reversal never double-credits the merchant bucket.
 */
class ReturnMerchantEarnings
{
    public function __construct(private MerchantEarningsService $earnings)
    {
    }

    public function handle(PayoutReversed $event): void
    {
        $request = $event->request;

        if ($request->source_bucket !== 'merchant_earnings' || ! $request->credit_amount) {
            return; // not a merchant-earnings withdrawal
        }

        $merchant = $request->user?->merchantAccount;
        if ($merchant === null) {
            return;
        }

        $this->earnings->release(
            $merchant,
            (float) $request->credit_amount,
            'earn-release:'.$request->reference,
            'Withdrawal returned',
        );
    }
}
