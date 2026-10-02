<?php

namespace App\Services\Payouts\Hardening;

use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Payouts\PayoutException;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\Auditor;

/**
 * Clawback of earnings after a refund or a lost chargeback (owner-approved policy, 2026-10-02):
 *
 *  - It is ALWAYS a person's decision. Disputes are never automated on this platform (see DisputeService), so nothing
 *    here is called from a webhook — an admin finds the sale, reads the amounts and confirms with a reason.
 *  - The earnings that sale produced (the merchant's reseller margin, the referrer's margin share) are reversed in
 *    full, once (idempotent on `clawback:{original reference}`).
 *  - The earner's balance MAY go below zero: that is a debt. It is repaid automatically by their next earnings, they
 *    see an "adjustment" with the reason, no cash is ever recovered from them, and while it exists they cannot
 *    withdraw (a withdrawal hold would overdraw).
 */
class EarningsClawback
{
    public function __construct(
        private readonly MerchantEarningsService $merchants,
        private readonly ReferralEarningsService $referrals,
    ) {}

    /**
     * Earnings created by a buyer's purchases (by buyer email) or by one order reference.
     *
     * @return list<array{kind: string, id: int, owner: string, owner_email: ?string, amount: float, reference: string, source: ?string, at: string, reversed: bool}>
     */
    public function candidates(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $buyer = User::where('email', $query)->first();
        $out = [];

        $merchant = MerchantEarning::query()->where('type', MerchantEarning::ACCRUAL)
            ->when($buyer, fn ($q) => $q->where('source_user_id', $buyer->id), fn ($q) => $q->where('reference', 'like', '%'.$query.'%'))
            ->latest('id')->limit(25)->get();
        foreach ($merchant as $e) {
            $m = Merchant::find($e->merchant_id);
            $out[] = $this->row('merchant', $e->id, $m ? 'Merchant #'.$m->id : 'Merchant', $m?->owner?->email, (float) $e->amount, $e->reference, $e->source_type, $e->created_at,
                MerchantEarning::where('reference', 'clawback:'.$e->reference)->exists());
        }

        $referral = ReferralEarning::query()->where('type', ReferralEarning::ACCRUAL)
            ->when($buyer, fn ($q) => $q->where('source_user_id', $buyer->id), fn ($q) => $q->where('reference', 'like', '%'.$query.'%'))
            ->latest('id')->limit(25)->get();
        foreach ($referral as $e) {
            $o = User::find($e->user_id);
            $out[] = $this->row('referral', $e->id, 'Referrer #'.$e->user_id, $o?->email, (float) $e->amount, $e->reference, $e->source_type, $e->created_at,
                ReferralEarning::where('reference', 'clawback:'.$e->reference)->exists());
        }

        return $out;
    }

    /** @throws PayoutException */
    public function reverse(string $kind, int $earningId, User $admin, string $reason): float
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']) || $admin->can('payouts.finance'), 403);
        if (mb_strlen(trim($reason)) < 5) {
            throw new PayoutException('Give a short reason (it is shown to the earner as the adjustment note).');
        }

        if ($kind === 'merchant') {
            $e = MerchantEarning::where('type', MerchantEarning::ACCRUAL)->findOrFail($earningId);
            $merchant = Merchant::findOrFail($e->merchant_id);
            $this->merchants->clawback($merchant, (float) $e->amount, 'clawback:'.$e->reference, $reason);
        } elseif ($kind === 'referral') {
            $e = ReferralEarning::where('type', ReferralEarning::ACCRUAL)->findOrFail($earningId);
            $this->referrals->clawback(User::findOrFail($e->user_id), (float) $e->amount, 'clawback:'.$e->reference, $reason);
        } else {
            throw new PayoutException('Unknown earnings type.');
        }

        Auditor::log('earnings.clawback', $kind === 'merchant' ? MerchantEarning::class : ReferralEarning::class, $e->id, ['by' => $admin->id, 'reason' => $reason, 'amount' => (float) $e->amount]);

        return (float) $e->amount;
    }

    private function row(string $kind, int $id, string $owner, ?string $email, float $amount, string $ref, ?string $source, $at, bool $reversed): array
    {
        return ['kind' => $kind, 'id' => $id, 'owner' => $owner, 'owner_email' => $email, 'amount' => round($amount, 4), 'reference' => $ref, 'source' => $source, 'at' => $at?->toDateTimeString() ?? '', 'reversed' => $reversed];
    }
}
