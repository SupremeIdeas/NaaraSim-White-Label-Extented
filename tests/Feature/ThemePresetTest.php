<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Support\ThemePreset;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NAARA THEME SYSTEM — Batch 1. The theme engine is paint-only and must fail
 * safe: naara-official emits NO css, junk tokens never reach the page, and a
 * missing row falls back to the built-in rather than blanking the platform.
 */
class ThemePresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ThemePreset::bust();
    }

    public function test_fresh_install_with_no_rows_falls_back_to_built_in(): void
    {
        // Empty table, no active-theme setting → synthetic naara-official.
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('', ThemePreset::styleCss(), 'Built-in emits no override.');
        $this->assertSame('theme-naara-official', ThemePreset::bodyClass());
        $this->assertSame('3d', ThemePreset::iconFamily()['style']);
    }

    public function test_seeded_naara_official_still_emits_empty_css(): void
    {
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();

        $this->assertSame('naara-official', ThemePreset::slug());
        // The built-in look already ships in app.css → zero injected CSS.
        $this->assertSame('', ThemePreset::styleCss());
    }

    public function test_a_custom_theme_emits_only_whitelisted_validated_vars(): void
    {
        ThemePresetModel::create([
            'slug' => 'aurora-shift',
            'name' => 'Aurora Shift',
            'tokens' => [
                'colors' => [
                    'primary' => '30 64 175',        // valid channel triple
                    'accent' => 'javascript:alert(1)', // junk → dropped
                    'action' => '999 0 0',            // out of range → dropped
                ],
                'radius' => ['card' => '2rem', 'pill' => '10px; } body{display:none'], // 2nd is junk
                'typography' => ['display' => 'Supreme Display', 'sans' => 'Comic Sans MS'], // 2nd not allow-listed
                'surface' => ['card_border_opacity' => '0.4'],
            ],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false,
            'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $css = ThemePreset::styleCss();

        // Wrapped in :root, valid tokens present…
        $this->assertStringStartsWith(':root{', $css);
        $this->assertStringContainsString('--brand-primary:30 64 175', $css);
        $this->assertStringContainsString('--radius-card:2rem', $css);
        $this->assertStringContainsString('--font-display:\'Supreme Display\'', $css);
        $this->assertStringContainsString('--card-border-opacity:0.4', $css);

        // …and every junk/out-of-range/non-allow-listed value dropped.
        $this->assertStringNotContainsString('javascript', $css);
        $this->assertStringNotContainsString('999', $css);
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringNotContainsString('Comic Sans', $css);
        $this->assertStringNotContainsString('body{', $css);
    }

    public function test_a_theme_with_its_own_dark_tokens_emits_its_own_dark_override(): void
    {
        ThemePresetModel::create([
            'slug' => 'aurora-shift',
            'name' => 'Aurora Shift',
            'tokens' => ['colors' => [
                'primary' => '30 64 175', 'primary_dark' => '20 44 120',
                'accent' => '56 189 248', 'accent_dark' => '36 123 161',
                'navy' => '10 20 35', 'action' => '230 60 45',
            ]],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false,
            'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $css = ThemePreset::styleCss();

        // 2026-09-13 reversal (owner-confirmed): dark mode uses THIS theme's
        // own primary_dark/accent_dark tokens, not a shared standard palette
        // — scoped tight enough (root.dark + this theme's own body class)
        // that it only overrides when BOTH dark mode is on AND this preset is
        // active. navy/action carry over unchanged (no separate dark variant).
        $this->assertStringContainsString(':root.dark body.theme-aurora-shift{', $css);
        $this->assertStringContainsString('--brand-primary:20 44 120', $css);
        $this->assertStringContainsString('--brand-accent:36 123 161', $css);
        $this->assertStringContainsString('--brand-navy:10 20 35', $css);
        $this->assertStringContainsString('--brand-action:230 60 45', $css);
    }

    public function test_a_theme_with_no_dark_tokens_gets_no_dark_override_block(): void
    {
        ThemePresetModel::create([
            'slug' => 'bare-custom',
            'name' => 'Bare Custom',
            'tokens' => ['colors' => ['primary' => '30 64 175']],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false,
            'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'bare-custom');
        ThemePreset::bust();

        $css = ThemePreset::styleCss();

        // A theme with no dark-specific tokens (e.g. a bare admin-created
        // custom theme) fails open: no dark-mode override at all, so dark
        // mode simply keeps the same colors light mode already set — never a
        // forced, unrelated palette.
        $this->assertStringContainsString('--brand-primary:30 64 175', $css);
        $this->assertStringNotContainsString(':root.dark', $css);
    }

    public function test_naara_official_never_gets_a_dark_mode_override(): void
    {
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();

        // The king theme keeps its own light AND dark mode exactly as shipped
        // — styleCss() returns '' entirely, so no theme override CSS (light
        // or dark) ever reaches naara-official's page.
        $this->assertSame('', ThemePreset::styleCss());
    }

    public function test_layout_variant_defaults_to_variant_a(): void
    {
        ThemePresetModel::create([
            'slug' => 'grid-nine', 'name' => 'Grid Nine',
            'tokens' => [], 'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'layout_variants' => ['dashboard_home' => 'variant-c'],
            'is_built_in' => false, 'sort_order' => 3,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'grid-nine');
        ThemePreset::bust();

        $this->assertSame('variant-c', ThemePreset::layoutVariant('dashboard_home'));
        $this->assertSame('variant-a', ThemePreset::layoutVariant('numbers'), 'Unset page → baseline.');
        $this->assertSame('variant-a', ThemePreset::layoutVariant('nonexistent_page'));
    }

    public function test_section_style_defaults_to_default(): void
    {
        ThemePresetModel::create([
            'slug' => 'grid-ten', 'name' => 'Grid Ten',
            'tokens' => [], 'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'section_styles' => ['header' => 'nonexistent-style'],
            'is_built_in' => false, 'sort_order' => 4,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'grid-ten');
        ThemePreset::bust();

        // A whitelisted section with no assignment (or an unknown value that
        // fell through the whitelist) both fall back to 'default' — never a
        // 500, never an unvalidated string reaching an @include.
        $this->assertSame('default', ThemePreset::sectionStyle('header'));
        $this->assertSame('default', ThemePreset::sectionStyle('login'));
        // An entirely unrecognised section key is safe too.
        $this->assertSame('default', ThemePreset::sectionStyle('nonexistent_section'));
    }

    public function test_section_style_picks_a_whitelisted_assignment(): void
    {
        ThemePresetModel::create([
            'slug' => 'grid-eleven', 'name' => 'Grid Eleven',
            'tokens' => [], 'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'section_styles' => ['header' => 'default'],
            'is_built_in' => false, 'sort_order' => 5,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'grid-eleven');
        ThemePreset::bust();

        $this->assertSame('default', ThemePreset::sectionStyle('header'));
    }

    public function test_seeder_creates_all_forty_presets(): void
    {
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();

        $all = ThemePreset::all();
        $this->assertCount(40, $all);
        // Exactly one built-in, and it is naara-official at sort_order 1.
        $builtIns = $all->where('is_built_in', true);
        $this->assertCount(1, $builtIns);
        $this->assertSame('naara-official', $builtIns->first()['slug']);

        // A non-official preset emits its validated palette.
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();
        $css = ThemePreset::styleCss();
        $this->assertStringContainsString('--brand-primary:59 63 140', $css);
        $this->assertStringContainsString('--radius-card:1.5rem', $css);
        $this->assertStringContainsString('--font-sans:\'Figtree\'', $css);
        $this->assertSame('sprite', ThemePreset::iconFamily()['style']);
    }

    public function test_reseeding_does_not_clobber_admin_tuning(): void
    {
        $this->seed(ThemePresetSeeder::class);
        // Admin edits a preset's tokens through the picker.
        ThemePresetModel::where('slug', 'aurora-shift')->update([
            'tokens' => ['colors' => ['primary' => '1 2 3']],
        ]);

        // Re-running the seeder (a deploy) must NOT reset that tuning.
        $this->seed(ThemePresetSeeder::class);

        $row = ThemePresetModel::where('slug', 'aurora-shift')->first();
        $this->assertSame('1 2 3', $row->tokens['colors']['primary']);
        // …but the built-in is always re-asserted authoritatively.
        $this->assertSame(40, ThemePresetModel::count());
    }

    public function test_missing_active_row_falls_back_without_blanking(): void
    {
        // Point the setting at a slug that doesn't exist → built-in, never blank.
        Setting::setValue(ThemePreset::SETTING_KEY, 'does-not-exist');
        ThemePreset::bust();

        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('', ThemePreset::styleCss());
    }

    public function test_browser_theme_color_is_null_on_the_default_theme(): void
    {
        $this->assertNull(ThemePreset::browserThemeColor());

        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
        $this->assertNull(ThemePreset::browserThemeColor());
    }

    public function test_browser_theme_color_follows_the_active_preset(): void
    {
        ThemePresetModel::create([
            'slug' => 'aurora-shift', 'name' => 'Aurora Shift',
            'tokens' => ['colors' => ['primary' => '59 63 140']],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false, 'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $this->assertSame('#3b3f8c', ThemePreset::browserThemeColor());
    }

    public function test_browser_theme_color_is_null_when_the_primary_token_is_invalid(): void
    {
        ThemePresetModel::create([
            'slug' => 'broken-preset', 'name' => 'Broken',
            'tokens' => ['colors' => ['primary' => 'not-a-colour']],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false, 'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'broken-preset');
        ThemePreset::bust();

        $this->assertNull(ThemePreset::browserThemeColor());
    }
}
