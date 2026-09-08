<?php

namespace App\Livewire;

use App\Models\KycVerification;
use App\Models\Merchant;
use App\Services\Kyc\KycService;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantService;
use App\Support\BusinessRegistration;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer path to becoming a merchant (ROADMAP §Layer 3.1; BUILD-4 §1). Two
 * stages: (1) unlock membership (spend / enrollment / referrals), then (2) the
 * merchant application. Identity verification (KYB) is NO LONGER a front gate —
 * it's deferred to payout time — so a user can set up a storefront and start
 * earning first. Admin approval activates the storefront and grants the role.
 */
#[Layout('components.layouts.customer')]
class BecomeMerchant extends Component
{
    // Optional business-verification (KYB) form — data-driven, global (§2.1).
    public string $country = 'NG';

    public string $regType = 'CAC';

    public string $regNumber = '';

    // Application form.
    public string $businessName = '';

    public string $brandColor = '#0A6E6E';

    public ?string $error = null;

    public function mount(): void
    {
        // Default the registration type to the selected country's first option.
        $this->regType = BusinessRegistration::typesFor($this->country)[0]['code'];
    }

    /** When the country changes, reset the reg-type to that country's first
     *  option so the two selects never get out of sync (§2.1). */
    public function updatedCountry(string $value): void
    {
        $this->regType = BusinessRegistration::typesFor($value)[0]['code'];
    }

    public function submitKyb(KycService $kyc): void
    {
        $this->validate([
            'country' => 'required|string|size:2',
            'regType' => 'required|string|max:40',
            'regNumber' => 'required|string|max:64',
        ]);
        // The reg-type must be one this country actually uses (defensive; the
        // select is already scoped, but never trust the client).
        if (! BusinessRegistration::isValidType($this->country, $this->regType)) {
            $this->addError('regType', 'Choose a registration type for the selected country.');

            return;
        }

        $kyc->submit(Auth::user(), KycVerification::L3, [
            'country' => strtoupper($this->country),
            'id_type' => strtoupper($this->regType),
            'id_number' => $this->regNumber,
        ]);

        $this->reset('regNumber');
        $this->dispatch('nx-toast', type: 'success', message: 'Business details submitted for verification.');
    }

    public function payEnrollment(MerchantService $merchants): void
    {
        $this->error = null;
        try {
            $merchants->payEnrollment(Auth::user());
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Fast-route enrollment paid — you can apply now.');
    }

    public function apply(MerchantService $merchants): void
    {
        $this->error = null;
        $this->validate([
            'businessName' => 'required|string|min:2|max:80',
            'brandColor' => 'nullable|string|max:9',
        ]);

        try {
            $merchants->apply(Auth::user(), [
                'business_name' => $this->businessName,
                'brand_color' => $this->brandColor,
            ]);
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Application submitted — we’ll review it shortly.');
    }

    public function render(KycService $kyc, MerchantService $merchants)
    {
        $user = Auth::user();
        $kybVerified = $kyc->hasLevel($user, KycVerification::L3);

        return view('livewire.become-merchant', [
            'programmeOpen' => MerchantSettings::enabled(),
            'kybVerified' => $kybVerified,
            'kybAttempt' => $kyc->latest($user, KycVerification::L3),
            // Eligibility is computed independently of KYB now (§1) — a user can
            // unlock and apply without any identity verification up front.
            'eligibility' => $merchants->eligibility($user),
            'merchant' => Merchant::where('owner_user_id', $user->id)->latest('id')->first(),
            // Global, data-driven business-registration catalogue (§2.1).
            'countries' => BusinessRegistration::countries(),
            'regTypes' => BusinessRegistration::typesFor($this->country),
            // Live V1/V2 plan comparison figures (§3.1) — pulled from settings.
            'pricing' => [
                'margin' => MerchantSettings::resellerMarginPct(),
                'upgradePrice' => MerchantSettings::upgradePriceUsd(),
            ],
        ]);
    }
}
