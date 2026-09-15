<?php

namespace Tests\Feature;

use App\Livewire\Admin\AppBuilder;
use App\Models\AppBuild;
use App\Models\User;
use App\Services\AppExport\BuildDispatcher;
use App\Support\AppExport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Installable App Export. Covers the honest-state rules (a ready APK is not a
 * live listing; iOS never gets a fake direct-install path), encrypted keystore
 * storage + the mandatory backup warning, the admin-only gate, build lifecycle
 * via the signed CI callback, and the admin-assignable download CTA placements.
 */
class AppExportTest extends TestCase
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

    public function test_manifest_reflects_admin_settings(): void
    {
        AppExport::save(['app_name' => 'Naara Go', 'theme_color' => '#123456']);

        $res = $this->get('/manifest.webmanifest');

        $res->assertOk();
        $res->assertJsonPath('name', 'Naara Go');
        $res->assertJsonPath('theme_color', '#123456');
        $res->assertJsonPath('display', 'standalone');
    }

    public function test_download_page_is_hidden_until_enabled(): void
    {
        $this->get('/download')->assertNotFound();

        AppExport::save(['download_enabled' => true]);
        $this->get('/download')->assertOk();
    }

    public function test_app_export_enabled_defaults_true_and_gates_the_download_page(): void
    {
        $this->assertTrue(AppExport::enabled());

        AppExport::save(['download_enabled' => true]);
        $this->get('/download')->assertOk();

        AppExport::save(['app_export_enabled' => false]);
        $this->assertFalse(AppExport::enabled());
        // Still 404 even though download_enabled itself is untouched — the
        // master switch overrides it.
        $this->get('/download')->assertNotFound();

        AppExport::save(['app_export_enabled' => true]);
        $this->get('/download')->assertOk();
    }

    public function test_build_dispatcher_refuses_to_create_a_build_while_disabled(): void
    {
        AppExport::save(['app_export_enabled' => false]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\AppExport\BuildDispatcher::class)->create('android', 'apk');
    }

    public function test_admin_can_toggle_app_export_enabled_from_app_builder(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->assertSet('form.app_export_enabled', true)
            ->call('toggleAppExportEnabled')
            ->assertSet('form.app_export_enabled', false);

        $this->assertFalse(AppExport::enabled());

        // Generating a build while off shows a toast instead of crashing, and
        // never creates a build row.
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->call('generateBuild', 'android', 'apk')
            ->assertDispatched('nx-toast');
        $this->assertSame(0, AppBuild::count());

        // Flip back on — App Builder itself is never gated, so the admin can
        // always reach this switch again.
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->call('toggleAppExportEnabled')
            ->assertSet('form.app_export_enabled', true);
        $this->assertTrue(AppExport::enabled());
    }

    public function test_apk_shows_only_when_a_ready_apk_exists(): void
    {
        AppExport::save(['download_enabled' => true]);
        $this->assertFalse(AppExport::androidDownloadable());

        AppExport::save(['android_apk_url' => 'https://cdn.naara.sim/app.apk']);
        $this->assertTrue(AppExport::androidDownloadable());
        $this->get('/download')->assertSee('Download APK');
    }

    public function test_ios_badge_never_shows_without_a_live_listing(): void
    {
        AppExport::save(['download_enabled' => true, 'ios_store_url' => 'https://apps.apple.com/app/id1']);
        // URL set but not marked live → still hidden.
        $this->assertFalse(AppExport::iosBadgeVisible());

        AppExport::save(['ios_store_live' => true]);
        $this->assertTrue(AppExport::iosBadgeVisible());
    }

    public function test_placement_is_off_until_enabled_and_toggled(): void
    {
        AppExport::save(['placements' => ['footer' => ['active' => true, 'label' => 'Grab our app']]]);
        // download not enabled → every placement is off regardless of its toggle.
        $this->assertFalse(AppExport::placementActive('footer'));

        AppExport::save(['download_enabled' => true]);
        $this->assertTrue(AppExport::placementActive('footer'));
        $this->assertSame('Grab our app', AppExport::placementLabel('footer'));
    }

    public function test_app_builder_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AppBuilder::class)->assertStatus(403);
        Livewire::actingAs($this->admin())->test(AppBuilder::class)->assertOk();
    }

    public function test_generate_build_creates_a_queued_record(): void
    {
        Queue::fake();
        AppExport::save(['version' => '2.1.0', 'build_number' => 7]);

        app(BuildDispatcher::class)->create('android', 'apk', $this->admin());

        $build = AppBuild::first();
        $this->assertSame('android', $build->platform);
        $this->assertSame('2.1.0', $build->version);
        $this->assertSame(AppBuild::STATUS_QUEUED, $build->status);
    }

    public function test_ci_callback_marks_build_ready_and_sets_download(): void
    {
        config(['services.appexport.ci_secret' => 'shh']);
        AppExport::save(['download_enabled' => true]);
        $build = AppBuild::create([
            'platform' => 'android', 'artifact_type' => 'apk', 'version' => '1.0.0',
            'build_number' => 1, 'status' => AppBuild::STATUS_BUILDING,
        ]);

        $body = json_encode(['build_id' => $build->id, 'status' => 'ready', 'artifact_url' => 'https://cdn/app.apk']);
        $sig = hash_hmac('sha256', $body, 'shh');

        $this->call('POST', '/webhooks/appbuild/ci', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_NAARA_SIGNATURE' => $sig], $body)
            ->assertOk();

        $this->assertSame(AppBuild::STATUS_READY, $build->fresh()->status);
        $this->assertSame('https://cdn/app.apk', AppExport::get('android_apk_url'));
    }

    public function test_ci_callback_rejects_a_bad_signature(): void
    {
        config(['services.appexport.ci_secret' => 'shh']);
        $build = AppBuild::create([
            'platform' => 'android', 'artifact_type' => 'apk', 'version' => '1.0.0',
            'build_number' => 1, 'status' => AppBuild::STATUS_BUILDING,
        ]);

        $body = json_encode(['build_id' => $build->id, 'status' => 'ready']);
        $this->call('POST', '/webhooks/appbuild/ci', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_NAARA_SIGNATURE' => 'wrong'], $body)
            ->assertStatus(401);

        $this->assertSame(AppBuild::STATUS_BUILDING, $build->fresh()->status);
    }

    public function test_keystore_is_stored_encrypted_and_needs_backup_ack(): void
    {
        $this->assertFalse(AppExport::hasCredential('android_keystore'));

        AppExport::storeCredential('android_keystore', base64_encode('KEYSTOREBYTES'), 'release.jks');

        $this->assertTrue(AppExport::hasCredential('android_keystore'));
        $meta = AppExport::credentialMeta('android_keystore');
        $this->assertSame('release.jks', $meta['filename']);
        $this->assertArrayNotHasKey('data', $meta); // bytes never surface in metadata
        // Uploading a keystore resets the backup acknowledgement.
        $this->assertFalse((bool) AppExport::get('keystore_backed_up'));

        AppExport::save(['keystore_backed_up' => true]);
        $this->assertTrue((bool) AppExport::get('keystore_backed_up'));
    }

    public function test_onboarding_redirects_to_login_until_slides_exist(): void
    {
        // No slides → onboarding is not "enabled" → straight to login.
        $this->get('/get-started')->assertRedirect(route('login'));

        AppExport::save(['onboarding_slides' => [
            ['image' => 'https://cdn/slide1.webp', 'title' => 'Welcome', 'subtitle' => 'Hi'],
        ]]);
        $this->get('/get-started')->assertOk()->assertSee('Welcome');
    }

    public function test_manifest_start_url_enters_onboarding_when_slides_exist(): void
    {
        $this->assertSame('/dashboard', AppExport::manifest()['start_url']);

        AppExport::save(['onboarding_slides' => [['image' => 'https://cdn/s.webp', 'title' => 'Hi']]]);
        $this->assertSame('/get-started', AppExport::manifest()['start_url']);
    }

    public function test_readiness_checklist_reflects_filled_fields(): void
    {
        $before = AppExport::readinessScore()['done'];

        AppExport::save([
            'privacy_policy_url' => 'https://naara.sim/privacy',
            'support_email' => 'help@naara.sim',
            'short_description' => 'Stay connected anywhere.',
        ]);

        $this->assertGreaterThan($before, AppExport::readinessScore()['done']);

        // Operator-only items (paid accounts) are never counted toward the score.
        $ios = collect(AppExport::publishChecklist()['iOS']);
        $this->assertTrue($ios->firstWhere('label', 'Apple Developer Program ($99/yr)')['operator']);
    }

    public function test_admin_can_add_an_onboarding_slide(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->call('addSlide')
            ->assertCount('form.onboarding_slides', 1);
    }

    // --- App Export audit (2026-09-15): §1.5/§3.5 iOS credential gap ---

    public function test_ios_credentials_are_stored_encrypted_independently_of_the_android_keystore(): void
    {
        $this->assertFalse(AppExport::hasCredential('ios_cert'));
        $this->assertFalse(AppExport::hasCredential('ios_provisioning_profile'));

        AppExport::storeCredential('ios_cert', base64_encode('CERTBYTES'), 'dist.p12');
        AppExport::storeCredential('ios_provisioning_profile', base64_encode('PROFILEBYTES'), 'profile.mobileprovision');

        $this->assertTrue(AppExport::hasCredential('ios_cert'));
        $this->assertTrue(AppExport::hasCredential('ios_provisioning_profile'));
        $this->assertSame('dist.p12', AppExport::credentialMeta('ios_cert')['filename']);
        // Uploading iOS credentials must NOT touch the unrelated Android backup flag.
        AppExport::save(['keystore_backed_up' => true]);
        AppExport::storeCredential('ios_cert', base64_encode('NEWCERT'), 'dist2.p12');
        $this->assertTrue((bool) AppExport::get('keystore_backed_up'));
    }

    public function test_checklist_ios_signing_item_accepts_either_uploaded_credentials_or_the_provider_flag(): void
    {
        $iosItem = fn () => collect(AppExport::publishChecklist()['iOS'])->firstWhere('label', 'iOS signing (stored here or on the CI provider)');
        $this->assertFalse($iosItem()['ok']);

        AppExport::save(['ios_signing_on_provider' => true]);
        $this->assertTrue($iosItem()['ok']);

        AppExport::save(['ios_signing_on_provider' => false]);
        $this->assertFalse($iosItem()['ok']);

        AppExport::storeCredential('ios_cert', base64_encode('C'), 'c.p12');
        AppExport::storeCredential('ios_provisioning_profile', base64_encode('P'), 'p.mobileprovision');
        $this->assertTrue($iosItem()['ok']);
    }

    public function test_checklist_flags_a_missing_ci_provider(): void
    {
        AppExport::save(['github_repo' => '']);
        config(['services.appexport.github_token' => '']);
        $item = fn () => collect(AppExport::publishChecklist()['Android'])->firstWhere('label', 'GitHub Actions CI configured');
        $this->assertFalse($item()['ok']);

        AppExport::save(['github_repo' => 'SupremeIdeas/NaaraSim']);
        config(['services.appexport.github_token' => 'gh-token']);
        $this->assertTrue($item()['ok']);
    }

    public function test_admin_can_upload_ios_credentials_and_toggle_provider_managed_signing(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->set('iosCert', \Illuminate\Http\UploadedFile::fake()->create('dist.p12', 10))
            ->call('uploadIosCert')
            ->assertHasNoErrors();
        $this->assertTrue(AppExport::hasCredential('ios_cert'));

        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->set('iosProvisioningProfile', \Illuminate\Http\UploadedFile::fake()->create('p.mobileprovision', 10))
            ->call('uploadIosProvisioningProfile')
            ->assertHasNoErrors();
        $this->assertTrue(AppExport::hasCredential('ios_provisioning_profile'));

        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->call('toggleIosSigningOnProvider');
        $this->assertTrue((bool) AppExport::get('ios_signing_on_provider'));
    }

    // --- App Export audit (2026-09-15): §3.3/§3.4 live status + auto-download ---

    public function test_poll_builds_dispatches_ready_event_only_once_on_the_transition(): void
    {
        $build = AppBuild::create([
            'platform' => 'android', 'artifact_type' => 'apk', 'version' => '1.0.0',
            'build_number' => 1, 'status' => AppBuild::STATUS_BUILDING,
        ]);

        $component = Livewire::actingAs($this->admin())->test(AppBuilder::class);

        // Still building — no event.
        $component->call('pollBuilds')->assertNotDispatched('appbuild-ready');

        // Now ready — the transition fires exactly once.
        $build->update(['status' => AppBuild::STATUS_READY, 'artifact_url' => 'https://cdn/app.apk']);
        $component->call('pollBuilds')->assertDispatched('appbuild-ready');

        // A second poll with no NEW transition must not re-fire (would
        // silently re-trigger the browser download on every 3s tick).
        $component->call('pollBuilds')->assertNotDispatched('appbuild-ready');
    }

    public function test_store_cannot_be_marked_live_without_a_url(): void
    {
        Livewire::actingAs($this->admin())->test(AppBuilder::class)
            ->set('form.app_name', 'NaaraSim')
            ->set('form.short_name', 'Naara')
            ->set('form.theme_color', '#0A6E6E')
            ->set('form.background_color', '#0D1B2A')
            ->set('form.version', '1.0.0')
            ->set('form.build_number', 1)
            ->set('form.preloader', 'pulse-logo')
            ->set('form.android_store_live', true)
            ->set('form.android_store_url', '')
            ->call('save')
            ->assertHasErrors('form.android_store_url');

        $this->assertFalse((bool) AppExport::get('android_store_live'));
    }
}
