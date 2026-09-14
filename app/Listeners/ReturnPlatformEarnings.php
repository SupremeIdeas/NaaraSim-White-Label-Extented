<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Services\Platform\PlatformEarningsService;

/**
 * Prompt 21-EXT §5.4 — when a platform-earnings withdrawal fails or is
 * reversed by the PSP, return the exact amount that was held back into the
 * single global bucket. Mirrors ReturnMerchantEarnings exactly; idempotent
 * on the payout reference, so a repeated reversal never double-credits.
 */
class ReturnPlatformEarnings
{
    public function __construct(private PlatformEarningsService $earnings) {}

    public function handle(PayoutReversed $event): void
    {
        $request = $event->request;

        if ($request->source_bucket !== 'platform_earnings' || ! $request->credit_amount) {
            return; // not a platform-earnings withdrawal
        }

        $this->earnings->release(
            (float) $request->credit_amount,
            'plat-release:'.$request->reference,
            'Withdrawal returned',
        );
    }
}
