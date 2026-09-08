<?php

namespace App\Livewire\Admin;

use App\Models\Merchant;
use App\Models\Setting;
use App\Services\Merchants\MerchantService;
use App\Support\Auditor;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Merchants (ROADMAP §Layer 3.6). Toggle the programme, set the global
 * reseller margin (MarginGuard still floors every resulting price), and
 * approve / reject / suspend merchants. The margin is admin-owned — merchants
 * never price their own products.
 */
#[Layout('components.layouts.admin')]
class Merchants extends Component
{
    public bool $enabled = false;

    public $resellerMargin = 10;

    public $minSpend = 75;

    public $enrollmentFee = 50;

    public $minReferrals = 1000;

    public $upgradePrice = 125;

    // One-time flat merchant-to-merchant referral bonus (BUILD-7 §4) — 0 = off.
    public $merchantReferralBonus = 0;

    // Deferred-verification payout rule (BUILD-4 §1.3) — mechanism ships off.
    public $kybThreshold = 500;

    public bool $kybOverThreshold = false;

    // Auto-promote eligible users to V1 (BUILD-4 §4.3) — off by default.
    public bool $autoPromote = false;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->enabled = MerchantSettings::enabled();
        $this->resellerMargin = MerchantSettings::resellerMarginPct();
        $this->minSpend = MerchantSettings::minSpendUsd();
        $this->enrollmentFee = MerchantSettings::enrollmentFeeUsd();
        $this->minReferrals = MerchantSettings::minReferrals();
        $this->upgradePrice = MerchantSettings::upgradePriceUsd();
        $this->merchantReferralBonus = MerchantSettings::merchantReferralBonusUsd();
        $this->kybThreshold = MerchantSettings::kybThresholdUsd();
        $this->kybOverThreshold = MerchantSettings::kybOverThresholdEnabled();
        $this->autoPromote = MerchantSettings::autoPromoteEnabled();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'resellerMargin' => 'required|numeric|min:0|max:500',
            'minSpend' => 'required|numeric|min:0|max:1000000',
            'enrollmentFee' => 'required|numeric|min:0|max:1000000',
            'minReferrals' => 'required|integer|min:0|max:10000000',
            'upgradePrice' => 'required|numeric|min:0|max:100000',
            'kybThreshold' => 'required|numeric|min:0|max:10000000',
        ]);

        Setting::setValue(MerchantSettings::FLAG, $this->enabled, 'merchants');
        Setting::setValue(MerchantSettings::MARGIN, (float) $this->resellerMargin, 'merchants');
        Setting::setValue(MerchantSettings::MIN_SPEND, (float) $this->minSpend, 'merchants');
        Setting::setValue(MerchantSettings::ENROLLMENT_FEE, (float) $this->enrollmentFee, 'merchants');
        Setting::setValue(MerchantSettings::MIN_REFERRALS, (int) $this->minReferrals, 'merchants');
        Setting::setValue(MerchantSettings::UPGRADE_PRICE, (float) $this->upgradePrice, 'merchants');
        Setting::setValue(MerchantSettings::MERCHANT_REFERRAL_BONUS, max(0, (float) $this->merchantReferralBonus), 'merchants');
        Setting::setValue(MerchantSettings::KYB_THRESHOLD, (float) $this->kybThreshold, 'merchants');
        Setting::setValue(MerchantSettings::KYB_OVER_THRESHOLD, $this->kybOverThreshold, 'merchants');
        Setting::setValue(MerchantSettings::AUTO_PROMOTE, $this->autoPromote, 'merchants');
        Auditor::log('merchants.settings_updated', null, null, [
            'enabled' => $this->enabled, 'margin' => $this->resellerMargin,
            'min_spend' => $this->minSpend, 'enrollment_fee' => $this->enrollmentFee, 'min_referrals' => $this->minReferrals,
        ]);

        $this->saved = 'Merchant settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Merchant settings saved.');
    }

    public function approve(int $id, MerchantService $merchants): void
    {
        $merchants->approve(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Merchant approved.');
    }

    public function reject(int $id, MerchantService $merchants): void
    {
        $merchants->reject(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Application rejected.');
    }

    public function suspend(int $id, MerchantService $merchants): void
    {
        $merchants->suspend(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Merchant suspended.');
    }

    public function upgrade(int $id, \App\Services\Merchants\MerchantUpgradeService $upgrades): void
    {
        $upgrades->grant(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Upgraded to V2.');
    }

    public function downgrade(int $id, \App\Services\Merchants\MerchantUpgradeService $upgrades): void
    {
        $upgrades->downgrade(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Returned to standard tier.');
    }

    public function render()
    {
        return view('livewire.admin.merchants', [
            'pending' => Merchant::with('owner:id,name,email')->where('status', Merchant::PENDING)->latest()->get(),
            'active' => Merchant::with('owner:id,name,email')->withCount('customers')
                ->whereIn('status', [Merchant::ACTIVE, Merchant::SUSPENDED])->latest()->get(),
        ]);
    }
}
