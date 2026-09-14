<?php

namespace Tests\Feature;

use App\Livewire\Admin\WhiteLabelRegistry;
use App\Models\DistributedPackage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WhiteLabelProjectIntake;
use App\Services\Updater\PackagePublisher;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 4 §2 — the White-Label Oversight admin screen. Gate, the feature-flag
 * toggle, per-brand API-log drill-down, and publisher-side publish/unpublish.
 */
class WhiteLabelRegistryScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function package(bool $published = false): DistributedPackage
    {
        return DistributedPackage::create([
            'package_id' => 'pkg-'.uniqid(),
            'product' => 'naarasim-whitelabel',
            'version' => '2026.09.10-1',
            'package_type' => 'code_and_migrations',
            'min_compatible_version' => '2026.09.01-1',
            'storage_path' => 'distribution/x.naaraupdate',
            'size_bytes' => 100,
            'is_published' => $published,
        ]);
    }

    public function test_a_non_admin_gets_a_403(): void
    {
        Livewire::actingAs(User::factory()->create())->test(WhiteLabelRegistry::class)->assertStatus(403);
    }

    public function test_an_admin_can_toggle_the_api_feature_flag(): void
    {
        $this->assertFalse((bool) Setting::getValue('white_label_api.enabled', false));

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('toggleApi');

        $this->assertTrue((bool) Setting::getValue('white_label_api.enabled', false));
    }

    public function test_publish_and_unpublish_a_package(): void
    {
        $package = $this->package(published: false);

        $c = Livewire::actingAs($this->admin())->test(WhiteLabelRegistry::class);

        $c->call('togglePublish', $package->id);
        $this->assertTrue($package->fresh()->is_published);

        $c->call('togglePublish', $package->id);
        $this->assertFalse($package->fresh()->is_published);
    }

    public function test_withdraw_deletes_the_package_and_its_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('distribution/x.naaraupdate', 'bytes');
        $package = $this->package(published: true);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('withdrawPackage', $package->id);

        $this->assertNull(DistributedPackage::find($package->id));
        Storage::disk('local')->assertMissing('distribution/x.naaraupdate');
    }

    public function test_the_per_brand_log_drilldown_shows_that_brands_calls(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Brand', 'slug' => 'brand', 'contact_email' => 'a@b.c', 'status' => 'active',
        ]);
        WhiteLabelApiLog::create([
            'white_label_instance_id' => $instance->id, 'endpoint' => 'white-label.updates.check',
            'method' => 'GET', 'response_status' => 200, 'created_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->assertSet('selectedInstanceId', null)
            ->call('selectInstance', $instance->id)
            ->assertSet('selectedInstanceId', $instance->id)
            ->assertSee('white-label.updates.check');
    }

    // --- Batch 6: license authority admin controls ---

    public function test_issuing_a_license_creates_an_active_instance_and_reveals_the_key(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('newBrand', 'Fresh Brand')
            ->set('newEmail', 'fresh@brand.test')
            ->set('newTier', 'extended')
            ->call('issueNewLicense')
            ->assertSet('revealedKey', fn ($k) => is_string($k) && str_starts_with($k, 'NAARA-'));

        $instance = WhiteLabelInstance::where('contact_email', 'fresh@brand.test')->first();
        $this->assertNotNull($instance);
        $this->assertSame('active', $instance->status);
        $this->assertSame('extended', $instance->tier);
    }

    public function test_approving_a_pending_request_issues_a_key_and_activates_it(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Waiting', 'slug' => 'waiting', 'contact_email' => 'w@w.test', 'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('approveInstance', $instance->id, 'normal')
            ->assertSet('revealedKey', fn ($k) => is_string($k) && str_starts_with($k, 'NAARA-'));

        $this->assertSame('active', $instance->fresh()->status);
        $this->assertSame('normal', $instance->fresh()->tier);
    }

    public function test_suspending_from_the_screen_revokes_the_token(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Live', 'slug' => 'live', 'contact_email' => 'l@l.test', 'status' => 'active',
            'tier' => 'normal', 'license_key' => 'NAARA-AAAA-BBBB-CCCC', 'license_issued_at' => now(),
        ]);
        $instance->createToken('t', WhiteLabelInstance::SCOPES);
        $this->assertSame(1, $instance->tokens()->count());

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('suspendInstance', $instance->id);

        $this->assertSame('suspended', $instance->fresh()->status);
        $this->assertSame(0, $instance->fresh()->tokens()->count());
    }

    public function test_revoking_from_the_screen_permanently_kills_the_key(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Gone', 'slug' => 'gone', 'contact_email' => 'g@g.test', 'status' => 'active',
            'tier' => 'normal', 'license_key' => 'NAARA-DDDD-EEEE-FFFF', 'license_issued_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('revokeLicense', $instance->id);

        $this->assertNotNull($instance->fresh()->license_revoked_at);
        $this->assertSame('suspended', $instance->fresh()->status);
    }

    // --- Prompt 21-EXT §3.1: pricing a self-service request ---

    public function test_pricing_a_self_service_request_sets_the_price_without_issuing_a_license(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Self Serve', 'slug' => 'self-serve', 'contact_email' => 's@s.test',
            'status' => 'pending', 'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('priceInputs.'.$instance->id, '1500')
            ->call('priceInstance', $instance->id);

        $instance->refresh();
        $this->assertSame('1500.00', (string) $instance->price_usd);
        $this->assertSame('pending', $instance->status);
        $this->assertNull($instance->license_key);
    }

    public function test_pricing_rejects_a_zero_or_blank_price(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Self Serve', 'slug' => 'self-serve-2', 'contact_email' => 's2@s.test',
            'status' => 'pending', 'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('priceInstance', $instance->id);

        $this->assertNull($instance->fresh()->price_usd);
    }

    // --- Prompt 21-EXT §1.4/§6.5: plan catalog + resell-status management ---

    public function test_an_admin_can_create_a_new_plan(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('planName', 'Custom Tier')
            ->set('planTagline', 'A bespoke option')
            ->set('planDescription', 'Description text.')
            ->set('planPrice', '3000')
            ->set('planTier', WhiteLabelInstance::TIER_NORMAL)
            ->set('planSupportLevel', WhiteLabelLicensePlan::SUPPORT_STANDARD)
            ->set('planFeaturesText', "Feature one\nFeature two")
            ->call('savePlan');

        $plan = WhiteLabelLicensePlan::where('name', 'Custom Tier')->first();
        $this->assertNotNull($plan);
        $this->assertSame('3000.00', (string) $plan->price_usd);
        $this->assertSame(['Feature one', 'Feature two'], $plan->features);
        $this->assertTrue($plan->is_active);
    }

    public function test_an_admin_can_edit_an_existing_plan_without_changing_its_key(): void
    {
        $plan = WhiteLabelLicensePlan::create([
            'key' => 'basic', 'name' => 'Basic', 'tagline' => 't', 'description' => 'd',
            'price_usd' => 1500, 'tier' => WhiteLabelInstance::TIER_NORMAL, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('editPlan', $plan->id)
            ->assertSet('planName', 'Basic')
            ->set('planName', 'Basic Renamed')
            ->set('planDescription', 'New description')
            ->call('savePlan');

        $plan->refresh();
        $this->assertSame('Basic Renamed', $plan->name);
        $this->assertSame('basic', $plan->key);
    }

    public function test_an_admin_can_upload_a_cover_image_for_a_plan(): void
    {
        Storage::fake('local');
        $plan = WhiteLabelLicensePlan::create([
            'key' => 'basic', 'name' => 'Basic', 'tagline' => 't', 'description' => 'd',
            'price_usd' => 1500, 'tier' => WhiteLabelInstance::TIER_NORMAL, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('editPlan', $plan->id)
            ->set('planCoverUpload', UploadedFile::fake()->image('cover.jpg', 1680, 945))
            ->call('savePlan');

        $this->assertNotNull($plan->fresh()->cover_image_url);
    }

    public function test_an_admin_can_toggle_a_plans_active_state(): void
    {
        $plan = WhiteLabelLicensePlan::create([
            'key' => 'basic', 'name' => 'Basic', 'tagline' => 't', 'description' => 'd',
            'price_usd' => 1500, 'tier' => WhiteLabelInstance::TIER_NORMAL, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())->test(WhiteLabelRegistry::class)->call('togglePlanActive', $plan->id);
        $this->assertFalse($plan->fresh()->is_active);
    }

    public function test_an_admin_can_toggle_resell_status_and_see_it_flip(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->assertSet('resellStatus.normal.open', true)
            ->call('toggleResell', WhiteLabelInstance::TIER_NORMAL)
            ->assertSet('resellStatus.normal.open', false);

        $this->assertFalse(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL));
    }

    // --- Prompt 21-EXT §5.3/§5.5: platform earnings withdrawal ---

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_a_plain_admin_does_not_see_the_platform_earnings_section(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->assertSet('isSuperAdmin', false)
            ->assertDontSee('Platform earnings');
    }

    public function test_a_super_admin_can_withdraw_platform_earnings(): void
    {
        \App\Models\Setting::setValue(\App\Support\PayoutSettings::FLAG, true, 'payouts');

        $admin = $this->superAdmin();
        $payer = User::factory()->create();
        app(\App\Services\Wallet\WalletService::class)->credit($payer, 1500, 'USD');
        $instance = app(\App\Services\Updater\WhiteLabelLicenseService::class)->register(['brand_name' => 'X', 'contact_email' => 'x@x.test']);
        $instance->forceFill(['price_usd' => 1500.00, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();
        app(\App\Services\Updater\WhiteLabelLicenseService::class)->payAndActivate($instance, $payer);

        $account = \App\Models\PayoutAccount::create([
            'user_id' => $admin->id, 'type' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '001', 'account_number' => '0123456789', 'account_name' => 'Supreme Ideas',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(WhiteLabelRegistry::class)
            ->assertSet('isSuperAdmin', true)
            ->assertSee('Platform earnings')
            ->set('platformAmountUsd', '1000')
            ->call('withdrawPlatformEarnings')
            ->assertSet('platformWithdrawError', null);

        $this->assertSame(500.0, app(\App\Services\Platform\PlatformEarningsService::class)->balance());
    }

    // --- Prompt 21-EXT2 §5/§6: project intake review ---

    private function licensedInstanceWithIntake(): array
    {
        $instance = app(WhiteLabelLicenseService::class)->register(['brand_name' => 'Biz', 'contact_email' => 'b@b.test']);
        $instance = app(WhiteLabelLicenseService::class)->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);
        $intake = app(WhiteLabelProjectIntakeService::class)->submit($instance, [
            'desired_brand_name' => 'ConnectNow', 'whatsapp_number' => '+1',
            'brand_primary_color' => '#0A6E6E', 'brand_accent_color' => '#D4A017',
            'logo_design_reference' => 'Modern, clean',
            'hosting_choice' => WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER,
        ]);

        return [$instance, $intake];
    }

    public function test_admin_can_mark_an_intake_seen(): void
    {
        [, $intake] = $this->licensedInstanceWithIntake();

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('markIntakeSeen', $intake->id);

        $this->assertSame(WhiteLabelProjectIntake::STATUS_SEEN, $intake->fresh()->status);
    }

    public function test_admin_can_set_a_deploy_timeline_only_after_marking_seen(): void
    {
        [, $intake] = $this->licensedInstanceWithIntake();

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('deployDaysInputs.'.$intake->id, '7')
            ->call('setIntakeDeployTimeline', $intake->id);

        $this->assertSame(WhiteLabelProjectIntake::STATUS_PENDING, $intake->fresh()->status, 'timeline refused before Seen — status unchanged');

        app(WhiteLabelProjectIntakeService::class)->markSeen($intake, $this->admin()->id);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('deployDaysInputs.'.$intake->id, '7')
            ->call('setIntakeDeployTimeline', $intake->id);

        $this->assertSame(WhiteLabelProjectIntake::STATUS_IN_PROGRESS, $intake->fresh()->status);
        $this->assertSame(7, $intake->fresh()->deploy_days);
    }

    /**
     * Regression: the expanded detail row is the only place the admin panel
     * reads the deploy progress/day-of-total via a closure-returning computed
     * property (Livewire computed properties can't take params directly, so
     * getIntakeProgressProperty()/getIntakeDayOfProperty() return closures the
     * blade must invoke). A live-browser walkthrough caught that the blade
     * was reading `$this->intakeProgress`/`$this->intakeDayOf` as plain
     * values instead of calling them — this test renders that exact branch
     * so it can never regress silently again.
     */
    public function test_the_expanded_detail_row_renders_the_deploy_progress_bar(): void
    {
        [$instance, $intake] = $this->licensedInstanceWithIntake();
        app(WhiteLabelProjectIntakeService::class)->markSeen($intake, $this->admin()->id);
        app(WhiteLabelProjectIntakeService::class)->setDeployTimeline($intake, 10);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('toggleIntakeDetail', $instance->id)
            ->assertOk()
            ->assertSee('Day 1 of 10')
            ->assertSee('0%');
    }

    public function test_admin_can_export_the_intake_as_a_pdf(): void
    {
        [, $intake] = $this->licensedInstanceWithIntake();

        $response = $this->actingAs($this->admin())->get(route('admin.white-label.intake.pdf', $intake->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_non_panel_user_gets_a_plain_404_on_the_intake_pdf_route(): void
    {
        // The panel-wide EnsureAdmin middleware 404s anyone without ANY panel
        // role before the controller's own super_admin/admin check ever runs
        // — the panel stays invisible (blueprint Section 25).
        [, $intake] = $this->licensedInstanceWithIntake();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.white-label.intake.pdf', $intake->id));

        $response->assertNotFound();
    }

    public function test_a_staff_user_is_forbidden_from_the_intake_pdf(): void
    {
        [, $intake] = $this->licensedInstanceWithIntake();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $response = $this->actingAs($staff)->get(route('admin.white-label.intake.pdf', $intake->id));

        $response->assertForbidden();
    }

    public function test_issue_token_directly_reveals_a_bearer_token_once(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Firewalled', 'slug' => 'fw', 'contact_email' => 'f@f.test', 'status' => 'active',
            'tier' => 'normal', 'license_key' => 'NAARA-GGGG-HHHH-JJJJ', 'license_issued_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('issueTokenFor', $instance->id)
            ->assertSet('revealedToken', fn ($t) => is_string($t) && str_contains($t, '|'));

        $this->assertSame(1, $instance->fresh()->tokens()->count());
    }
}
