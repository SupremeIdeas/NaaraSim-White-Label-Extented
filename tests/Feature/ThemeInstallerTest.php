<?php

namespace Tests\Feature;

use App\Jobs\ApplyUpdateJob;
use App\Livewire\Admin\Updater;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\ThemeInstaller;
use App\Support\ThemePreset;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * Batch 3 — the theme installer. Proves the design principle from the
 * blueprint's own §0: a theme is paint, never plumbing, so installing one is a
 * same-request DB write + a few file copies, with reject-don't-silently-drop
 * validation at the boundaries that actually matter (built-in slug collision,
 * layout_variants, icon_family) and a clear warning (not a silent gap) for the
 * one thing the render path itself already drops safely (an unapproved font).
 */
class ThemeInstallerTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    private string $pkgDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);

        $this->pkgDir = storage_path('app/testing/theme-'.uniqid());
        File::ensureDirectoryExists($this->pkgDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pkgDir);
        parent::tearDown();
    }

    /** A tiny valid WebP-ish byte string — content doesn't matter, only that it round-trips as bytes. */
    private function fakeImageBytes(): string
    {
        return "RIFF\x00\x00\x00\x00WEBPVP8 fake-pixels-for-test";
    }

    /**
     * @param  array<string,mixed>  $themeOverrides  merged into a valid baseline theme.json
     */
    private function buildThemePackage(array $themeOverrides = [], array $buildOverrides = []): string
    {
        $theme = array_replace_recursive([
            'slug' => 'naara-line-persona',
            'name' => 'Naara Line — Bold Persona',
            'persona' => 'High-energy, gold-forward look.',
            'tokens' => [
                'colors' => ['primary' => '12 138 128', 'accent' => '212 175 55', 'navy' => '10 20 38'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => 'Supreme Display', 'sans' => 'Figtree'],
                'surface' => ['card_border_opacity' => '0.12'],
            ],
            'icon_family' => ['style' => '3d', 'set' => 'naara-line'],
            'hero_assets' => ['dashboard' => 'assets/hero-dashboard.webp'],
            'layout_variants' => ['dashboard' => 'variant-b'],
            'sort_order' => 20,
        ], $themeOverrides);

        $files = [
            ['path' => 'theme.json', 'action' => 'add', 'content' => json_encode($theme, JSON_UNESCAPED_SLASHES)],
        ];
        if (isset($theme['hero_assets']) && is_array($theme['hero_assets'])) {
            foreach ($theme['hero_assets'] as $ref) {
                if (is_string($ref)) {
                    $files[] = ['path' => $ref, 'action' => 'add', 'content' => $this->fakeImageBytes()];
                }
            }
        }

        return app(PackageBuilder::class)->build(array_merge([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'package_type' => 'theme',
            'files' => $files,
        ], $buildOverrides));
    }

    // --- Round-trip ---

    public function test_a_valid_theme_package_previews_and_installs(): void
    {
        Storage::fake('public');

        $pkg = $this->buildThemePackage();

        $preview = app(ThemeInstaller::class)->preview($pkg);
        $this->assertSame('naara-line-persona', $preview->slug);
        $this->assertSame('12 138 128', $preview->colorSwatches['primary'] ?? null);
        $this->assertSame([], $preview->fontWarnings);

        $row = app(ThemeInstaller::class)->install($pkg, null);

        $this->assertInstanceOf(ThemePresetModel::class, $row);
        $this->assertSame('naara-line-persona', $row->slug);
        $this->assertFalse($row->is_built_in);
        $this->assertSame('variant-b', $row->layout_variants['dashboard'] ?? null);

        // The stored hero URL must pass heroFor()'s own render-time pattern.
        Setting::setValue(ThemePreset::SETTING_KEY, 'naara-line-persona');
        ThemePreset::bust();
        $this->assertNotNull(ThemePreset::heroFor('dashboard'));
        $this->assertSame('theme-naara-line-persona', ThemePreset::bodyClass());

        Storage::disk('public')->assertExists('themes/naara-line-persona/dashboard.webp');
    }

    public function test_reinstalling_the_same_slug_updates_and_preserves_admin_set_sort_order(): void
    {
        Storage::fake('public');

        $pkg1 = $this->buildThemePackage();
        $row = app(ThemeInstaller::class)->install($pkg1, null);
        $this->assertSame(20, $row->sort_order);

        // Admin re-orders it by hand after install.
        $row->update(['sort_order' => 5]);

        // A theme UPDATE package ships a different sort_order — it must be ignored.
        $pkg2 = $this->buildThemePackage(['name' => 'Naara Line — Refreshed', 'sort_order' => 99]);
        $updated = app(ThemeInstaller::class)->install($pkg2, null);

        $this->assertSame($row->id, $updated->id, 'should update the same row, not duplicate it');
        $this->assertSame('Naara Line — Refreshed', $updated->name);
        $this->assertSame(5, $updated->sort_order, 'admin-set ordering must survive a theme update');
        $this->assertSame(1, ThemePresetModel::where('slug', 'naara-line-persona')->count());
    }

    // --- Font allow-list warning (installs anyway) ---

    public function test_an_unapproved_font_surfaces_a_warning_but_still_installs(): void
    {
        Storage::fake('public');

        $pkg = $this->buildThemePackage([
            'tokens' => ['typography' => ['display' => 'Poppins']],
            'hero_assets' => [],
        ]);

        $preview = app(ThemeInstaller::class)->preview($pkg);
        $this->assertNotEmpty($preview->fontWarnings);
        $this->assertStringContainsString('Poppins', $preview->fontWarnings[0]);

        $row = app(ThemeInstaller::class)->install($pkg, null);
        $this->assertSame('Poppins', $row->tokens['typography']['display'] ?? null, 'the raw value is stored');

        // But it never renders — emitVars() still silently drops it, exactly as documented.
        Setting::setValue(ThemePreset::SETTING_KEY, $row->slug);
        ThemePreset::bust();
        $this->assertStringNotContainsString('Poppins', ThemePreset::styleCss());
        // The theme's OTHER tokens still apply.
        $this->assertStringContainsString('--brand-primary:12 138 128', ThemePreset::styleCss());
    }

    // --- Reject, don't silently drop ---

    public function test_a_built_in_slug_is_rejected_outright(): void
    {
        $pkg = $this->buildThemePackage(['slug' => 'naara-official', 'hero_assets' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/built-in/i');

        app(ThemeInstaller::class)->install($pkg, null);
    }

    public function test_an_invalid_layout_variant_is_rejected_not_silently_dropped(): void
    {
        $pkg = $this->buildThemePackage(['layout_variants' => ['dashboard' => 'variant-z'], 'hero_assets' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/variant-z/');

        app(ThemeInstaller::class)->install($pkg, null);

        $this->assertNull(ThemePresetModel::where('slug', 'naara-line-persona')->first());
    }

    public function test_an_invalid_icon_family_is_rejected(): void
    {
        $pkg = $this->buildThemePackage(['icon_family' => ['style' => 'flat', 'set' => 'x'], 'hero_assets' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/icon_family/');

        app(ThemeInstaller::class)->install($pkg, null);
    }

    public function test_an_invalid_slug_shape_is_rejected(): void
    {
        $pkg = $this->buildThemePackage(['slug' => 'Not A Slug!', 'hero_assets' => []]);

        $this->expectException(\RuntimeException::class);

        app(ThemeInstaller::class)->install($pkg, null);
    }

    // --- Same trust gate as every other package type ---

    public function test_a_tampered_theme_package_is_rejected_at_the_same_verifier_step(): void
    {
        $pkg = $this->buildThemePackage(['hero_assets' => []]);

        $zip = new ZipArchive;
        $zip->open($pkg);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['version'] = '2099.01.01-1';
        $zip->deleteName('manifest.json');
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/rejected|signature/i');

        app(ThemeInstaller::class)->install($pkg, null);
    }

    public function test_a_code_package_is_rejected_by_the_theme_installer(): void
    {
        $pkg = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'files' => [['path' => 'app/Foo.php', 'action' => 'add', 'content' => '<?php']],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not a theme/');

        app(ThemeInstaller::class)->preview($pkg);
    }

    // --- Asset integrity: reject and clean up rather than save a dead link ---

    public function test_a_missing_referenced_asset_is_rejected_with_no_orphaned_file(): void
    {
        Storage::fake('public');

        // theme.json references an asset that was never actually added to the package.
        $theme = [
            'slug' => 'broken-assets-theme', 'name' => 'Broken', 'icon_family' => ['style' => '3d', 'set' => 'default'],
            'hero_assets' => ['dashboard' => 'assets/does-not-exist.webp'],
        ];
        $pkg = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'package_type' => 'theme',
            'files' => [['path' => 'theme.json', 'action' => 'add', 'content' => json_encode($theme)]],
        ]);

        try {
            app(ThemeInstaller::class)->install($pkg, null);
            $this->fail('expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does-not-exist.webp', $e->getMessage());
        }

        $this->assertNull(ThemePresetModel::where('slug', 'broken-assets-theme')->first());
        Storage::disk('public')->assertDirectoryEmpty('themes/broken-assets-theme');
    }

    // --- Livewire screen ---

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_the_themes_tab_previews_then_installs_and_never_touches_the_apply_job(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();

        $pkg = $this->buildThemePackage();
        $upload = UploadedFile::fake()->createWithContent('theme.naaraupdate', File::get($pkg));

        Livewire::actingAs($this->superAdmin())
            ->test(Updater::class)
            ->set('tab', 'themes')
            ->set('themePackage', $upload)
            ->call('verifyTheme')
            ->assertSet('themeVerifyError', null)
            ->assertSet('themePreview.slug', 'naara-line-persona')
            ->call('installTheme')
            ->assertSet('themeStatus', fn ($s) => str_contains((string) $s, 'installed'));

        $this->assertNotNull(ThemePresetModel::where('slug', 'naara-line-persona')->first());
        Queue::assertNotPushed(ApplyUpdateJob::class);
    }

    public function test_a_theme_package_uploaded_to_the_code_tab_is_routed_to_the_themes_tab(): void
    {
        Storage::fake('local');
        Queue::fake();

        $pkg = $this->buildThemePackage(['hero_assets' => []]);
        $upload = UploadedFile::fake()->createWithContent('theme.naaraupdate', File::get($pkg));

        Livewire::actingAs($this->superAdmin())
            ->test(Updater::class)
            ->set('package', $upload)
            ->call('verifyPackage')
            ->assertSet('verified', null)
            ->assertSet('verifyError', fn ($e) => str_contains((string) $e, 'Themes tab'));

        Queue::assertNotPushed(ApplyUpdateJob::class);
    }

    public function test_activate_and_remove_theme_actions(): void
    {
        Storage::fake('public');

        $pkg = $this->buildThemePackage();
        app(ThemeInstaller::class)->install($pkg, null);

        Livewire::actingAs($this->superAdmin())
            ->test(Updater::class)
            ->set('tab', 'themes')
            ->call('activateTheme', 'naara-line-persona')
            ->assertSet('themeVerifyError', null);

        $this->assertSame('naara-line-persona', Setting::getValue(ThemePreset::SETTING_KEY));

        Livewire::actingAs($this->superAdmin())
            ->test(Updater::class)
            ->set('tab', 'themes')
            ->call('removeTheme', 'naara-line-persona');

        $this->assertNull(ThemePresetModel::where('slug', 'naara-line-persona')->first());
        // Removing the ACTIVE theme must reset the active-theme setting, never
        // leave it dangling at a deleted slug.
        $this->assertSame(ThemePreset::DEFAULT_SLUG, Setting::getValue(ThemePreset::SETTING_KEY));
    }

    public function test_a_built_in_theme_cannot_be_removed_via_the_screen(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Updater::class)
            ->set('tab', 'themes')
            ->call('removeTheme', ThemePreset::DEFAULT_SLUG)
            ->assertSet('themeVerifyError', fn ($e) => str_contains((string) $e, 'Built-in'));

        $this->assertNotNull(ThemePresetModel::where('slug', ThemePreset::DEFAULT_SLUG)->first());
    }

    public function test_a_non_super_admin_cannot_open_the_updater_themes_tab(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Updater::class)->assertStatus(403);
    }
}
