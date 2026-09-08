<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Setting;
use App\Models\User;
use App\Support\LandingHeroLibrary;
use App\Support\ThemePageLibrary;
use App\Support\ThemePreset;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Theme visual rebuild, Batch 3 of 8 (owner request, 2026-09-07: "10 to
 * make it 15"). Five brand-new personas, built from scratch: aurora-shift,
 * sunset-transit, fintra-clean, capable-mono, waitlisty-soft — each gets
 * the full suite in one go (unlike batch 1, which shipped header/login/
 * bottom_nav first and completed the rest in batch 2). Mirrors
 * ThemeBatch1VisualRebuildTest's coverage shape for the chrome sections,
 * plus the full page-suite coverage established by
 * ThemeFullPageSuiteTest/ThemeLandingPageTest for the earlier full-suite
 * themes.
 */
class ThemeBatch3VisualRebuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    public static function batch3Themes(): array
    {
        return [
            'aurora-shift' => ['aurora-shift'],
            'sunset-transit' => ['sunset-transit'],
            'fintra-clean' => ['fintra-clean'],
            'capable-mono' => ['capable-mono'],
            'waitlisty-soft' => ['waitlisty-soft'],
        ];
    }

    public static function batch3ThemesWithLoginBg(): array
    {
        return [
            'aurora-shift' => ['aurora-shift', 'aurora'],
            'sunset-transit' => ['sunset-transit', 'mesh-grain'],
            'fintra-clean' => ['fintra-clean', 'none'],
            'capable-mono' => ['capable-mono', 'dot-grid'],
            'waitlisty-soft' => ['waitlisty-soft', 'aurora'],
        ];
    }

    #[DataProvider('batch3Themes')]
    public function test_theme_resolves_its_own_chrome_and_landing_style(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->assertSame($slug, ThemePreset::sectionStyle('header'));
        $this->assertSame($slug, ThemePreset::sectionStyle('login'));
        $this->assertSame($slug, ThemePreset::sectionStyle('bottom_nav'));
        $this->assertSame($slug, ThemePreset::sectionStyle('landing_hero'));
        $this->assertSame($slug, ThemePreset::sectionStyle('about_page'));
        $this->assertSame($slug, ThemePreset::sectionStyle('how_it_works_page'));
        $this->assertSame($slug, ThemePreset::sectionStyle('contact_page'));
        $this->assertSame($slug, ThemePreset::sectionStyle('footer'));
    }

    #[DataProvider('batch3ThemesWithLoginBg')]
    public function test_theme_resolves_its_assigned_login_background_effect(string $slug, string $loginBg): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->assertSame($loginBg, ThemePreset::sectionStyle('login_bg'));
    }

    #[DataProvider('batch3Themes')]
    public function test_login_page_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/login')->assertOk()->assertSee('data-footer-style="'.$slug.'"', false);
    }

    #[DataProvider('batch3Themes')]
    public function test_authenticated_header_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Connectivity');
    }

    #[DataProvider('batch3Themes')]
    public function test_home_page_renders_its_own_landing_page_and_footer(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/')
            ->assertOk()
            ->assertSee(LandingHeroLibrary::defaultsFor($slug)['headline'])
            ->assertSee('data-footer-style="'.$slug.'"', false);
    }

    #[DataProvider('batch3Themes')]
    public function test_about_page_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/about')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('about_page', $slug)['headline']);
    }

    #[DataProvider('batch3Themes')]
    public function test_how_it_works_page_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/how-it-works')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('how_it_works_page', $slug)['headline']);
    }

    #[DataProvider('batch3Themes')]
    public function test_contact_page_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/contact')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('contact_page', $slug)['headline']);
    }

    public function test_naara_official_is_unaffected_by_batch_3(): void
    {
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('default', ThemePreset::sectionStyle('header'));
        $this->assertSame('default', ThemePreset::sectionStyle('login'));
        $this->assertSame('default', ThemePreset::sectionStyle('bottom_nav'));
        $this->assertSame('default', ThemePreset::sectionStyle('landing_hero'));
        $this->assertSame('default', ThemePreset::sectionStyle('footer'));
        $this->assertSame('none', ThemePreset::sectionStyle('login_bg'));
    }
}
