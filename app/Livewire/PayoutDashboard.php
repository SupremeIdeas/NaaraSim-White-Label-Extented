<?php

namespace App\Livewire;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Partners\PartnerEarningsService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One shared payout dashboard for every earner type (NAARA-BUILD-22 §4). Embedded
 * by the partner / merchant / referral surfaces via
 *   <livewire:payout-dashboard earner-type="referral" />
 * — same balance, same history, same free-payout/KYC-threshold prompt, never three
 * divergent pages. Payouts run automatically (§6), so this is a status surface: it
 * shows what's owed, what's already been paid, and — only once the free threshold
 * is spent — a positive-framed prompt to verify identity.
 *
 * Visible with ZERO verification (§3.4): browsing the balance never triggers KYC.
 */
class PayoutDashboard extends Component
{
    public string $earnerType = 'referral'; // partner | merchant | referral

    public function mount(?string $earnerType = null): void
    {
        $type = $earnerType ?? $this->earnerType;
        abort_unless(in_array($type, ['partner', 'merchant', 'referral', 'staff'], true), 404);
        $this->earnerType = $type;
    }

    /** Staff are exempt from the free-payout / KYC threshold (BUILD-23 §4). */
    private function isExempt(): bool
    {
        return $this->earnerType === 'staff';
    }

    private function balance(): float
    {
        $user = Auth::user();

        return match ($this->earnerType) {
            'merchant' => ($m = $user->merchantAccount) ? app(MerchantEarningsService::class)->balance($m) : 0.0,
            'partner' => ($p = $user->partnerAccount) ? app(PartnerEarningsService::class)->balance($p) : 0.0,
            'staff' => app(\App\Services\Staff\StaffEarningsService::class)->balance($user),
            default => app(ReferralEarningsService::class)->balance($user),
        };
    }

    /** Recent ledger rows for the earner's own history table (never exposes cost). */
    private function history()
    {
        $user = Auth::user();

        return match ($this->earnerType) {
            'merchant' => ($m = $user->merchantAccount)
                ? \App\Models\MerchantEarning::where('merchant_id', $m->id)->latest('id')->limit(10)->get()
                : collect(),
            'partner' => ($p = $user->partnerAccount)
                ? $p->earnings()->latest('id')->limit(10)->get()
                : collect(),
            'staff' => \App\Models\StaffEarning::where('user_id', $user->id)->latest('id')->limit(10)->get(),
            default => \App\Models\ReferralEarning::where('user_id', $user->id)->latest('id')->limit(10)->get(),
        };
    }

    public function render()
    {
        $user = Auth::user();
        $threshold = app(PayoutThreshold::class);

        $hasVerifiedAccount = PayoutAccount::where('user_id', $user->id)->where('is_verified', true)->exists();
        $exempt = $this->isExempt();

        return view('livewire.payout-dashboard', [
            'balance' => round($this->balance(), 2),
            'history' => $this->history(),
            'payouts' => PayoutRequest::where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'enabled' => PayoutSettings::enabled(),
            'exempt' => $exempt,
            'remainingFree' => $threshold->remainingFree($user),
            // Staff bypass the threshold entirely (BUILD-23 §4).
            'requiresKyc' => ! $exempt && $threshold->requiresKyc($user),
            'canWithdraw' => $exempt || $threshold->canWithdraw($user),
            'hasVerifiedAccount' => $hasVerifiedAccount,
            'freeCount' => PayoutSettings::freePayoutCount(),
        ]);
    }
}
