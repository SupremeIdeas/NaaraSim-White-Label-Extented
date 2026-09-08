<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * BLUEPRINT-ambient-gradient — the two verified bugs (hover-gated card border,
 * flat light-mode hero) and the reusable <x-ambient-glow> extraction.
 */
class AmbientGradientTest extends TestCase
{
    public function test_card_gradient_border_is_ambient_by_default_not_hover_only(): void
    {
        $css = file_get_contents(resource_path('css/ui-elements.css'));

        // §2: the .nx-card::before floor is no longer 0 (invisible on touch)…
        $this->assertMatchesRegularExpression('/\.nx-card::before\s*\{[^}]*opacity:\s*0\.35;/s', $css);
        // …and full strength on real interaction (hover OR keyboard focus).
        $this->assertStringContainsString('.nx-card:focus-within::before', $css);
    }

    public function test_light_mode_hero_has_a_real_aurora_not_a_flat_colour(): void
    {
        $css = file_get_contents(resource_path('css/sections.css'));

        // §1: .nx-sechero.is-light now layers brand radial gradients over the base.
        $this->assertMatchesRegularExpression(
            '/\.nx-sechero\.is-light\s*\{[^}]*radial-gradient\([^)]*var\(--brand-primary\)/s',
            $css,
        );
        // The reusable ambient utility exists with a light + dark tuning.
        $this->assertStringContainsString('.nx-ambient::before', $css);
        $this->assertStringContainsString('.dark .nx-ambient::before', $css);
    }

    public function test_ambient_glow_component_wraps_content(): void
    {
        $html = Blade::render('<x-ambient-glow class="px-4"><p>hi</p></x-ambient-glow>');
        $this->assertStringContainsString('nx-ambient', $html);
        $this->assertStringContainsString('px-4', $html);
        $this->assertStringContainsString('<p>hi</p>', $html);
    }

    public function test_homepage_products_section_opts_into_the_ambient_glow(): void
    {
        $this->get('/')->assertOk()->assertSee('nx-ambient', false);
    }
}
