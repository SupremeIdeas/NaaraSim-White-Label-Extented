<?php

namespace Tests\Feature;

use App\Support\PlatformTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard background / Platform Theme. The default mode emits no override CSS
 * (the built-in treatment renders it); other modes emit only clamped --dbg-*
 * variables or a sanitised wallpaper URL — never author-supplied CSS.
 */
class PlatformThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformTheme::flush();
    }

    public function test_default_mode_emits_no_override_and_no_motion(): void
    {
        $this->assertSame('default', PlatformTheme::mode());
        $this->assertSame('', PlatformTheme::styleCss());
        $this->assertSame('dashboard-bg', PlatformTheme::bodyClass());
    }

    public function test_animated_mode_adds_the_motion_class(): void
    {
        PlatformTheme::save(['mode' => 'animated_gradient']);

        $this->assertStringContainsString('dashboard-bg--animated', PlatformTheme::bodyClass());
    }

    public function test_tuning_is_clamped_server_side(): void
    {
        PlatformTheme::save([
            'mode' => 'static_gradient',
            'light' => ['up_intensity' => 9.0, 'lo_feather' => 5, 'up_size' => 999],
        ]);

        $light = PlatformTheme::current()['light'];
        $this->assertSame(1.5, $light['up_intensity']);   // clamped to max
        $this->assertSame(70, $light['lo_feather']);       // clamped to min
        $this->assertSame(90, $light['up_size']);          // clamped to max

        // Emitted CSS only ever contains --dbg-* declarations, never raw values.
        $css = PlatformTheme::styleCss();
        $this->assertStringContainsString('--dbg-up-intensity:1.5', $css); // clamped, not the 9.0 input
        $this->assertStringNotContainsString('9.0', $css);                  // the blown-out value never reaches CSS
    }

    public function test_image_mode_sanitises_the_wallpaper_url(): void
    {
        PlatformTheme::save([
            'mode' => 'image',
            'image_light' => "/storage/theme/light.webp') ; background: url('evil",
            'image_dark' => 'https://cdn.example.com/dark.webp',
        ]);

        $css = PlatformTheme::styleCss();
        // The breakout sequences (close-quote, close-paren, semicolon, space) are
        // stripped, so the URL can't terminate url() early and inject a second
        // declaration. The malicious "') ; background: url('" collapses to inert
        // path characters inside the still-quoted url().
        $this->assertStringNotContainsString('background: ', $css); // injected spaced decl gone (our template has no space)
        $this->assertStringNotContainsString("') ;", $css);         // the exact breakout is gone
        $this->assertStringContainsString("url('/storage/theme/light.webp", $css);
        $this->assertStringContainsString('https://cdn.example.com/dark.webp', $css);
    }

    public function test_derive_dark_drops_coral_from_the_extra_stop(): void
    {
        $dark = PlatformTheme::deriveDark([
            'extra_color' => 'action',
            'extra_alpha' => 0.3,
            'lo_intensity' => 1.0,
        ]);

        // Coral reads as "off" on navy — the derived dark starting point zeroes it.
        $this->assertSame(0.0, $dark['extra_alpha']);
    }
}
