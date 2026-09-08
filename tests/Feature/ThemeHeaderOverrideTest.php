<?php

namespace Tests\Feature;

use App\Livewire\Admin\ThemePicker;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Support\ThemePreset;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Header editor (owner request, 2026-09-07): "full control" over the
 * header — a colour independent of the theme's own brand colours (which
 * even syncs the mobile browser chrome tint), a 10-40px bottom-corner
 * curve, and glassmorphism depth — for ANY theme, including naara-official
 * (whose own header never gets the curve controls, since it's a fade/blur
 * bar with no bottom edge to round). Mirrors ThemeColorOverrideTest's
 * coverage shape and the "only persist what actually differs" discipline
 * that test's own regression tests added.
 */
class ThemeHeaderOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    // ---- resolveHeaderSettings() --------------------------------------------

    public function test_resolve_header_settings_ignores_an_invalid_bg_value(): void
    {
        $settings = ThemePreset::resolveHeaderSettings(['bg' => 'javascript:alert(1)'], 'aries-contrast');

        $this->assertNull($settings['bg']);
    }

    public function test_resolve_header_settings_accepts_a_valid_bg_channel_triple(): void
    {
        $settings = ThemePreset::resolveHeaderSettings(['bg' => '200 30 30'], 'aries-contrast');

        $this->assertSame('200 30 30', $settings['bg']);
    }

    public function test_resolve_header_settings_never_applies_radius_to_the_default_style(): void
    {
        $settings = ThemePreset::resolveHeaderSettings(['radius_bl' => 30, 'radius_br' => 30], 'default');

        $this->assertSame(0, $settings['radius_bl']);
        $this->assertSame(0, $settings['radius_br']);
    }

    public function test_resolve_header_settings_applies_radius_to_a_named_style(): void
    {
        $settings = ThemePreset::resolveHeaderSettings(['radius_bl' => 25, 'radius_br' => 15], 'aries-contrast');

        $this->assertSame(25, $settings['radius_bl']);
        $this->assertSame(15, $settings['radius_br']);
    }

    public function test_resolve_header_settings_rejects_a_radius_outside_the_valid_range(): void
    {
        $settings = ThemePreset::resolveHeaderSettings(['radius_bl' => 5, 'radius_br' => 999], 'aries-contrast');

        $this->assertSame(0, $settings['radius_bl']);
        $this->assertSame(0, $settings['radius_br']);
    }

    public function test_resolve_header_settings_falls_back_to_the_per_style_blur_default(): void
    {
        $this->assertSame(0, ThemePreset::resolveHeaderSettings([], 'default')['blur']);
        $this->assertSame(0, ThemePreset::resolveHeaderSettings([], 'aries-contrast')['blur']);
        $this->assertSame(8, ThemePreset::resolveHeaderSettings([], 'midnight-signal')['blur']);
        $this->assertSame(8, ThemePreset::resolveHeaderSettings([], 'neon-vertex')['blur']);
        $this->assertSame(12, ThemePreset::resolveHeaderSettings([], 'noir-reserve')['blur']);
    }

    public function test_resolve_header_settings_accepts_an_overridden_blur(): void
    {
        $this->assertSame(20, ThemePreset::resolveHeaderSettings(['blur' => 20], 'midnight-signal')['blur']);
    }

    public function test_resolve_header_settings_rejects_a_blur_outside_the_valid_range(): void
    {
        $this->assertSame(8, ThemePreset::resolveHeaderSettings(['blur' => 999], 'midnight-signal')['blur']);
    }

    // ---- sectionStyleFor() ----------------------------------------------------

    public function test_section_style_for_resolves_an_arbitrary_bag_without_touching_the_active_theme(): void
    {
        $this->assertSame('aries-contrast', ThemePreset::sectionStyleFor(['header' => 'aries-contrast'], 'header'));
        $this->assertSame('default', ThemePreset::sectionStyleFor(['header' => 'not-a-real-style'], 'header'));
        $this->assertSame('default', ThemePreset::sectionStyleFor([], 'header'));
    }

    // ---- headerStyleCss() / headerColorHex() -----------------------------------

    public function test_header_style_css_never_early_returns_for_naara_official(): void
    {
        $this->assertSame('naara-official', ThemePreset::slug());

        $css = ThemePreset::headerStyleCss();

        $this->assertStringContainsString('[data-header-root]', $css);
        $this->assertStringNotContainsString('border-bottom-left-radius', $css);
    }

    public function test_header_style_css_includes_radius_for_a_named_header_style(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'aries-contrast');
        ThemePresetModel::where('slug', 'aries-contrast')->update([
            'header_settings' => ['radius_bl' => 20, 'radius_br' => 30],
        ]);
        ThemePreset::bust();

        $css = ThemePreset::headerStyleCss();

        $this->assertStringContainsString('border-bottom-left-radius:20px', $css);
        $this->assertStringContainsString('border-bottom-right-radius:30px', $css);
    }

    public function test_header_style_css_includes_the_bg_override_scoped_away_from_admin(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'aries-contrast');
        ThemePresetModel::where('slug', 'aries-contrast')->update([
            'header_settings' => ['bg' => '200 30 30'],
        ]);
        ThemePreset::bust();

        $css = ThemePreset::headerStyleCss();

        $this->assertStringContainsString('body:not(.is-admin-surface) [data-header-root]{background:rgb(200 30 30)}', $css);
    }

    public function test_header_style_css_omits_the_bg_rule_when_unset(): void
    {
        $this->assertStringNotContainsString('background:rgb', ThemePreset::headerStyleCss());
    }

    public function test_header_color_hex_is_null_without_an_override(): void
    {
        $this->assertNull(ThemePreset::headerColorHex());
    }

    public function test_header_color_hex_reflects_a_saved_override(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'header_settings' => ['bg' => '255 0 0'],
        ]);
        ThemePreset::bust();

        $this->assertSame('#ff0000', ThemePreset::headerColorHex());
    }

    public function test_the_theme_color_meta_tag_reflects_the_header_override(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'header_settings' => ['bg' => '255 0 0'],
        ]);
        ThemePreset::bust();

        $this->get('/')->assertOk()->assertSee('content="#ff0000"', false);
    }

    // ---- admin-surface exclusion ------------------------------------------------

    /**
     * The plain string "is-admin-surface" legitimately appears on EVERY
     * page — it's part of the `body:not(.is-admin-surface)` CSS selector
     * headerStyleCss() always emits — so these two assert against the
     * <body> tag's own class attribute specifically, not a page-wide
     * substring match.
     */
    private function bodyClassOf(string $html): string
    {
        preg_match('/<body class="([^"]*)"/', $html, $m);

        return $m[1] ?? '';
    }

    public function test_the_admin_panel_body_carries_the_is_admin_surface_class(): void
    {
        Livewire::actingAs($this->admin());

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertStringContainsString('is-admin-surface', $this->bodyClassOf($response->getContent()));
    }

    public function test_a_customer_page_body_never_carries_the_is_admin_surface_class(): void
    {
        $response = $this->get('/')->assertOk();
        $this->assertStringNotContainsString('is-admin-surface', $this->bodyClassOf($response->getContent()));
    }

    // ---- admin editor -------------------------------------------------------

    public function test_edit_header_seeds_the_form_with_current_effective_values(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->assertSet('showHeaderModal', true)
            ->assertSet('headerEditingSlug', 'aries-contrast')
            ->assertSet('headerEditingStyle', 'aries-contrast')
            ->assertSet('headerBg', '')
            ->assertSet('headerRoundBottom', false)
            ->assertSet('headerBlur', 0);
    }

    public function test_edit_header_on_naara_official_resolves_the_default_style(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'naara-official')
            ->assertSet('headerEditingStyle', 'default');
    }

    public function test_edit_header_prefers_an_existing_bg_override_over_no_override(): void
    {
        ThemePresetModel::where('slug', 'aries-contrast')->update([
            'header_settings' => ['bg' => '10 20 30'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->assertSet('headerBg', '#0a141e');
    }

    public function test_save_header_persists_a_valid_bg_hex_as_a_channel_triple(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->set('headerBg', '#ff0000')
            ->call('saveHeader')
            ->assertHasNoErrors()
            ->assertSet('showHeaderModal', false);

        $saved = ThemePresetModel::where('slug', 'aries-contrast')->value('header_settings');
        $this->assertSame('255 0 0', $saved['bg']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.header_updated']);
    }

    public function test_save_header_rejects_a_malformed_bg_hex(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->set('headerBg', 'not-a-hex-color')
            ->call('saveHeader')
            ->assertHasErrors('headerBg');
    }

    public function test_save_header_persists_radius_only_when_round_bottom_is_enabled(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->set('headerRoundBottom', true)
            ->set('headerRadiusBl', 25)
            ->set('headerRadiusBr', 30)
            ->call('saveHeader')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'aries-contrast')->value('header_settings');
        $this->assertSame(25, $saved['radius_bl']);
        $this->assertSame(30, $saved['radius_br']);
    }

    public function test_save_header_omits_radius_when_round_bottom_is_left_unchecked(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->call('saveHeader')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'aries-contrast')->value('header_settings');
        $this->assertArrayNotHasKey('radius_bl', $saved);
        $this->assertArrayNotHasKey('radius_br', $saved);
    }

    public function test_save_header_never_persists_radius_even_if_forced_on_the_default_style(): void
    {
        // Defense in depth: even if headerRoundBottom were somehow true for
        // the 'default' style (the UI never offers this checkbox for it),
        // the server-side validation rules only apply when headerRoundBottom
        // is true — but radius is meaningless for 'default' regardless, so
        // resolveHeaderSettings() (used everywhere the value is READ) always
        // zeroes it out for that style no matter what's stored.
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'naara-official')
            ->set('headerRoundBottom', true)
            ->set('headerRadiusBl', 25)
            ->set('headerRadiusBr', 30)
            ->call('saveHeader')
            ->assertHasNoErrors();

        $row = ThemePresetModel::where('slug', 'naara-official')->first();
        $this->assertSame(25, $row->header_settings['radius_bl']); // stored as submitted...
        $style = ThemePreset::sectionStyleFor($row->section_styles ?? [], 'header');
        $resolved = ThemePreset::resolveHeaderSettings($row->header_settings, $style);
        $this->assertSame(0, $resolved['radius_bl']); // ...but never applied, since style is 'default'.
    }

    public function test_save_header_ignores_a_blur_value_that_matches_the_style_default(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'midnight-signal')
            ->call('saveHeader')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'midnight-signal')->value('header_settings');
        $this->assertArrayNotHasKey('blur', $saved);
    }

    public function test_save_header_persists_a_blur_value_that_differs_from_the_style_default(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'midnight-signal')
            ->set('headerBlur', 20)
            ->call('saveHeader')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'midnight-signal')->value('header_settings');
        $this->assertSame(20, $saved['blur']);
    }

    public function test_reset_header_field_removes_just_the_bg_key(): void
    {
        ThemePresetModel::where('slug', 'aries-contrast')->update([
            'header_settings' => ['bg' => '255 0 0', 'blur' => 15],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->call('resetHeaderField', 'bg')
            ->assertSet('headerBg', '');

        $saved = ThemePresetModel::where('slug', 'aries-contrast')->value('header_settings');
        $this->assertArrayNotHasKey('bg', $saved);
        $this->assertSame(15, $saved['blur']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.header_field_reset']);
    }

    public function test_reset_all_header_clears_every_field(): void
    {
        ThemePresetModel::where('slug', 'aries-contrast')->update([
            'header_settings' => ['bg' => '255 0 0', 'radius_bl' => 20, 'radius_br' => 20, 'blur' => 15],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'aries-contrast')
            ->call('resetAllHeader')
            ->assertSet('headerBg', '')
            ->assertSet('headerRoundBottom', false);

        $saved = ThemePresetModel::where('slug', 'aries-contrast')->value('header_settings');
        $this->assertSame([], $saved);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.header_reset']);
    }

    public function test_edit_header_rejects_an_unknown_theme(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHeader', 'not-a-real-theme')
            ->assertSet('showHeaderModal', false);
    }

    public function test_a_non_admin_cannot_edit_or_save_the_header(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)->assertStatus(403);
    }
}
