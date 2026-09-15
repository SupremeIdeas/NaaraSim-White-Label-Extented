<?php

namespace App\Livewire;

use App\Exceptions\LicenseActivationException;
use App\Models\Merchant;
use App\Models\WhiteLabelGuideLink;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WhiteLabelProjectIntake;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Updater\WhiteLabelProjectIntakeException;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use App\Support\MediaStorage;
use App\Support\ThemeAddonCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

    public string $hostingPreference = WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER;

    public bool $disclaimerAcknowledged = false;

    /** Owner request (2026-09-15) — optional custom-theme request add-on,
     *  billed together with the license. Defaults to None so a merchant who
     *  just wants a normal setup checks out with no extra cost. */
    public string $themeAddon = ThemeAddonCatalog::NONE;

    public ?string $error = null;

    // Prompt 21-EXT2 §2 — project intake form (post-purchase commencement brief).
    public string $intakeDesiredBrandName = '';

    public string $intakeWhatsapp = '';

    // Brand identity — mirrors how Naara's own on-brand palette (Deep Teal +
    // Warm Gold) drives every themed surface; the merchant picks the same
    // two-tone pairing for their own white-label.
    public string $intakeBrandPrimaryColor = '#0A6E6E';

    public string $intakeBrandAccentColor = '#D4A017';

    public $intakeLogoUpload = null;

    public string $intakeLogoDesignReference = '';

    public $intakeBannerReferenceUpload = null;

    public string $intakeBannerDesignRequest = '';

    public string $intakeHostingChoice = WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER;

    public bool $intakeHostingDisclaimerAcknowledged = false;

    public string $intakeHostingHost = '';

    public string $intakeHostingUsername = '';

    public string $intakeHostingPassword = '';

    public string $intakeHostingNotes = '';

    public string $intakeAdditionalNotes = '';

    public ?string $intakeError = null;

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

    /** @return array<string, array{label:string, price:float}> */
    public function getThemeAddonOptionsProperty(): array
    {
        return ThemeAddonCatalog::options();
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

    /** The full amount payNow() will charge — the confirmed license price
     *  plus whatever theme add-on was requested alongside it, if any. */
    public function getTotalDueProperty(): ?float
    {
        return $this->instance?->totalDueUsd();
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
                'theme_addon' => $this->themeAddon,
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

    public function getIntakeProperty(): ?WhiteLabelProjectIntake
    {
        return $this->instance?->intake;
    }

    /** Owner request (2026-09-15) — the in-app step-by-step guide. Whichever
     *  hosting choice is currently "live" for this merchant: the submitted
     *  intake's binding choice once it exists, otherwise the in-progress
     *  intake form's selection once the license is active, otherwise the
     *  pre-purchase soft preference — so the guide's reference links update
     *  the moment a merchant picks something, at every stage. */
    public function getEffectiveHostingChoiceProperty(): string
    {
        if ($this->intake !== null) {
            return $this->intake->hosting_choice;
        }
        if ($this->instance?->status === WhiteLabelInstance::ACTIVE) {
            return $this->intakeHostingChoice;
        }

        return $this->hostingPreference;
    }

    public function getGuideDomainLinksProperty()
    {
        return WhiteLabelGuideLink::forCategory(WhiteLabelGuideLink::CATEGORY_DOMAIN);
    }

    public function getGuideHostingLinksProperty()
    {
        $category = match ($this->effectiveHostingChoice) {
            WhiteLabelInstance::HOSTING_OWN_VPS => WhiteLabelGuideLink::CATEGORY_VPS,
            WhiteLabelInstance::HOSTING_OWN_SHARED => WhiteLabelGuideLink::CATEGORY_SHARED,
            default => null,
        };

        return $category !== null ? WhiteLabelGuideLink::forCategory($category) : collect();
    }

    /** Which of the 5 guide steps is most relevant right now, so the guide
     *  opens on the step that actually matches where this merchant is. */
    public function getGuideCurrentStepProperty(): int
    {
        $instance = $this->instance;
        if ($instance === null) {
            return 1;
        }
        if ($instance->status === WhiteLabelInstance::PENDING) {
            return $instance->price_usd === null ? 1 : 2;
        }
        if ($instance->status === WhiteLabelInstance::ACTIVE) {
            $intake = $this->intake;
            if ($intake === null) {
                return 3;
            }

            return $intake->status === WhiteLabelProjectIntake::STATUS_COMPLETED ? 5 : 4;
        }

        return 1;
    }

    public function getIntakeIsSelfHostedProperty(): bool
    {
        return in_array($this->intakeHostingChoice, [
            WhiteLabelInstance::HOSTING_OWN_VPS, WhiteLabelInstance::HOSTING_OWN_SHARED,
        ], true);
    }

    public function getDeployProgressProperty(): ?int
    {
        $intake = $this->intake;
        if ($intake === null) {
            return null;
        }

        return app(WhiteLabelProjectIntakeService::class)->progressPercent($intake);
    }

    public function getDeployDayOfProperty(): ?array
    {
        $intake = $this->intake;
        if ($intake === null) {
            return null;
        }

        return app(WhiteLabelProjectIntakeService::class)->dayOf($intake);
    }

    /** Prompt 21-EXT2 §3 — file (or refile, before it's reviewed) the
     *  project-commencement brief once the license is live. */
    public function submitIntake(WhiteLabelProjectIntakeService $intakes): void
    {
        $this->intakeError = null;
        $instance = $this->instance;
        if ($instance === null || ! $instance->hasLiveLicense()) {
            return;
        }

        $rules = [
            'intakeDesiredBrandName' => ['required', 'string', 'max:120'],
            'intakeWhatsapp' => ['required', 'string', 'max:32'],
            'intakeBrandPrimaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'intakeBrandAccentColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'intakeLogoUpload' => ['nullable', 'image', 'max:2048'],
            'intakeLogoDesignReference' => ['nullable', 'string', 'max:1000', 'required_without:intakeLogoUpload'],
            'intakeBannerReferenceUpload' => ['nullable', 'image', 'max:2048'],
            'intakeBannerDesignRequest' => ['nullable', 'string', 'max:2000'],
            'intakeHostingChoice' => ['required', 'in:'.implode(',', WhiteLabelInstance::HOSTING_CHOICES)],
            'intakeAdditionalNotes' => ['nullable', 'string', 'max:2000'],
        ];
        if ($this->intakeIsSelfHosted) {
            $rules['intakeHostingHost'] = ['required', 'string', 'max:255'];
            $rules['intakeHostingUsername'] = ['required', 'string', 'max:255'];
            $rules['intakeHostingPassword'] = ['required', 'string', 'max:255'];
            $rules['intakeHostingNotes'] = ['nullable', 'string', 'max:2000'];
        }
        $this->validate($rules, [
            'intakeLogoDesignReference.required_without' => 'Upload a logo, or describe/link a design reference for our team to work from.',
            'intakeBrandPrimaryColor.regex' => 'Use a hex colour like #0A6E6E.',
            'intakeBrandAccentColor.regex' => 'Use a hex colour like #D4A017.',
        ]);

        if ($this->intakeIsSelfHosted && ! $this->intakeHostingDisclaimerAcknowledged) {
            $this->intakeError = 'Please acknowledge the hosting-credential disclaimer.';

            return;
        }

        $logoUrl = $this->intakeLogoUpload ? MediaStorage::storePublic($this->intakeLogoUpload, 'white-label-intake-logos') : null;
        $bannerRefUrl = $this->intakeBannerReferenceUpload ? MediaStorage::storePublic($this->intakeBannerReferenceUpload, 'white-label-intake-banners') : null;

        try {
            $intakes->submit($instance, [
                'desired_brand_name' => $this->intakeDesiredBrandName,
                'whatsapp_number' => $this->intakeWhatsapp,
                'brand_primary_color' => $this->intakeBrandPrimaryColor,
                'brand_accent_color' => $this->intakeBrandAccentColor,
                'logo_url' => $logoUrl,
                'logo_design_reference' => $this->intakeLogoDesignReference ?: null,
                'banner_reference_url' => $bannerRefUrl,
                'banner_design_request' => $this->intakeBannerDesignRequest ?: null,
                'hosting_choice' => $this->intakeHostingChoice,
                'hosting_disclaimer_acknowledged' => $this->intakeHostingDisclaimerAcknowledged,
                'hosting_host' => $this->intakeHostingHost ?: null,
                'hosting_username' => $this->intakeHostingUsername ?: null,
                'hosting_password' => $this->intakeHostingPassword ?: null,
                'hosting_notes' => $this->intakeHostingNotes ?: null,
                'additional_notes' => $this->intakeAdditionalNotes ?: null,
            ]);
        } catch (WhiteLabelProjectIntakeException) {
            $this->intakeError = 'Your license needs to be active before you can submit this form.';

            return;
        }

        $this->reset(
            'intakeDesiredBrandName', 'intakeWhatsapp', 'intakeHostingDisclaimerAcknowledged',
            'intakeLogoUpload', 'intakeLogoDesignReference', 'intakeBannerReferenceUpload', 'intakeBannerDesignRequest',
            'intakeHostingHost', 'intakeHostingUsername', 'intakeHostingPassword', 'intakeHostingNotes', 'intakeAdditionalNotes',
        );
        $this->dispatch('nx-toast', type: 'success', message: 'Project details submitted — our team will review it shortly.');
    }

    public function render()
    {
        return view('livewire.merchant-white-label');
    }
}
