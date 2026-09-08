<?php

namespace Tests\Feature;

use App\Livewire\Admin\Splash as SplashPanel;
use App\Models\Setting;
use App\Models\User;
use App\Support\SplashSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplashTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_defaults_are_off_with_the_supreme_ideas_tagline(): void
    {
        $s = SplashSettings::current();

        $this->assertFalse($s['enabled']);
        $this->assertSame('from Supreme Ideas', $s['brand_tagline']);
        $this->assertSame(1400, $s['duration_ms']);
    }

    public function test_settings_change_reflects_immediately_via_cache_bust(): void
    {
        SplashSettings::current(); // prime cache (disabled)

        Setting::setValue('splash.enabled', true);
        Setting::setValue('splash.product_name', 'NaaraSim');
        Setting::setValue('splash.brand_tagline', 'from Supreme Ideas Agency');

        // No manual flush — the Setting::saved hook cleared the cache.
        $s = SplashSettings::current();
        $this->assertTrue($s['enabled']);
        $this->assertSame('from Supreme Ideas Agency', $s['brand_tagline']);
    }

    public function test_duration_is_capped_so_it_never_feels_slow(): void
    {
        Setting::setValue('splash.duration_ms', 99999);
        $this->assertSame(4000, SplashSettings::current()['duration_ms']);
    }

    public function test_splash_renders_theme_aware_overlay_only_when_enabled(): void
    {
        // Disabled -> nothing.
        $this->blade('<x-splash />')->assertDontSee('z-[9999]', false);

        Setting::setValue('splash.enabled', true);
        Setting::setValue('splash.product_name', 'NaaraSim');

        $this->blade('<x-splash />')
            ->assertSee('NaaraSim')
            ->assertSee('from Supreme Ideas', false)
            ->assertSee('z-[9999]', false)
            ->assertSee('dark:bg-navy', false); // correct-theme paint (no flash)
    }

    public function test_light_and_dark_logos_switch_by_css_so_neither_bleeds_into_the_wrong_theme(): void
    {
        Setting::setValue('splash.enabled', true);
        Setting::setValue('splash.product_logo_light', 'https://cdn.naarasim.com/product-light.svg');
        Setting::setValue('splash.product_logo_dark', 'https://cdn.naarasim.com/product-dark.svg');
        Setting::setValue('splash.brand_logo_light', 'https://cdn.naarasim.com/brand-light.svg');
        Setting::setValue('splash.brand_logo_dark', 'https://cdn.naarasim.com/brand-dark.svg');

        $html = $this->blade('<x-splash />');

        // The light logo is shown only in light mode (block dark:hidden)…
        $html->assertSee('src="https://cdn.naarasim.com/product-light.svg"', false)
            ->assertSeeInOrder(['product-light.svg', 'dark:hidden'], false);
        // …and the dark logo only in dark mode (hidden dark:block).
        $html->assertSee('src="https://cdn.naarasim.com/product-dark.svg"', false)
            ->assertSee('dark:block', false)
            // Brand logos follow the same rule.
            ->assertSee('src="https://cdn.naarasim.com/brand-light.svg"', false)
            ->assertSee('src="https://cdn.naarasim.com/brand-dark.svg"', false);
    }

    public function test_a_missing_logo_for_one_mode_shows_the_wordmark_not_the_wrong_logo(): void
    {
        // Only a light logo is set; in dark mode there must be NO product image
        // (so the light-mode logo never appears on the dark background).
        Setting::setValue('splash.enabled', true);
        Setting::setValue('splash.product_logo_light', 'https://cdn.naarasim.com/only-light.svg');
        Setting::setValue('splash.product_name', 'NaaraSim');

        $html = $this->blade('<x-splash />');
        $html->assertSee('src="https://cdn.naarasim.com/only-light.svg"', false)
            ->assertDontSee('dark:block', false)  // no dark-mode <img> rendered
            ->assertSee('NaaraSim');               // wordmark still shows in dark mode
    }

    public function test_admin_can_toggle_and_rebrand_the_splash_without_redeploy(): void
    {
        Livewire::actingAs($this->admin())->test(SplashPanel::class)
            ->set('enabled', true)
            ->set('product_name', 'NaaraSim')
            ->set('brand_tagline', 'from Supreme Ideas')
            ->set('product_logo_light', 'https://cdn.naarasim.com/logo-light.svg')
            ->set('duration_ms', 1200)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', 'Splash screen saved — it updates immediately, no redeploy.');

        $s = SplashSettings::current();
        $this->assertTrue($s['enabled']);
        $this->assertSame('https://cdn.naarasim.com/logo-light.svg', $s['product_logo_light']);
        $this->assertSame(1200, $s['duration_ms']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'splash.updated']);
    }

    public function test_invalid_logo_url_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(SplashPanel::class)
            ->set('product_logo_light', 'not-a-url')
            ->call('save')
            ->assertHasErrors('product_logo_light');
    }

    public function test_admin_can_upload_a_logo_and_it_is_stored_and_wired_to_the_url(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]); // force server disk

        Livewire::actingAs($this->admin())->test(SplashPanel::class)
            ->set('product_logo_dark_file', \Illuminate\Http\UploadedFile::fake()->image('dark.png', 300, 100))
            ->assertSet('uploadError', null)
            // The uploaded file's URL is wired into the dark-logo field…
            ->assertSet('product_logo_dark', fn ($v) => is_string($v) && str_contains($v, '/storage/splash/'));

        $this->assertSame(1, count(\Illuminate\Support\Facades\Storage::disk('public')->allFiles('splash')));
    }
}
