<?php

namespace Tests\Feature;

use App\Livewire\MerchantWhiteLabel;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WhiteLabelGuideLink;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WhiteLabelProjectIntake;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use App\Services\Wallet\WalletService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 21-EXT §2/§3 — the merchant-facing self-service license flow. A
 * non-merchant is 404'd (same as every other merchant screen); a Standard
 * merchant sees the page but it's locked (unlike MerchantClients' 404 — this
 * gate is visible-but-locked, mirroring the base Merchant-V2 gate); a V2
 * merchant can request a plan, pay to activate, and pay the remaining
 * balance to upgrade to Extended without disturbing their live token.
 */
class MerchantWhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
    }

    private function merchant(string $tier = Merchant::TIER_STANDARD, float $fund = 0): Merchant
    {
        $owner = User::factory()->create();
        if ($fund > 0) {
            app(WalletService::class)->credit($owner, $fund, 'USD');
        }

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => Merchant::ACTIVE, 'tier' => $tier,
        ]);
    }

    public function test_a_non_merchant_gets_a_404(): void
    {
        Livewire::actingAs(User::factory()->create())->test(MerchantWhiteLabel::class)->assertStatus(404);
    }

    public function test_a_standard_merchant_sees_the_page_locked_not_404(): void
    {
        $merchant = $this->merchant(Merchant::TIER_STANDARD);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertOk()
            ->assertSet('isV2', false)
            ->assertSee('Merchant V2 required');
    }

    public function test_a_v2_merchant_sees_the_plan_catalog(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertOk()
            ->assertSet('isV2', true)
            ->assertSee('Basic')
            ->assertSee('Extended V2');
    }

    public function test_the_comparison_table_shows_every_plan_and_its_price(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSee('Full platform, unlocked day one')
            ->assertSee('Pay balance later to reach Extended')
            ->assertSee('$1,500')
            ->assertSee('$7,500')
            ->assertSee('Priority');
    }

    public function test_a_v2_merchant_can_request_a_plan(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('disclaimerAcknowledged', true)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', null);

        $instance = WhiteLabelInstance::where('merchant_id', $merchant->id)->first();
        $this->assertNotNull($instance);
        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status);
        $this->assertSame($plan->id, $instance->license_plan_id);
        $this->assertSame(WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE, $instance->acquisition_method);
    }

    public function test_a_v2_merchant_can_request_a_plan_with_a_theme_addon(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('disclaimerAcknowledged', true)
            ->set('themeAddon', \App\Support\ThemeAddonCatalog::ELEGANT)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', null);

        $instance = WhiteLabelInstance::where('merchant_id', $merchant->id)->first();
        $this->assertSame(\App\Support\ThemeAddonCatalog::ELEGANT, $instance->theme_addon);
        $this->assertSame('2900.00', (string) $instance->theme_addon_price_usd);
    }

    public function test_requesting_without_acknowledging_the_disclaimer_is_refused(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', fn ($e) => $e !== null);

        $this->assertNull(WhiteLabelInstance::where('merchant_id', $merchant->id)->first());
    }

    public function test_a_standard_merchant_cannot_request_a_plan_via_the_component(): void
    {
        $merchant = $this->merchant(Merchant::TIER_STANDARD);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('disclaimerAcknowledged', true)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', fn ($e) => $e !== null);

        $this->assertNull(WhiteLabelInstance::where('merchant_id', $merchant->id)->first());
    }

    public function test_a_priced_request_can_be_paid_from_the_dashboard(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2, fund: 1500);
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500.00])->save();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->call('payNow')
            ->assertSet('error', null);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->fresh()->status);
        $this->assertSame('0.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);
    }

    public function test_paying_a_priced_request_with_a_theme_addon_charges_the_combined_total(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2, fund: 4400);
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
            'theme_addon' => \App\Support\ThemeAddonCatalog::BASIC,
        ]);
        $instance->forceFill(['price_usd' => 3200.00])->save();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSet('totalDue', 4400.0)
            ->assertSee('$4,400')
            ->call('payNow')
            ->assertSet('error', null);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->fresh()->status);
        $this->assertSame('0.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);
    }

    public function test_an_active_normal_instance_can_pay_the_balance_to_upgrade(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2, fund: 5000);
        $basic = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id, 'license_plan_id' => $basic->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500.00])->save();
        app(WhiteLabelLicenseService::class)->payAndActivate($instance->fresh(), $merchant->owner);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSet('balanceOwed', 3500.0)
            ->call('payBalance')
            ->assertSet('error', null);

        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $instance->fresh()->tier);
        $this->assertSame('0.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);
    }

    // --- Prompt 21-EXT2: project intake form ---

    private function activeInstance(Merchant $merchant): WhiteLabelInstance
    {
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co', 'merchant_id' => $merchant->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);

        return app(WhiteLabelLicenseService::class)->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
    }

    public function test_a_v2_merchant_can_submit_the_intake_form_for_managed_hosting(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $this->activeInstance($merchant);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('intakeDesiredBrandName', 'ConnectNow')
            ->set('intakeWhatsapp', '+2348012345678')
            ->set('intakeLogoDesignReference', 'Something modern, teal and gold')
            ->set('intakeHostingChoice', WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER)
            ->call('submitIntake')
            ->assertSet('intakeError', null);

        $intake = WhiteLabelProjectIntake::first();
        $this->assertNotNull($intake);
        $this->assertSame('ConnectNow', $intake->desired_brand_name);
        $this->assertNull($intake->hosting_host);
    }

    public function test_own_vps_choice_requires_credentials_and_disclaimer(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $this->activeInstance($merchant);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('intakeDesiredBrandName', 'ConnectNow')
            ->set('intakeWhatsapp', '+2348012345678')
            ->set('intakeLogoDesignReference', 'Something modern, teal and gold')
            ->set('intakeHostingChoice', WhiteLabelInstance::HOSTING_OWN_VPS)
            ->set('intakeHostingHost', 'my-vps.cloudwaysapps.com')
            ->set('intakeHostingUsername', 'root')
            ->set('intakeHostingPassword', 'S3cret!')
            ->call('submitIntake')
            ->assertSet('intakeError', fn ($e) => $e !== null); // disclaimer not acknowledged yet

        $this->assertNull(WhiteLabelProjectIntake::first());

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('intakeDesiredBrandName', 'ConnectNow')
            ->set('intakeWhatsapp', '+2348012345678')
            ->set('intakeLogoDesignReference', 'Something modern, teal and gold')
            ->set('intakeHostingChoice', WhiteLabelInstance::HOSTING_OWN_VPS)
            ->set('intakeHostingHost', 'my-vps.cloudwaysapps.com')
            ->set('intakeHostingUsername', 'root')
            ->set('intakeHostingPassword', 'S3cret!')
            ->set('intakeHostingDisclaimerAcknowledged', true)
            ->call('submitIntake')
            ->assertSet('intakeError', null);

        $intake = WhiteLabelProjectIntake::first();
        $this->assertNotNull($intake);
        $this->assertSame('S3cret!', $intake->hosting_password);
    }

    public function test_intake_requires_either_a_logo_upload_or_a_design_reference(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $this->activeInstance($merchant);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('intakeDesiredBrandName', 'ConnectNow')
            ->set('intakeWhatsapp', '+2348012345678')
            ->set('intakeHostingChoice', WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER)
            ->call('submitIntake')
            ->assertHasErrors(['intakeLogoDesignReference']);

        $this->assertNull(WhiteLabelProjectIntake::first());
    }

    public function test_intake_stores_brand_colors_and_uploaded_logo_and_banner_reference(): void
    {
        Storage::fake('local');
        $merchant = $this->merchant(Merchant::TIER_V2);
        $this->activeInstance($merchant);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('intakeDesiredBrandName', 'ConnectNow')
            ->set('intakeWhatsapp', '+2348012345678')
            ->set('intakeBrandPrimaryColor', '#123456')
            ->set('intakeBrandAccentColor', '#abcdef')
            ->set('intakeLogoUpload', UploadedFile::fake()->image('logo.png', 500, 500))
            ->set('intakeBannerReferenceUpload', UploadedFile::fake()->image('banner.jpg', 1680, 945))
            ->set('intakeBannerDesignRequest', 'Bright, teal-and-gold, matching the Naara style')
            ->set('intakeHostingChoice', WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER)
            ->call('submitIntake')
            ->assertSet('intakeError', null);

        $intake = WhiteLabelProjectIntake::first();
        $this->assertSame('#123456', $intake->brand_primary_color);
        $this->assertSame('#abcdef', $intake->brand_accent_color);
        $this->assertNotNull($intake->logo_url);
        $this->assertNotNull($intake->banner_reference_url);
        $this->assertSame('Bright, teal-and-gold, matching the Naara style', $intake->banner_design_request);
    }

    public function test_deploy_progress_renders_on_the_dashboard(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $instance = $this->activeInstance($merchant);
        $intakeService = app(WhiteLabelProjectIntakeService::class);
        $intake = $intakeService->submit($instance, [
            'desired_brand_name' => 'ConnectNow', 'whatsapp_number' => '+1',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);
        $admin = \App\Models\User::factory()->create();
        $intakeService->markSeen($intake, $admin->id);
        $intakeService->setDeployTimeline($intake, 10);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSet('deployProgress', 0)
            ->assertSee('Day 1 of 10')
            ->assertSee('Deployment in progress');
    }

    // --- Owner request (2026-09-15): in-app step-by-step guide ---

    public function test_a_standard_merchant_does_not_see_the_guide(): void
    {
        $merchant = $this->merchant(Merchant::TIER_STANDARD);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertDontSee('Your white-label guide');
    }

    public function test_a_v2_merchant_sees_the_guide(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSee('Your white-label guide')
            ->assertSee('Choose & request a plan')
            ->assertSee('Log in and go live');
    }

    public function test_guide_shows_domain_links_always_and_hosting_links_only_for_the_chosen_category(): void
    {
        WhiteLabelGuideLink::create(['category' => WhiteLabelGuideLink::CATEGORY_DOMAIN, 'label' => 'Namecheap Domains', 'url' => 'https://namecheap.test/', 'is_active' => true]);
        WhiteLabelGuideLink::create(['category' => WhiteLabelGuideLink::CATEGORY_VPS, 'label' => 'Cloudways VPS', 'url' => 'https://cloudways.test/', 'is_active' => true]);
        WhiteLabelGuideLink::create(['category' => WhiteLabelGuideLink::CATEGORY_SHARED, 'label' => 'Hostinger Shared', 'url' => 'https://hostinger.test/', 'is_active' => true]);
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('hostingPreference', WhiteLabelInstance::HOSTING_OWN_VPS)
            ->assertSee('Namecheap Domains')
            ->assertSee('Cloudways VPS')
            ->assertDontSee('Hostinger Shared');
    }

    public function test_guide_hides_hosting_links_when_hosting_on_supreme_ideas_server(): void
    {
        WhiteLabelGuideLink::create(['category' => WhiteLabelGuideLink::CATEGORY_VPS, 'label' => 'Cloudways VPS', 'url' => 'https://cloudways.test/', 'is_active' => true]);
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('hostingPreference', WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER)
            ->assertDontSee('Cloudways VPS');
    }

    public function test_an_inactive_guide_link_is_never_shown(): void
    {
        WhiteLabelGuideLink::create(['category' => WhiteLabelGuideLink::CATEGORY_VPS, 'label' => 'Retired Host', 'url' => 'https://retired.test/', 'is_active' => false]);
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('hostingPreference', WhiteLabelInstance::HOSTING_OWN_VPS)
            ->assertDontSee('Retired Host');
    }

    public function test_guide_uses_the_intake_hosting_choice_once_the_intake_form_is_reachable(): void
    {
        WhiteLabelGuideLink::create(['category' => WhiteLabelGuideLink::CATEGORY_SHARED, 'label' => 'Hostinger Shared', 'url' => 'https://hostinger.test/', 'is_active' => true]);
        $merchant = $this->merchant(Merchant::TIER_V2);
        $this->activeInstance($merchant);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('intakeHostingChoice', WhiteLabelInstance::HOSTING_OWN_SHARED)
            ->assertSee('Hostinger Shared');
    }
}
