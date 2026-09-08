<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Setting;
use App\Models\User;
use App\Support\ThemePreset;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Theme visual rebuild, Batch 1 of 8 (owner request, 2026-09-07). Five
 * presets get a real, structurally unique header + login screen instead of
 * the shared "default" chrome: aries-contrast, midnight-signal, neon-vertex,
 * paperwhite, origin-bold. Proves the resolver picks the right style for
 * each and that both swappable surfaces (the mobile header via an
 * authenticated page, and the login screen) render without error under
 * every one of the five.
 */
class ThemeBatch1VisualRebuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    public static function batch1Themes(): array
    {
        return [
            'aries-contrast' => ['aries-contrast'],
            'midnight-signal' => ['midnight-signal'],
            'neon-vertex' => ['neon-vertex'],
            'paperwhite' => ['paperwhite'],
            'origin-bold' => ['origin-bold'],
        ];
    }

    public static function batch1ThemesWithLoginBg(): array
    {
        return [
            'aries-contrast' => ['aries-contrast', 'dot-grid'],
            'midnight-signal' => ['midnight-signal', 'mesh-grain'],
            'neon-vertex' => ['neon-vertex', 'aurora'],
            'paperwhite' => ['paperwhite', 'none'],
            'origin-bold' => ['origin-bold', 'dot-grid'],
        ];
    }

    #[DataProvider('batch1Themes')]
    public function test_theme_resolves_its_own_header_and_login_style(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->assertSame($slug, ThemePreset::sectionStyle('header'));
        $this->assertSame($slug, ThemePreset::sectionStyle('login'));
        $this->assertSame($slug, ThemePreset::sectionStyle('bottom_nav'));
        // landing_hero now exists for all 5 batch-1 themes (batch 2
        // completed aries-contrast/paperwhite/origin-bold's full suite on
        // top of neon-vertex/midnight-signal's own from the prior batch) —
        // every batch-1 theme resolves its own hand-built landing page.
        $this->assertSame($slug, ThemePreset::sectionStyle('landing_hero'));
    }

    #[DataProvider('batch1ThemesWithLoginBg')]
    public function test_theme_resolves_its_assigned_login_background_effect(string $slug, string $loginBg): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->assertSame($loginBg, ThemePreset::sectionStyle('login_bg'));
    }

    #[DataProvider('batch1Themes')]
    public function test_login_page_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    #[DataProvider('batch1Themes')]
    public function test_authenticated_header_renders_under_the_theme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Connectivity');
    }

    public function test_naara_official_is_unaffected_by_batch_1(): void
    {
        // The default theme must never pick up a batch-1 style family.
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('default', ThemePreset::sectionStyle('header'));
        $this->assertSame('default', ThemePreset::sectionStyle('login'));
        $this->assertSame('default', ThemePreset::sectionStyle('bottom_nav'));
        $this->assertSame('none', ThemePreset::sectionStyle('login_bg'));
    }
}
