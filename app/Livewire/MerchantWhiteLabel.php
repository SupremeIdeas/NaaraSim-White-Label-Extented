<?php

namespace App\Livewire;

use App\Exceptions\LicenseActivationException;
use App\Models\Merchant;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Services\Updater\WhiteLabelLicenseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Prompt 21-EXT §2/§3 — the merchant-facing self-service white-label license
 * flow: browse the seeded plan catalog, request a license against one, pay to
 * activate it once an admin has confirmed the price, and — for a Basic/Medium
 * (`normal`-tier) purchase — later pay off the remaining balance to unlock
 * Extended without re-issuing the license key or disturbing an already
 * deployed fork's live token.
 *
 * Unlike MerchantClients/MerchantInvoices (which 404 for a non-V2 merchant),
 * this page is visible-but-locked for one: the same pattern the base
 * Merchant-V2 gate itself already uses, so a Standard merchant sees what
 * they're missing rather than a dead end.
 */
#[Layout('components.layouts.customer')]
class MerchantWhiteLabel extends Component
{
    public string $hostingPreference = WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER;

    public bool $disclaimerAcknowledged = false;

    public ?string $error = null;

    private function merchant(): ?Merchant
    {
        return Auth::user()?->merchantAccount;
    }

    public function mount(): void
    {
        abort_unless($this->merchant() !== null, 404);
    }

    public function getIsV2Property(): bool
    {
        $merchant = $this->merchant();

        return $merchant !== null && $merchant->isActive() && $merchant->isV2();
    }

    public function getPlansProperty()
    {
        return WhiteLabelLicensePlan::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function getInstanceProperty(): ?WhiteLabelInstance
    {
        $merchant = $this->merchant();
        if ($merchant === null) {
            return null;
        }

        return WhiteLabelInstance::where('merchant_id', $merchant->id)->latest()->first();
    }

    /** The true remaining balance to reach Extended, per §3.3: the current
     *  live Extended plan's price minus whatever this instance has actually
     *  paid so far — never a re-typed guess. */
    public function getBalanceOwedProperty(): ?float
    {
        $instance = $this->instance;
        if ($instance === null || $instance->tier !== WhiteLabelInstance::TIER_NORMAL || ! $instance->hasLiveLicense()) {
            return null;
        }

        $extended = WhiteLabelLicensePlan::where('tier', WhiteLabelInstance::TIER_EXTENDED)
            ->where('is_active', true)
            ->orderBy('price_usd')
            ->first();

        if ($extended === null) {
            return null;
        }

        return max(0.0, round((float) $extended->price_usd - $instance->amountPaidTotal(), 2));
    }

    /** Prompt 21-EXT §2.4 — submit a request against a chosen plan card. */
    public function requestLicense(int $planId, WhiteLabelLicenseService $licenses): void
    {
        $this->error = null;
        $merchant = $this->merchant();

        if ($merchant === null || ! $merchant->isActive() || ! $merchant->isV2()) {
            $this->error = 'Merchant V2 is required to request a white-label license.';

            return;
        }
        if ($this->instance !== null) {
            $this->error = 'You already have a white-label request in progress.';

            return;
        }
        if (! $this->disclaimerAcknowledged) {
            $this->error = 'Please acknowledge the hosting disclaimer before requesting a license.';

            return;
        }

        $plan = WhiteLabelLicensePlan::where('is_active', true)->find($planId);
        if ($plan === null) {
            $this->error = 'That plan is not available.';

            return;
        }

        try {
            $licenses->register([
                'brand_name' => $merchant->business_name,
                'contact_email' => $merchant->owner?->email ?? '',
                'owner_user_id' => $merchant->owner_user_id,
                'merchant_id' => $merchant->id,
                'license_plan_id' => $plan->id,
                'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
                'requested_tier' => $plan->tier,
                'hosting_preference' => $this->hostingPreference,
                'hosting_disclaimer_acknowledged' => true,
            ]);
        } catch (\RuntimeException) {
            $this->error = 'This license tier is temporarily closed for new requests — check back later.';

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Request submitted. An admin will confirm pricing shortly.');
    }

    /** Prompt 21-EXT §3.2 — the merchant's own "pay to activate" action. */
    public function payNow(WhiteLabelLicenseService $licenses): void
    {
        $this->error = null;
        $instance = $this->instance;
        if ($instance === null) {
            return;
        }

        try {
            $licenses->payAndActivate($instance, Auth::user());
        } catch (LicenseActivationException) {
            $this->error = 'This request is not ready for payment yet.';

            return;
        } catch (\Throwable) {
            $this->error = 'Payment failed — check your wallet balance and try again.';

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Payment successful — your white-label license is now active.');
    }

    /** Prompt 21-EXT §3.3 — pay the remaining balance to unlock Extended. */
    public function payBalance(WhiteLabelLicenseService $licenses): void
    {
        $this->error = null;
        $instance = $this->instance;
        $amount = $this->balanceOwed;

        if ($instance === null || $amount === null || $amount <= 0) {
            return;
        }

        try {
            $licenses->payBalanceAndUpgrade($instance, Auth::user(), $amount);
        } catch (LicenseActivationException) {
            $this->error = 'This instance is not eligible for a balance-completion upgrade.';

            return;
        } catch (\Throwable) {
            $this->error = 'Payment failed — check your wallet balance and try again.';

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Upgraded to Extended — your fork unlocks on its next check-in.');
    }

    public function render()
    {
        return view('livewire.merchant-white-label');
    }
}
