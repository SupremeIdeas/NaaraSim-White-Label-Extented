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
