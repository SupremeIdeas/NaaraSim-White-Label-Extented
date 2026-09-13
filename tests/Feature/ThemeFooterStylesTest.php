<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SiteChrome;
use App\Support\ThemePreset;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Swappable FOOTER section (owner request, 2026-09-07: "please all themes
 * too should have unique footer too... not always we get a straight line
 * footer, then footer swappable too"). neon-vertex and midnight-signal were
 * the first two themes with a hand-built footer; batch 2 (2026-09-07) added
 * 5 more (aries-contrast, paperwhite, origin-bold, solar-flare, noir-reserve);
 * batch 3 (2026-09-07) adds 5 brand-new personas (aurora-shift,
 * sunset-transit, fintra-clean, capable-mono, waitlisty-soft) — all
 * mirroring the header/bottom_nav pattern exactly: chrome-only, no
 * editable content fields, same functional links/columns as the shared
 * default footer. Every other theme, including naara-official, keeps using
 * today's exact shared footer.
 */
class ThemeFooterStylesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    public static function footerThemes(): array
    {
        return [
            'neon-vertex' => ['neon-vertex'],
            'midnight-signal' => ['midnight-signal'],
            'aries-contrast' => ['aries-contrast'],
            'paperwhite' => ['paperwhite'],
            'origin-bold' => ['origin-bold'],
            'solar-flare' => ['solar-flare'],
            'noir-reserve' => ['noir-reserve'],
            'aurora-shift' => ['aurora-shift'],
            'sunset-transit' => ['sunset-transit'],
            'fintra-clean' => ['fintra-clean'],
            'capable-mono' => ['capable-mono'],
            'waitlisty-soft' => ['waitlisty-soft'],
        ];
    }

    #[DataProvider('footerThemes')]
    public function test_theme_resolves_its_own_footer_style(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->assertSame($slug, ThemePreset::sectionStyle('footer'));
        // Sibling sections stay resolved the same way (batch-1 assignment).
        $this->assertSame($slug, ThemePreset::sectionStyle('header'));
    }

    public function test_naara_official_footer_is_unaffected(): void
    {
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('default', ThemePreset::sectionStyle('footer'));

        $this->get('/')->assertOk()
            ->assertDontSee('data-footer-style="neon-vertex"', false)
            ->assertDontSee('data-footer-style="midnight-signal"', false);
    }

    public function test_every_other_seeded_theme_stays_on_the_default_footer(): void
    {
        foreach (['verdant-pulse', 'cobalt-frost', 'mango-burst'] as $slug) {
            Setting::setValue(ThemePreset::SETTING_KEY, $slug);
            ThemePreset::bust();

            $this->assertSame('default', ThemePreset::sectionStyle('footer'), "theme {$slug} should stay on the default footer");
        }
    }

    #[DataProvider('footerThemes')]
    public function test_home_page_renders_the_theme_own_footer_markup(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/')->assertOk()->assertSee('data-footer-style="'.$slug.'"', false);
    }

    #[DataProvider('footerThemes')]
    public function test_login_page_slim_footer_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        // Login uses variant="slim" — proves the swap forwards $variant
        // correctly and the slim partial still renders without error.
        $this->get('/login')->assertOk()->assertSee('data-footer-style="'.$slug.'"', false);
    }

    #[DataProvider('footerThemes')]
    public function test_themed_footer_preserves_the_real_legal_links(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $legal = collect(SiteChrome::footerLegal())->pluck('label')->all();

        $response = $this->get('/')->assertOk();
        foreach ($legal as $label) {
            $response->assertSee($label);
        }
    }

    /**
     * Theme-integrity blueprint §1.4/§2 — every themed footer variant now
     * renders through the SAME shared `<x-footer-credit>` component, so the
     * agency link exists everywhere, not just on the default footer.
     */
    #[DataProvider('footerThemes')]
    public function test_themed_footer_links_the_agency_name(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/')
            ->assertOk()
            ->assertSee('href="https://supremeideas.agency"', false)
            ->assertSee('Supreme Ideas Agency');
    }
}
