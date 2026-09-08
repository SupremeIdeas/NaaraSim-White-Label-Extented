<?php

namespace App\Services\Merchants;

use App\Models\Merchant;
use App\Models\Referral;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\DB;

/**
 * Merchant-to-merchant sub-referral (BUILD-7 §4). A DELIBERATELY conservative,
 * single-hop, one-time flat bonus — NOT a recurring downline / percentage
 * override (which would take the regulatory shape of MLM). When someone a
 * merchant invited becomes a merchant too, the inviter earns a one-time admin-set
 * flat bonus, once, via CreditService (idempotent reference). Reuses the Referral
 * table with a type column; no second referrals system.
 */
class MerchantReferralService
{
    public function __construct(private readonly CreditService $credits)
    {
    }

    /**
     * Called when $newMerchant becomes an ACTIVE merchant. If they were referred
     * by another user who is themselves a merchant, and a bonus is configured,
     * pay the inviter once. Safe to call repeatedly — the idempotent reference and
     * the rewarded flag guarantee a single payout.
     */
    public function rewardReferrerIfEligible(User $newMerchant): void
    {
        $bonus = MerchantSettings::merchantReferralBonusUsd();
        if ($bonus <= 0 || empty($newMerchant->referred_by)) {
            return;
        }

        $referrer = User::find($newMerchant->referred_by);
        if (! $referrer || ! $this->isMerchant($referrer)) {
            return; // only merchants earn the merchant-referral bonus
        }

        DB::transaction(function () use ($referrer, $newMerchant, $bonus) {
            $referral = Referral::firstOrCreate(
                ['referrer_id' => $referrer->id, 'referred_id' => $newMerchant->id, 'type' => Referral::TYPE_MERCHANT],
                ['reward_pct' => 0, 'rewarded' => false],
            );
            if ($referral->rewarded) {
                return; // already paid
            }

            // Flat, credit-based, one-time. Idempotent reference double-guards it.
            $this->credits->earn(
                $referrer, $bonus, 'merchant_referral',
                "merchant_referral:{$referrer->id}:{$newMerchant->id}",
                'Merchant referral bonus',
            );

            $referral->forceFill(['rewarded' => true, 'rewarded_at' => now()])->save();
        });
    }

    private function isMerchant(User $user): bool
    {
        return Merchant::query()->where('owner_user_id', $user->id)
            ->where('status', Merchant::ACTIVE)->exists();
    }
}
