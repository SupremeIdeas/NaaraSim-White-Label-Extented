<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Referrals\ReferralRewardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Books the referral margin-share reward off the money path (NAARA-BUILD-22 §1).
 * Kept out of the checkout request cycle; fully idempotent (Referral.rewarded +
 * the ledger's unique reference), so a retry can never double-pay. A missing
 * user or non-referred buyer is a silent no-op.
 */
class ProcessReferralRewardJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $buyerId,
        public string $sourceType,
        public float $orderProfit,
    ) {}

    public function handle(ReferralRewardService $rewards): void
    {
        $buyer = User::find($this->buyerId);
        if (! $buyer) {
            return;
        }

        $rewards->rewardFirstTransaction($buyer, $this->sourceType, $this->orderProfit);
    }
}
