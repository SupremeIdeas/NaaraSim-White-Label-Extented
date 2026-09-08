<?php

namespace Tests\Feature;

use App\Jobs\ApplyUpdateJob;
use App\Livewire\Admin\WhiteLabelUpdater;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\UpdateApplier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 5 §3/§6 — the white-label subscriber's Updater screen. Proves the two
 * entry points genuinely lead to the identical apply pipeline, and — the
 * hardest requirement in this batch — that the manual-upload fallback keeps
 * working end-to-end even with NAARA_UPDATE_API_TOKEN unset/invalid, so the
 * platform update capability never has a single point of failure through the
 * distribution API alone.
 */
class WhiteLabelUpdaterScreenTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    private string $pkgDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);

        $this->pkgDir = storage_path('app/testing/wl-screen-'.uniqid());
        File::ensureDirectoryExists($this->pkgDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pkgDir);
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function buildPackage(array $overrides = []): string
    {
        return app(PackageBuilder::class)->build(array_merge([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'files' => [['path' => 'app/Foo.php', 'action' => 'add', 'content' => '<?php // '.uniqid()]],
        ], $overrides));
    }

    public function test_a_non_super_admin_cannot_open_the_screen(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(WhiteLabelUpdater::class)->assertStatus(403);
    }

    // --- Pull flow (code): check -> pick -> download+apply via the SAME queued job ---

    public function test_checking_for_updates_shows_the_error_state_when_not_configured(): void
    {
        // No NAARA_UPDATE_API_TOKEN / base URL configured — the default state
        // for a freshly-forked instance before Batch 6 registration.
        config()->set('updater.original_platform_base_url', null);
        config()->set('updater.api_token', null);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelUpdater::class)
            ->call('checkForCodeUpdates')
            ->assertSet('availableUpdates.ok', false);
    }

    public function test_pull_and_apply_downloads_verifies_and_queues_the_same_apply_job(): void
    {
        Queue::fake();
        Setting::setValue(UpdateApplier::VERSION_SETTING, '2026.09.01-1');
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'tok');

        $built = $this->buildPackage();
        Http::fake(['master.example.test/*' => Http::response(File::get($built), 200)]);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelUpdater::class)
            ->call('pullAndApply', 'some-package-id')
            ->assertSet('pullStatus', fn ($s) => str_contains((string) $s, 'Downloaded and queued'));

        Queue::assertPushed(ApplyUpdateJob::class);
    }

    public function test_pull_and_apply_fails_cleanly_when_the_download_fails_verification(): void
    {
        Queue::fake();
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'tok');

        Http::fake(['master.example.test/*' => Http::response('not a real package', 200)]);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelUpdater::class)
            ->call('pullAndApply', 'some-package-id')
            ->assertSet('pullStatus', fn ($s) => str_contains((string) $s, 'Download failed'));

        Queue::assertNotPushed(ApplyUpdateJob::class);
    }

    // --- Manual upload fallback: must work with NO API configuration at all ---

    public function test_manual_upload_still_works_end_to_end_with_no_api_token_configured(): void
    {
        Queue::fake();
        config()->set('updater.original_platform_base_url', null);
        config()->set('updater.api_token', null);

        $built = $this->buildPackage();
        $upload = UploadedFile::fake()->createWithContent('update.naaraupdate', File::get($built));

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelUpdater::class)
            ->set('package', $upload)
            ->call('verifyPackage')
            ->assertSet('verifyError', null)
            ->assertSet('verified.files', 1)
            ->call('applyPackage')
            ->assertSet('status', fn ($s) => str_contains((string) $s, 'queued'));

        Queue::assertPushed(ApplyUpdateJob::class);
    }

    // --- Themes: pull flow reports outcome inline (synchronous install) ---

    public function test_pull_and_install_theme_downloads_installs_and_reports_success(): void
    {
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'tok');

        $theme = ['slug' => 'pulled-theme', 'name' => 'Pulled', 'icon_family' => ['style' => '3d', 'set' => 'default']];
        $built = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'package_type' => 'theme',
            'files' => [['path' => 'theme.json', 'action' => 'add', 'content' => json_encode($theme)]],
        ]);

        Http::fake([
            'master.example.test/*themes*download*' => Http::response(File::get($built), 200),
            'master.example.test/*report*' => Http::response(['message' => 'Recorded.']),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelUpdater::class)
            ->call('pullAndInstallTheme', 'theme-pkg-id')
            ->assertSet('themeStatus', fn ($s) => str_contains((string) $s, 'installed'));

        $this->assertNotNull(ThemePresetModel::where('slug', 'pulled-theme')->first());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'report') && $r['status'] === 'applied');
    }

    public function test_pull_and_install_theme_reports_failure_when_install_is_rejected(): void
    {
        // Seed the built-in row so there's an actual slug to collide with.
        $this->seed(\Database\Seeders\ThemePresetSeeder::class);
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'tok');

        // A theme claiming the built-in slug — ThemeInstaller rejects this outright.
        $theme = ['slug' => 'naara-official', 'name' => 'X', 'icon_family' => ['style' => '3d', 'set' => 'default']];
        $built = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'package_type' => 'theme',
            'files' => [['path' => 'theme.json', 'action' => 'add', 'content' => json_encode($theme)]],
        ]);

        Http::fake([
            'master.example.test/*themes*download*' => Http::response(File::get($built), 200),
            'master.example.test/*report*' => Http::response(['message' => 'Recorded.']),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelUpdater::class)
            ->call('pullAndInstallTheme', 'theme-pkg-id')
            ->assertSet('themeStatus', fn ($s) => str_contains((string) $s, 'Install failed'));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'report') && $r['status'] === 'failed');
    }
}
