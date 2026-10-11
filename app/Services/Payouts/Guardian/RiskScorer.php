<?php

namespace App\Services\Payouts\Guardian;

use App\Models\CreditLedger;
use App\Models\KnownDevice;
use App\Models\MerchantEarning;
use App\Models\PartnerEarning;
use App\Models\PayoutRequest;
use App\Models\PayoutTrustProfile;
use App\Models\ReferralEarning;
use App\Models\StaffEarning;
use App\Support\PayoutSettings;

/**
 * The soft signals (Addendum C §4): each adds weighted points to a 0–100 risk score
 * and logs its evidence. A signal whose data does not exist for a bucket is skipped
 * and recorded as `unavailable` — never guessed.
 */
class RiskScorer
{
    /** @return list<GateResult> one result per signal that fired or was unavailable */
    public function signals(GuardianContext $ctx): array
    {
        $w = PayoutSettings::weights();
        $r = $ctx->request;
        $user = $ctx->user;
        $out = [];
        if ($user === null) {
            return $out;
        }

        if ($user->created_at !== null && $user->created_at->gt(now()->subDays(PayoutSettings::newAccountDays()))) {
            $out[] = GateResult::warn('S_new_account', ['age_days' => (int) $user->created_at->diffInDays(now())], $w['new_account']);
        }

        $paidBefore = PayoutRequest::where('user_id', $user->id)->where('id', '!=', $r->id)->where('status', PayoutRequest::PAID)->exists();
        if (! $paidBefore) {
            $out[] = GateResult::warn('S_first_withdrawal', [], $w['first_withdrawal']);
        }

        $out[] = $this->freshEarnings($ctx, $w['fresh_earnings']);
        $out[] = $this->receivedFunds($ctx, $w['peer_funds']);

        if ($r->source_bucket === 'referral_earnings') {
            $out[] = $this->referralConcentration($ctx, $w['referral_concentration']);
        }

        $recent = PayoutRequest::where('user_id', $user->id)->where('id', '!=', $r->id)->where('created_at', '>=', now()->subDay())->count();
        if ($recent >= 2) {
            $out[] = GateResult::warn('S_velocity', ['requests_24h' => $recent], $w['velocity']);
        }
        $failed = PayoutRequest::where('user_id', $user->id)->where('id', '!=', $r->id)
            ->whereIn('status', [PayoutRequest::FAILED, PayoutRequest::REVERSED])->where('created_at', '>=', now()->subDays(30))->count();
        if ($failed > 0) {
            $out[] = GateResult::warn('S_recent_failures', ['failed_30d' => $failed], $w['recent_failures']);
        }

        $profile = PayoutTrustProfile::find($user->id);
        $tier = $profile?->tier ?? 'new';
        if ($tier === 'trusted') {
            $out[] = GateResult::warn('S_trust_tier', ['tier' => $tier], $w['trusted_tier']);
        } elseif ($tier === 'vip') {
            $out[] = GateResult::warn('S_trust_tier', ['tier' => $tier], $w['vip_tier']);
        }
        if ($profile?->last_incident_at !== null && $profile->last_incident_at->gt(now()->subDays(60))) {
            $out[] = GateResult::warn('S_recent_incident', ['at' => $profile->last_incident_at->toIso8601String()], $w['recent_incident']);
        }

        return array_values(array_filter($out));
    }

    /** Earn-then-withdraw: how much of this payout is earnings younger than the maturity window. */
    private function freshEarnings(GuardianContext $ctx, int $weight): ?GateResult
    {
        $r = $ctx->request;
        $days = PayoutSettings::maturityDays();
        $since = now()->subDays($days);
        $held = (float) $r->credit_amount;
        if ($days === 0 || $held <= 0) {
            return null;
        }

        $young = match ($r->source_bucket) {
            'referral_credits' => (float) CreditLedger::where('user_id', $r->user_id)->where('type', 'earn')->where('withdrawable', true)->where('created_at', '>=', $since)->sum('amount'),
            'referral_earnings' => (float) ReferralEarning::where('user_id', $r->user_id)->where('type', ReferralEarning::ACCRUAL)->where('created_at', '>=', $since)->sum('amount'),
            'staff_earnings' => (float) StaffEarning::where('user_id', $r->user_id)->where('type', StaffEarning::ACCRUAL)->where('created_at', '>=', $since)->sum('amount'),
            'merchant_earnings' => (float) MerchantEarning::whereIn('merchant_id', \App\Models\Merchant::where('owner_user_id', $r->user_id)->pluck('id'))->where('type', MerchantEarning::ACCRUAL)->where('created_at', '>=', $since)->sum('amount'),
            'partner_earnings' => (float) PartnerEarning::whereIn('partner_id', \App\Models\Partner::where('owner_user_id', $r->user_id)->pluck('id'))->where('type', PartnerEarning::ACCRUAL)->where('created_at', '>=', $since)->sum('amount'),
            default => null,
        };
        if ($young === null) {
            return GateResult::warn('S_fresh_earnings', ['unavailable' => true], 0);
        }

        $share = min(1.0, $young / $held);

        return $share > 0.5 ? GateResult::warn('S_fresh_earnings', ['share' => round($share, 2), 'maturity_days' => $days], (int) round($weight * $share)) : null;
    }

    /** Mostly money another member sent: the classic way to move funds through someone, so a person looks first. */
    private function receivedFunds(GuardianContext $ctx, int $weight): ?GateResult
    {
        $r = $ctx->request;
        $held = (float) $r->credit_amount;
        if (! PayoutSettings::peerReviewReceived() || $r->source_bucket !== 'referral_earnings' || $held <= 0) {
            return null;
        }
        $received = (float) ReferralEarning::where('user_id', $r->user_id)->where('type', ReferralEarning::TRANSFER_IN)->where('created_at', '>=', now()->subDays(30))->sum('amount');
        $share = min(1.0, $received / $held);

        return $share > 0.5 ? GateResult::warn('S_peer_funds', ['share' => round($share, 2)], $weight) : null;
    }

    /** Earnings dominated by a couple of referred accounts, or referred accounts sharing the referrer's device/IP. */
    private function referralConcentration(GuardianContext $ctx, int $weight): ?GateResult
    {
        $rows = ReferralEarning::where('user_id', $ctx->request->user_id)->where('type', ReferralEarning::ACCRUAL)
            ->whereNotNull('source_user_id')->get(['source_user_id', 'amount']);
        if ($rows->count() < 3) {
            return null;
        }

        $total = (float) $rows->sum('amount');
        $top = (float) $rows->groupBy('source_user_id')->map(fn ($g) => (float) $g->sum('amount'))->max();
        $share = $total > 0 ? $top / $total : 0.0;

        $referrerDevices = KnownDevice::where('user_id', $ctx->request->user_id);
        $fp = (clone $referrerDevices)->pluck('fingerprint')->filter()->all();
        $ips = (clone $referrerDevices)->pluck('ip_address')->filter()->all();
        $sharedDevice = ($fp !== [] || $ips !== []) && KnownDevice::whereIn('user_id', $rows->pluck('source_user_id')->unique())
            ->where(fn ($q) => $q->whereIn('fingerprint', $fp ?: [''])->orWhereIn('ip_address', $ips ?: ['']))->exists();

        if ($share > 0.7 || $sharedDevice) {
            return GateResult::warn('S_referral_concentration', ['top_share' => round($share, 2), 'shared_device_or_ip' => $sharedDevice], $weight);
        }

        return null;
    }
}
