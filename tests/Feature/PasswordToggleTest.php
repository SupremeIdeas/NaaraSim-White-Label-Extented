<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Task #17 — show/hide password toggle (<x-ui.password>). Progressive
 * enhancement: masked by default, Alpine flips it while the eye is toggled.
 */
class PasswordToggleTest extends TestCase
{
    public function test_component_is_masked_by_default_and_toggles_via_alpine(): void
    {
        $html = Blade::render('<x-ui.password name="password" required class="my-input" />');

        // No-JS safe: the base type stays password…
        $this->assertStringContainsString('type="password"', $html);
        // …and Alpine only flips it while toggled.
        $this->assertStringContainsString("x-bind:type=\"show ? 'text' : 'password'\"", $html);
        // Caller attributes pass through; we add right padding for the icon.
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('my-input', $html);
        $this->assertStringContainsString('pr-10', $html);
        // SVG eye icons (no emoji), accessible toggle button.
        $this->assertStringContainsString('#i-eye', $html);
        $this->assertStringContainsString('#i-eye-off', $html);
        $this->assertStringContainsString('aria-pressed', $html);
    }

    public function test_login_page_uses_the_toggle(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('x-bind:type', false)
            ->assertSee('#i-eye', false);
    }
}
