<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 26 — Brand & design system (logos + fonts).
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        BrandSettings::flush();
    }

    public function test_ships_default_brand_logos_but_no_admin_override(): void
    {
        $this->assertSame(config('app.name'), BrandSettings::name());
        // The official logos ship as defaults (public/brand/*), so a product
        // logo always displays — no admin upload is set yet, though.
        $this->assertTrue(BrandSettings::hasProductLogo());
        $this->assertNull(BrandSettings::logo('product', 'light')); // no admin override
        $this->assertSame('/brand/naarasim-product-light.png', BrandSettings::resolvedLogo('product', 'light'));
        $this->assertSame('/brand/naarasim-favicon.png', BrandSettings::favicon());
        // Each sub-brand ships its own mark: family (Naara umbrella) + gift.
        $this->assertSame('/brand/naara-family-light.png', BrandSettings::resolvedLogo('family', 'light'));
        $this->assertSame('/brand/naara-family-dark.png', BrandSettings::resolvedLogo('family', 'dark'));
        $this->assertSame('/brand/naara-gift-light.png', BrandSettings::resolvedLogo('gift', 'light'));
    }

    public function test_a_saved_logo_url_is_returned_and_theme_variants_fall_back(): void
    {
        Setting::setValue('brand.logo_product_light', 'https://cdn.test/logo-light.png', 'brand');
        BrandSettings::flush();

        $this->assertTrue(BrandSettings::hasProductLogo());
        $this->assertSame('https://cdn.test/logo-light.png', BrandSettings::logo('product', 'light'));
        // logo() returns the exact variant (null if unset); the <x-brand-logo>
        // component does the light<->dark cross-fallback so a logo always shows.
        $this->assertNull(BrandSettings::logo('product', 'dark'));

        $html = view('components.brand-logo', ['variant' => 'product', 'class' => 'h-8'])->render();
        $this->assertStringContainsString('logo-light.png', $html); // used for the dark <img> too
    }

    public function test_brand_logo_component_falls_back_to_the_wordmark(): void
    {
        $html = view('components.brand-logo', ['variant' => 'product', 'class' => 'h-8', 'fallbackIcon' => 'signal'])->render();
        $this->assertStringContainsString(config('app.name'), $html); // wordmark text present
    }

    public function test_branding_page_is_admin_only_and_saves_the_brand_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/branding')->assertNotFound();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_name', 'NaaraSim Global')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('NaaraSim Global', BrandSettings::name());
    }

    public function test_naara_gift_has_its_own_admin_settable_logo(): void
    {
        // The Naara Gift mark ships as a default; an admin upload overrides it.
        $this->assertSame('/brand/naara-gift-light.png', BrandSettings::resolvedLogo('gift', 'light'));
        $this->assertNull(BrandSettings::logo('gift', 'light')); // no admin override yet

        Setting::setValue('brand.logo_gift_light', 'https://cdn.test/naara-gift.png', 'brand');
        BrandSettings::flush();

        $this->assertSame('https://cdn.test/naara-gift.png', BrandSettings::resolvedLogo('gift', 'light'));
        // Cross-theme fallback: one upload serves both light and dark.
        $this->assertSame('https://cdn.test/naara-gift.png', BrandSettings::resolvedLogo('gift', 'dark'));

        $html = view('components.brand-logo', ['variant' => 'gift', 'label' => 'Naara Gift', 'fallbackIcon' => 'gift'])->render();
        $this->assertStringContainsString('naara-gift.png', $html);
    }

    public function test_the_naara_family_mark_is_admin_settable_and_overrides_the_default(): void
    {
        // The umbrella Naara mark ships as a default (home dashboard, marketing,
        // Aurora welcome) and an admin upload overrides it.
        $this->assertSame('/brand/naara-family-light.png', BrandSettings::resolvedLogo('family', 'light'));
        $this->assertNull(BrandSettings::logo('family', 'light'));

        Setting::setValue('brand.logo_family_dark', 'https://cdn.test/naara.png', 'brand');
        BrandSettings::flush();

        // Admin upload wins; the light variant cross-falls back to the upload.
        $this->assertSame('https://cdn.test/naara.png', BrandSettings::resolvedLogo('family', 'dark'));

        $html = view('components.brand-logo', ['variant' => 'family', 'label' => 'Naara'])->render();
        $this->assertStringContainsString('naara.png', $html);
    }

    public function test_admin_can_upload_a_naara_family_logo(): void
    {
        Storage::fake('wasabi');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_name', 'NaaraSim')
            ->set('family_light', File::image('naara.png', 240, 90))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(BrandSettings::logo('family', 'light'));
    }

    public function test_admin_can_upload_a_naara_gift_logo(): void
    {
        Storage::fake('wasabi');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_name', 'NaaraSim')
            ->set('gift_light', File::image('gift.png', 200, 80))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(BrandSettings::resolvedLogo('gift', 'light'));
    }

    public function test_the_brand_fonts_are_present_and_wired(): void
    {
        $this->assertFileExists(public_path('fonts/supreme-display.ttf'));
        $this->assertFileExists(public_path('fonts/didact-gothic.woff2'));
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('Supreme Display', $css);
        $this->assertStringContainsString('Didact Gothic', $css);
        // app.css must actually read the override variable, not just theme() —
        // this is the dead-CSS-variable bug the admin font system depends on.
        $this->assertStringContainsString('var(--font-sans)', $css);
        $this->assertStringContainsString('var(--font-display)', $css);
    }

    public function test_unconfigured_install_has_no_font_override(): void
    {
        $this->assertFalse(BrandSettings::hasFontOverride());
        $this->assertFalse(BrandSettings::usesGoogleFont());
        $this->assertSame('', BrandSettings::fontCss());
        $this->assertNull(BrandSettings::googleFontsHref());

        // No injected <style id="brand-font-vars"> and no Google Fonts <link>
        // on a fresh install — byte-identical to before this feature shipped.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('brand-font-vars', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_admin_can_set_a_google_font_for_titles_and_body(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('font_display_source', 'google')
            ->set('font_display_google', 'Poppins')
            ->set('font_sans_source', 'google')
            ->set('font_sans_google', 'Inter')
            ->call('saveFonts')
            ->assertHasNoErrors();

        $this->assertTrue(BrandSettings::hasFontOverride());
        $this->assertTrue(BrandSettings::usesGoogleFont());
        $this->assertSame('Poppins', BrandSettings::googleFontName('display'));
        $this->assertSame('Inter', BrandSettings::googleFontName('sans'));

        $css = BrandSettings::fontCss();
        $this->assertStringContainsString("--font-display: 'Poppins'", $css);
        $this->assertStringContainsString("--font-sans: 'Inter'", $css);

        $href = BrandSettings::googleFontsHref();
        $this->assertStringContainsString('fonts.googleapis.com/css2', $href);
        $this->assertStringContainsString('family=Poppins', $href);
        $this->assertStringContainsString('family=Inter', $href);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('brand-font-vars', $html);
        $this->assertStringContainsString('fonts.googleapis.com', $html);

        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('fonts.googleapis.com', $csp);
        $this->assertStringContainsString('fonts.gstatic.com', $csp);
    }

    public function test_google_font_name_is_strictly_validated(): void
    {
        $this->assertTrue(BrandSettings::isValidGoogleFontName('Poppins'));
        $this->assertTrue(BrandSettings::isValidGoogleFontName('Noto Sans JP'));
        $this->assertFalse(BrandSettings::isValidGoogleFontName('Evil";}</style><script>alert(1)</script>'));
        $this->assertFalse(BrandSettings::isValidGoogleFontName("Poppins');}body{color:red"));

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('font_display_source', 'google')
            ->set('font_display_google', 'Evil"; } body { display: none; } .x {')
            ->call('saveFonts')
            ->assertHasErrors('font_display_google');
    }

    public function test_admin_can_upload_a_custom_font(): void
    {
        Storage::fake('wasabi');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('font_sans_source', 'custom')
            ->set('font_sans_custom', File::create('body-font.woff2', 50))
            ->call('saveFonts')
            ->assertHasNoErrors();

        $this->assertSame('custom', BrandSettings::fontSource('sans'));
        $this->assertNotNull(BrandSettings::customFontUrl('sans'));

        $css = BrandSettings::fontCss();
        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString(BrandSettings::CUSTOM_FONT_FAMILY['sans'], $css);
        $this->assertStringContainsString("--font-sans: '".BrandSettings::CUSTOM_FONT_FAMILY['sans']."'", $css);
        // A custom upload never trusts admin text as a CSS family name — a
        // fixed, code-generated name sidesteps that injection risk entirely.
        $this->assertFalse(BrandSettings::usesGoogleFont());
    }

    public function test_resetting_fonts_returns_to_the_naara_defaults(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('font_display_source', 'google')
            ->set('font_display_google', 'Poppins')
            ->call('saveFonts')
            ->assertHasNoErrors();
        $this->assertTrue(BrandSettings::hasFontOverride());

        Livewire::actingAs($admin)->test(Branding::class)
            ->call('resetFonts');

        $this->assertFalse(BrandSettings::hasFontOverride());
        $this->assertSame('', BrandSettings::fontSource('display'));
    }
}
