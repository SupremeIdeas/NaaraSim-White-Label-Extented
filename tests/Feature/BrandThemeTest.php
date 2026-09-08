<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** Module 26 — admin-editable brand colours, roundness, and preloader. */
class BrandThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        BrandSettings::flush();
    }

    public function test_hex_to_channels_and_darken_are_correct_and_safe(): void
    {
        $this->assertSame('10 110 110', BrandSettings::hexToChannels('#0A6E6E'));
        $this->assertSame('212 160 23', BrandSettings::hexToChannels('D4A017')); // no hash
        $this->assertSame('255 255 255', BrandSettings::hexToChannels('#fff'));   // shorthand
        $this->assertNull(BrandSettings::hexToChannels('not-a-colour'));
        $this->assertNull(BrandSettings::hexToChannels('#12'));
    }

    public function test_no_override_emits_no_theme_css(): void
    {
        $this->assertFalse(BrandSettings::hasThemeOverride());
        $this->assertSame('', BrandSettings::themeCss());
    }

    public function test_saving_colours_emits_a_root_override_with_channel_triples(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('color_primary', '#112233')
            ->set('color_accent', '#D4A017')
            ->set('color_navy', '#0D1B2A')
            ->set('color_action', '#E8412A')
            ->set('radius', '1rem')
            ->set('preloader_enabled', true)
            ->call('saveTheme')
            ->assertHasNoErrors();

        BrandSettings::flush();
        $css = BrandSettings::themeCss();
        $this->assertStringContainsString('--brand-primary: 17 34 51;', $css);
        $this->assertStringContainsString('--brand-primary-dark:', $css); // auto-derived
        $this->assertStringContainsString('--brand-radius: 1rem;', $css);
        $this->assertTrue(BrandSettings::preloaderEnabled());

        // It actually renders into the page head.
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('--brand-primary: 17 34 51', false);
    }

    public function test_the_chosen_theme_survives_spa_navigation(): void
    {
        // The user's dark/light choice must outlive wire:navigate — the incoming
        // server-rendered <html> has no `dark` class, so the layout must re-apply
        // the stored theme on every livewire:navigated, not only on full load.
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('window.applyStoredTheme', $html);
        $this->assertStringContainsString("addEventListener('livewire:navigated', window.applyStoredTheme)", $html);
    }

    public function test_invalid_hex_is_rejected(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('color_primary', 'teal')
            ->call('saveTheme')
            ->assertHasErrors(['color_primary']);
    }

    public function test_reset_returns_the_defaults(): void
    {
        Setting::setValue('brand.color_primary', '#112233', 'brand');
        BrandSettings::flush();
        $this->assertTrue(BrandSettings::hasThemeOverride());

        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(Branding::class)->call('resetTheme');

        BrandSettings::flush();
        $this->assertFalse(BrandSettings::hasThemeOverride());
        $this->assertSame('#0A6E6E', BrandSettings::color('primary'));
    }

    public function test_preloader_renders_only_when_enabled(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('nx-preloader', false);

        Setting::setValue('brand.preloader_enabled', true, 'brand');
        BrandSettings::flush();
        $this->get('/login')->assertOk()->assertSee('nx-preloader', false);
    }

    public function test_preloader_defaults_to_the_pulsing_logo_mark(): void
    {
        // Default style is the impulse logo; an unknown value falls back to it.
        $this->assertSame('pulse-logo', BrandSettings::preloaderStyle());
        Setting::setValue('brand.preloader_style', 'nonsense', 'brand');
        BrandSettings::flush();
        $this->assertSame('pulse-logo', BrandSettings::preloaderStyle());

        // When enabled, the overlay carries the pulsing favicon mark (not a ring).
        Setting::setValue('brand.preloader_enabled', true, 'brand');
        Setting::setValue('brand.preloader_style', 'pulse-logo', 'brand');
        BrandSettings::flush();
        $this->get('/login')->assertOk()
            ->assertSee('nx-pulse', false)
            ->assertSee('naarasim-favicon.png', false);
    }

    public function test_admin_can_choose_the_preloader_style_and_it_is_validated(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('color_primary', '#0A6E6E')->set('color_accent', '#D4A017')
            ->set('color_navy', '#0D1B2A')->set('color_action', '#E8412A')->set('radius', '0.5rem')
            ->set('preloader_enabled', true)
            ->set('preloader_style', 'bars')
            ->call('saveTheme')
            ->assertHasNoErrors();
        BrandSettings::flush();
        $this->assertSame('bars', BrandSettings::preloaderStyle());

        // A style outside the whitelist is rejected.
        Livewire::actingAs($admin)->test(Branding::class)
            ->set('preloader_style', 'sparkles')
            ->call('saveTheme')
            ->assertHasErrors(['preloader_style']);
    }

    public function test_the_progress_preloader_style_is_selectable_and_renders(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('color_primary', '#0A6E6E')->set('color_accent', '#D4A017')
            ->set('color_navy', '#0D1B2A')->set('color_action', '#E8412A')->set('radius', '0.5rem')
            ->set('preloader_enabled', true)
            ->set('preloader_style', 'progress')
            ->call('saveTheme')
            ->assertHasNoErrors();
        BrandSettings::flush();
        $this->assertSame('progress', BrandSettings::preloaderStyle());

        // The overlay renders the determinate progress bar (not the spinner).
        $html = Blade::render('<x-brand-preloader />');
        $this->assertStringContainsString('nx-pre-progress', $html);
    }

    public function test_the_scoped_action_loader_uses_the_pulse_motif(): void
    {
        // <x-brand-loader> reacts to wire:loading and reuses the shared pulse mark.
        $html = Blade::render('<x-brand-loader target="purchase" :overlay="true" />');
        $this->assertStringContainsString('wire:loading', $html);
        $this->assertStringContainsString('wire:target="purchase"', $html);
        $this->assertStringContainsString('nx-pulse', $html);
    }

    public function test_radius_is_clamped_to_a_safe_value(): void
    {
        // A malformed radius can never reach the injected CSS.
        Setting::setValue('brand.color_primary', '#0A6E6E', 'brand');
        Setting::setValue('brand.radius', '9px;} body{display:none', 'brand');
        BrandSettings::flush();

        $css = BrandSettings::themeCss();
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringContainsString('--brand-radius: 0.5rem;', $css);
    }
}
