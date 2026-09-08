<?php

namespace Tests\Feature;

use App\Support\GlassmorphismSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-tunable card glassmorphism dial. Default install emits no override CSS
 * (the shipped .nx-glass-tile var() fallbacks already match); any change emits
 * clamped --nx-glass-* variables only.
 */
class GlassmorphismSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GlassmorphismSettings::flush();
    }

    public function test_default_emits_no_override_css(): void
    {
        $this->assertSame(GlassmorphismSettings::DEFAULT_OPACITY, GlassmorphismSettings::opacity());
        $this->assertSame(GlassmorphismSettings::DEFAULT_BLUR, GlassmorphismSettings::blur());
        $this->assertSame('', GlassmorphismSettings::styleCss());
    }

    public function test_save_clamps_out_of_range_values(): void
    {
        GlassmorphismSettings::save(500, -10);

        $this->assertSame(GlassmorphismSettings::MAX_OPACITY, GlassmorphismSettings::opacity());
        $this->assertSame(GlassmorphismSettings::MIN_BLUR, GlassmorphismSettings::blur());
    }

    public function test_style_css_emits_light_and_dark_alpha_plus_blur(): void
    {
        GlassmorphismSettings::save(80, 6);

        $css = GlassmorphismSettings::styleCss();
        $this->assertStringContainsString('--nx-glass-opacity-light:0.8', $css);
        $this->assertStringContainsString('--nx-glass-opacity-dark:0.73', $css);
        $this->assertStringContainsString('--nx-glass-blur:6px', $css);
    }

    public function test_dark_offset_never_drops_below_the_minimum_opacity(): void
    {
        GlassmorphismSettings::save(GlassmorphismSettings::MIN_OPACITY, 12);

        $css = GlassmorphismSettings::styleCss();
        $this->assertStringContainsString('--nx-glass-opacity-dark:0.2', $css);
    }

    public function test_flush_forces_a_fresh_read(): void
    {
        GlassmorphismSettings::save(90, 20);
        GlassmorphismSettings::flush();

        $this->assertSame(90, GlassmorphismSettings::opacity());
        $this->assertSame(20, GlassmorphismSettings::blur());
    }
}
