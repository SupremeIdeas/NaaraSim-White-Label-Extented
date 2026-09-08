<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The premium WebGL login panel (brand "connected planet"). The canvas renders
 * by default with the glow-orb fallback beneath it, so reduced-motion / no-WebGL
 * users still get a branded panel.
 */
class LoginWebglTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_renders_the_webgl_canvas_with_a_fallback(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-webgl="login"', false) // the scene canvas
            ->assertSee('blur-3xl', false);          // the glow-orb fallback beneath it
    }
}
