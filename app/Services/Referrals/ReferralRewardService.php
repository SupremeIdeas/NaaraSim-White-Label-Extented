<?php

namespace App\Services\Referrals;

use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Support\ReferralSettings;
use Illuminate\Support\Facades\DB;

/**
 * Referral-share-on-first-transaction (NAARA-BUILD-22 §1). When a referred user
 * completes their FIRST successful transaction, their referrer earns a share of
 * Naara's OWN margin on that order — real, withdrawable earnings booked to the
 * ReferralEarning ledger. Distinct from the NaaraCredit referral bonus.
 *
 * "First transaction, once ever" is enforced by the Referral.rewarded flag plus
 * the ledger's idempotent reference — so even if the trigger fires on several
 * orders (or the same order twice), the referrer is rewarded exactly once. The
 * reward_pct is locked into the Referral row when it is first created, so a later
 * global rate change never retroactively alters an already-rewarded referral.
 */
class ReferralRewardService
{
    public function __construct(private readonly ReferralEarningsService $earnings) {}

    /**
     * @param  string  $sourceType  'esim' | 'number'
     * @param  float   $orderProfit Naara's internal margin on this order (charged − cost)
     */
    public function rewardFirstTransaction(User $buyer, string $sourceType, float $orderProfit): ?ReferralEarning
    {
        if (empty($buyer->referred_by)) {
            return null; // not a referred user
        }

        $referrer = User::find($buyer->referred_by);
        if (! $referrer || $referrer->id === $buyer->id) {
            return null;
        }

        return DB::transaction(function () use ($buyer, $referrer, $sourceType, $orderProfit) {
            // Lock the referral row so a concurrent order can't reward twice.
            $referral = Referral::where('referrer_id', $referrer->id)
                ->where('referred_id', $buyer->id)
                ->where('type', Referral::TYPE_CUSTOMER)
                ->lockForUpdate()
                ->first();

            if (! $referral) {
                $referral = Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $buyer->id,
                    'type' => Referral::TYPE_CUSTOMER,
                    // Lock in the rate at creation time (never retroactive).
                    'reward_pct' => ReferralSettings::marginSharePct(),
                    'rewarded' => false,
                ]);
            }

            if ($referral->rewarded) {
                return null; // already rewarded on an earlier transaction
            }

            $reward = round(((float) $referral->reward_pct / 100) * max(0.0, $orderProfit), 4);
            if ($reward <= 0) {
                // A zero/negative-margin first order earns nothing — do NOT burn
                // the one-shot; the next profitable order will trigger it.
                return null;
            }

            $earning = $this->earnings->accrue(
                $referrer, $buyer, $sourceType, $reward,
                "referral_reward:{$referral->id}",
            );

            $referral->forceFill(['rewarded' => true, 'rewarded_at' => now()])->save();

            return $earning;
        });
    }
}
