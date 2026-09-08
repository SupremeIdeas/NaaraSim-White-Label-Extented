<?php

namespace App\Livewire;

use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\PayoutAccount;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Merchants\MerchantWithdrawalService;
use App\Services\Payouts\PayoutException;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Merchant storefront dashboard (ROADMAP §Layer 3.5). One place for a merchant
 * to run their reseller business: co-brand their storefront, share their invite
 * link, watch customers + earnings, and cash out through the payout engine. Only
 * an ACTIVE merchant reaches it (404 otherwise); the reseller margin is admin-
 * owned and never editable here — a merchant styles their brand, not their price.
 */
#[Layout('components.layouts.customer')]
class MerchantDashboard extends Component
{
    use WithFileUploads;

    public Merchant $merchant;

    // Storefront (branding only — never pricing).
    public string $businessName = '';

    public string $brandColor = '';

    public $logo = null;

    // Withdraw.
    public ?int $accountId = null;

    public $amountUsd = '';

    public ?string $withdrawError = null;

    public function upgradeToV2(\App\Services\Merchants\MerchantUpgradeService $upgrades): void
    {
        try {
            $upgrades->selfUpgrade($this->merchant);
        } catch (\App\Services\Merchants\MerchantException $e) {
            $this->dispatch('nx-toast', variant: 'hero', type: 'error', title: 'Upgrade not completed', message: $e->getMessage(),
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

            return;
        }
        $this->merchant = $this->merchant->fresh();
        $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'You’re now Merchant V2',
            message: 'Client management and the developer portal are unlocked.',
            cta: ['label' => 'Manage clients', 'href' => route('merchant.clients')]);
    }

    public function mount(): void
    {
        $merchant = Auth::user()->merchantAccount;
        abort_if($merchant === null || ! $merchant->isActive(), 404);

        $this->merchant = $merchant;
        $this->businessName = $merchant->business_name;
        $this->brandColor = $merchant->brand_color ?: '#0A6E6E';
        $this->accountId = PayoutAccount::where('user_id', Auth::id())->where('is_verified', true)
            ->where('is_default', true)->value('id');
    }

    public function saveStorefront(): void
    {
        $this->validate([
            'businessName' => ['required', 'string', 'max:120'],
            'brandColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ], ['brandColor.regex' => 'Use a hex colour like #0A6E6E.']);

        $data = ['business_name' => $this->businessName, 'brand_color' => $this->brandColor];
        if ($this->logo) {
            $data['logo_url'] = MediaStorage::storePublic($this->logo, 'merchant-logos');
        }
        $this->merchant->update($data);
        $this->reset('logo');

        Auditor::log('merchant.storefront_updated', 'Merchant', $this->merchant->id);
        $this->dispatch('nx-toast', type: 'success', message: 'Storefront updated.');
    }

    public function withdraw(MerchantWithdrawalService $withdrawals): void
    {
        $this->withdrawError = null;
        $this->validate([
            'accountId' => ['required', 'integer'],
            'amountUsd' => ['required', 'numeric', 'min:0.01'],
        ]);

        $account = PayoutAccount::where('user_id', Auth::id())->find($this->accountId);
        if ($account === null) {
            $this->withdrawError = 'Choose a verified payout account.';

            return;
        }

        try {
            $withdrawals->request($this->merchant, $account, (float) $this->amountUsd);
        } catch (PayoutException $e) {
            $this->withdrawError = $e->getMessage();

            return;
        }

        $this->reset('amountUsd');
        $this->dispatch('nx-toast', type: 'success', message: 'Withdrawal requested — we’ll process it shortly.');
    }

    public function render(MerchantEarningsService $earnings)
    {
        $customers = $this->merchant->customers()->latest('id')->limit(10)->get();
        $ledger = MerchantEarning::where('merchant_id', $this->merchant->id)
            ->latest('id')->limit(12)->get();
        $lifetime = (float) MerchantEarning::where('merchant_id', $this->merchant->id)
            ->where('type', MerchantEarning::ACCRUAL)->sum('amount');

        return view('livewire.merchant-dashboard', [
            'accounts' => PayoutAccount::where('user_id', Auth::id())->where('is_verified', true)->get(),
            'customerCount' => $this->merchant->customers()->count(),
            'customers' => $customers,
            'ledger' => $ledger,
            'balance' => $earnings->balance($this->merchant),
            'lifetime' => round($lifetime, 2),
            'payoutsEnabled' => PayoutSettings::enabled(),
            'inviteUrl' => route('merchant.join', ['slug' => $this->merchant->slug]),
        ]);
    }
}
