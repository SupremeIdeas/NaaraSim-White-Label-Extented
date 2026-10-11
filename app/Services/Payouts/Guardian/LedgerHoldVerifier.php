<?php

namespace App\Services\Payouts\Guardian;

use App\Models\CreditLedger;
use App\Models\MerchantEarning;
use App\Models\PartnerEarning;
use App\Models\PayoutRequest;
use App\Models\ReferralEarning;
use App\Models\StaffEarning;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-bucket hold verification. For each bucket it finds the ONE hold row the
 * withdrawal service wrote for this request's reference, and checks: it exists,
 * the amount equals what the request says it holds, it was not released/refunded,
 * and the ledger balance is not negative.
 */
class LedgerHoldVerifier implements HoldVerifier
{
    private const TOLERANCE = 0.011;

    public function verify(PayoutRequest $request): HoldCheck
    {
        $ref = $request->reference;
        $held = (float) $request->credit_amount;
        if ($held <= 0) {
            return new HoldCheck(HoldCheck::MISSING, ['reason' => 'request records no held amount']);
        }

        return match ($request->source_bucket) {
            'referral_credits' => $this->credits($request, $held),
            'referral_earnings' => $this->earnings(ReferralEarning::class, 'user_id', $request->user_id, 'earn-hold:'.$ref, 'earn-release:'.$ref, $held),
            'staff_earnings' => $this->earnings(StaffEarning::class, 'user_id', $request->user_id, 'earn-hold:'.$ref, 'earn-release:'.$ref, $held),
            'merchant_earnings' => $this->earnings(MerchantEarning::class, 'merchant_id', $this->merchantId($request), 'earn-hold:'.$ref, 'earn-release:'.$ref, $held),
            'partner_earnings' => $this->earnings(PartnerEarning::class, 'partner_id', $this->partnerId($request), 'earn-hold:'.$ref, 'earn-release:'.$ref, $held),
            default => new HoldCheck(HoldCheck::UNVERIFIABLE, ['bucket' => $request->source_bucket]),
        };
    }

    private function credits(PayoutRequest $request, float $held): HoldCheck
    {
        $hold = CreditLedger::where('reference', 'wd-hold:'.$request->reference)->where('user_id', $request->user_id)->first();
        if ($hold === null || $hold->type !== 'spend') {
            return new HoldCheck(HoldCheck::MISSING, ['reference' => 'wd-hold:'.$request->reference]);
        }
        if (abs((float) $hold->amount - $held) > self::TOLERANCE) {
            return new HoldCheck(HoldCheck::MISMATCH, ['held' => (float) $hold->amount, 'request' => $held]);
        }
        if (CreditLedger::where('reference', 'wd-refund:'.$request->reference)->exists()) {
            return new HoldCheck(HoldCheck::RELEASED, ['reference' => 'wd-refund:'.$request->reference]);
        }
        $balance = (float) (CreditLedger::where('user_id', $request->user_id)->latest('id')->value('balance_after') ?? 0);

        return $balance < 0 ? new HoldCheck(HoldCheck::NEGATIVE, ['balance' => $balance]) : new HoldCheck(HoldCheck::OK, ['held_credits' => $held]);
    }

    /** @param  class-string<Model>  $model */
    private function earnings(string $model, ?string $ownerColumn, ?int $ownerId, string $holdRef, string $releaseRef, float $held): HoldCheck
    {
        $scope = fn () => $model::query()->when($ownerColumn, fn ($q) => $q->where($ownerColumn, $ownerId));

        if ($ownerColumn !== null && $ownerId === null) {
            return new HoldCheck(HoldCheck::MISSING, ['reason' => 'ledger owner could not be resolved']);
        }

        $hold = $scope()->where('reference', $holdRef)->first();
        if ($hold === null || $hold->type !== 'hold') {
            return new HoldCheck(HoldCheck::MISSING, ['reference' => $holdRef]);
        }
        // Hold rows are negative movements; compare magnitudes.
        if (abs(abs((float) $hold->amount) - $held) > self::TOLERANCE) {
            return new HoldCheck(HoldCheck::MISMATCH, ['held' => abs((float) $hold->amount), 'request' => $held]);
        }
        if ($scope()->where('reference', $releaseRef)->exists()) {
            return new HoldCheck(HoldCheck::RELEASED, ['reference' => $releaseRef]);
        }
        $balance = (float) ($scope()->latest('id')->value('balance_after') ?? 0);

        return $balance < 0 ? new HoldCheck(HoldCheck::NEGATIVE, ['balance' => $balance]) : new HoldCheck(HoldCheck::OK, ['held_usd' => $held]);
    }

    private function merchantId(PayoutRequest $request): ?int
    {
        return \App\Models\Merchant::where('owner_user_id', $request->user_id)->value('id');
    }

    private function partnerId(PayoutRequest $request): ?int
    {
        return \App\Models\Partner::where('owner_user_id', $request->user_id)->value('id');
    }
}
