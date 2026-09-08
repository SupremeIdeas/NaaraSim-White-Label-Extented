<?php

namespace App\Livewire;

use App\Models\PayoutAccount;
use App\Services\Credits\CreditService;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Payouts\StripeConnectService;
use App\Services\Payouts\WithdrawalService;
use App\Support\CreditSettings;
use App\Support\PayoutSettings;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer cash-out (ROADMAP §Layer 1). Manage bank/payout accounts (name
 * resolved before saving) and withdraw withdrawable NaaraCredits to one.
 * Browsing and payout-account setup are free (NAARA-BUILD-22 §3) — KYC-L2 is
 * only required once the unified free-payout threshold is spent, exactly like
 * every other earner type's payout flow.
 */
#[Layout('components.layouts.customer')]
class Withdraw extends Component
{
    // Add-account form.
    public string $accountType = 'bank';

    public string $country = 'NG';

    public string $bankCode = '';

    public string $accountNumber = '';

    /** @var list<array{code: string, name: string}> */
    public array $banks = [];

    public ?string $accountError = null;

    // PayPal add-account form — double-entry re-confirmation (no PSP resolve
    // API exists for a payout email, so this re-typed match is the guard
    // against a mistyped destination).
    public string $paypalEmail = '';

    public string $paypalEmailConfirm = '';

    // Withdraw form.
    public ?int $accountId = null;

    public $amountUsd = '';

    public ?string $withdrawError = null;

    public function mount(StripeConnectService $connect): void
    {
        $this->loadBanks();
        $this->accountId = PayoutAccount::where('user_id', Auth::id())->where('is_default', true)->value('id');

        // Landed back from Stripe's hosted onboarding: force-refresh status now
        // rather than waiting on the account.updated webhook, so the page
        // reflects reality immediately instead of looking stuck.
        $pending = PayoutAccount::where('user_id', Auth::id())->where('type', 'stripe')
            ->where('payouts_enabled', false)->first();
        if ($pending !== null && $connect->available()) {
            try {
                $connect->refreshStatus($pending);
            } catch (\Throwable) {
                // Best-effort — the webhook is still the source of truth.
            }
        }
    }

    public function updatedCountry(): void
    {
        $this->bankCode = '';
        $this->loadBanks();
    }

    private function loadBanks(): void
    {
        $this->banks = app(PayoutAccountService::class)->banksFor(strtoupper($this->country))['banks'];
    }

    public function addAccount(PayoutAccountService $accounts): void
    {
        $this->accountError = null;
        $this->validate([
            'country' => 'required|string|size:2',
            'bankCode' => 'required|string',
            'accountNumber' => 'required|string|max:32',
        ]);

        try {
            $account = $accounts->addAccount(Auth::user(), [
                'country' => $this->country,
                'currency' => $this->currencyFor($this->country),
                'bank_code' => $this->bankCode,
                'bank_name' => collect($this->banks)->firstWhere('code', $this->bankCode)['name'] ?? null,
                'account_number' => $this->accountNumber,
            ]);
        } catch (AccountResolutionException $e) {
            $this->accountError = $e->getMessage();

            return;
        }

        $this->reset('accountNumber');
        $this->accountId = $account->id;
        $this->dispatch('nx-toast', type: 'success', message: 'Account verified as '.$account->account_name.'.');
    }

    public function addPaypalAccount(PayoutAccountService $accounts): void
    {
        $this->accountError = null;
        $this->validate([
            'paypalEmail' => 'required|email|max:190',
            'paypalEmailConfirm' => 'required|email|max:190',
        ]);

        if (strtolower($this->paypalEmail) !== strtolower($this->paypalEmailConfirm)) {
            $this->accountError = 'Those two PayPal emails don\'t match. Please retype them.';

            return;
        }

        $account = $accounts->addPaypalAccount(Auth::user(), $this->paypalEmail);

        $this->reset('paypalEmail', 'paypalEmailConfirm');
        $this->accountId = $account->id;
        $this->dispatch('nx-toast', type: 'success', message: 'PayPal account added: '.$account->account_name);
    }

    /** Start (or resume) Stripe's hosted onboarding for a Connect account. */
    public function connectStripe(StripeConnectService $connect)
    {
        $account = $connect->accountFor(Auth::user());

        $url = $connect->onboardingUrl(
            $account,
            refreshUrl: route('rewards.withdraw'),
            returnUrl: route('rewards.withdraw'),
        );

        return redirect()->away($url);
    }

    public function setDefault(int $id, PayoutAccountService $accounts): void
    {
        $accounts->setDefault(Auth::user(), PayoutAccount::findOrFail($id));
        $this->accountId = $id;
    }

    public function removeAccount(int $id, PayoutAccountService $accounts): void
    {
        $accounts->remove(Auth::user(), PayoutAccount::findOrFail($id));
        if ($this->accountId === $id) {
            $this->accountId = null;
        }
    }

    public function withdraw(WithdrawalService $withdrawals): void
    {
        $this->withdrawError = null;
        $this->validate([
            'accountId' => 'required|integer',
            'amountUsd' => 'required|numeric|min:0.01',
        ]);

        $account = PayoutAccount::where('user_id', Auth::id())->find($this->accountId);
        if ($account === null) {
            $this->withdrawError = 'Choose a payout account.';

            return;
        }

        try {
            $credits = CreditSettings::usdToCredits((float) $this->amountUsd);
            $withdrawals->request(Auth::user(), $credits, $account);
        } catch (PayoutException $e) {
            $this->withdrawError = $e->getMessage();

            return;
        }

        $this->reset('amountUsd');
        $this->dispatch('nx-toast', type: 'success', message: 'Withdrawal requested — we’ll process it shortly.');
    }

    private function currencyFor(string $country): string
    {
        return ['NG' => 'NGN', 'GH' => 'GHS', 'KE' => 'KES', 'ZA' => 'ZAR'][strtoupper($country)] ?? 'USD';
    }

    public function render(WithdrawalService $withdrawals, CreditService $credits, PayoutService $payouts, PayoutThreshold $threshold)
    {
        $user = Auth::user();

        return view('livewire.withdraw', [
            'accounts' => PayoutAccount::where('user_id', $user->id)->latest()->get(),
            'availableUsd' => $withdrawals->availableUsd($user),
            'withdrawableCredits' => $credits->withdrawableBalance($user),
            'minWithdrawal' => PayoutSettings::minWithdrawal(),
            'enabled' => PayoutSettings::enabled(),
            // §8: the volume-recommended payout rail (or null). Other rails still show.
            'recommendedGateway' => $payouts->recommendedGateway(),
            'paypalAvailable' => ProviderStatus::isActive('paypal'),
            'stripeAvailable' => ProviderStatus::isActive('stripe'),
            'stripeAccount' => PayoutAccount::where('user_id', $user->id)->where('type', 'stripe')->first(),
            // Free-payout / KYC threshold state (§3) — same positive framing as
            // the shared PayoutDashboard for partner/merchant/referral earners.
            'remainingFree' => $threshold->remainingFree($user),
            'requiresKyc' => $threshold->requiresKyc($user),
            'canWithdraw' => $threshold->canWithdraw($user),
            'freeCount' => PayoutSettings::freePayoutCount(),
        ]);
    }
}
