<?php

namespace Tests\Feature\Appearance;

use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Support\Appearance\UpdateUserAppearance;
use App\Support\ThemePreset;

/** Prompt 20 §13: on a non-default platform theme the default accent inherits the theme's brand colour; an explicit accent wins. */
class AppearanceThemeBridgeTest extends AppearanceTestCase
{
    private function purpleTheme(): void
    {
        ThemePresetModel::create([
            'slug' => 'test-purple', 'name' => 'Test Purple',
            'tokens' => ['colors' => ['primary' => '109 63 160', 'primary_dark' => '79 45 120', 'accent' => '232 121 249', 'accent_dark' => '162 85 174', 'navy' => '30 20 45', 'action' => '236 90 70']],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false, 'sort_order' => 99,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'test-purple');
        ThemePreset::bust();
    }

    private function accentStyle(string $html): string
    {
        return preg_match('/<style id="nx-accent-vars">(.*?)<\/style>/s', $html, $m) ? $m[1] : '';
    }

    public function test_the_default_theme_keeps_the_shipped_teal(): void
    {
        $html = $this->actingAs($this->member())->get(route('account.appearance'))->getContent();

        $this->assertSame('', $this->accentStyle($html));
    }

    public function test_a_themed_install_recolours_the_default_accent_in_both_modes_and_never_touches_gold(): void
    {
        $this->purpleTheme();
        $css = $this->accentStyle($this->actingAs($this->member())->get(route('account.appearance'))->getContent());

        $this->assertStringContainsString('html:not(.dark)[data-nx-accent=teal]{', $css);
        $this->assertStringContainsString('html.dark[data-nx-accent=teal]{', $css);
        $this->assertStringContainsString('--nx-cta-b:', $css);
        $this->assertStringNotContainsString('--nx-gold', $css, 'gold means money and is never recoloured');
        $this->assertStringNotContainsString('--brand-primary', $css, 'the theme already owns the brand variables');
    }

    public function test_a_member_who_picks_an_accent_of_their_own_is_not_overridden_by_the_theme(): void
    {
        $this->purpleTheme();
        $u = $this->member();
        (new UpdateUserAppearance)($u, ['accent' => 'ocean']);

        $this->assertSame('', $this->accentStyle($this->actingAs($u)->get(route('account.appearance'))->getContent()));
    }
}
