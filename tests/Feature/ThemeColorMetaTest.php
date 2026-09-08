<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Support\ThemePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser-chrome <meta name="theme-color"> (owner request): it must
 * follow the active Theme Preset, not stay pinned to the static App Export
 * PWA colour, once a non-default theme is selected.
 */
class ThemeColorMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ThemePreset::bust();
    }

    public function test_default_theme_uses_the_app_export_colour(): void
    {
        $this->get('/')->assertSee('name="theme-color" content="#0A6E6E"', false);
    }

    public function test_a_non_default_theme_repaints_the_browser_chrome(): void
    {
        ThemePresetModel::create([
            'slug' => 'aurora-shift', 'name' => 'Aurora Shift',
            'tokens' => ['colors' => ['primary' => '59 63 140']],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false, 'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $this->get('/')->assertSee('name="theme-color" content="#3b3f8c"', false);
    }
}
