<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * BLUEPRINT-batch1-sections §1/§2 — the glass header + sun/moon theme switch.
 */
class SectionsHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_toggle_is_the_scoped_sun_moon_switch(): void
    {
        $html = Blade::render('<x-theme-toggle />');

        // The new switch markup… (Theme Toggle Studio: renamed off the shared
        // .nx-switch class into its own .nx-theme-toggle namespace so the
        // theme-toggle's sizing no longer collides with the generic admin
        // switch component that also used to use .nx-switch).
        $this->assertStringContainsString('nx-theme-toggle', $html);
        $this->assertStringContainsString('sun-moon', $html);
        // …but NOT the blueprint's global IDs (they would be invalid rendered twice).
        $this->assertStringNotContainsString('id="input"', $html);
        $this->assertStringNotContainsString('id="moon-dot', $html);

        // Behavioural contract is preserved: label, persistence, dispatch.
        $this->assertStringContainsString('Toggle dark mode', $html);
        $this->assertStringContainsString("localStorage.setItem('theme'", $html);
        $this->assertStringContainsString("theme-changed", $html);
    }

    public function test_toggle_can_be_rendered_twice_without_duplicate_ids(): void
    {
        // Two instances on one page (mobile header + desktop rail) must be valid.
        $html = Blade::render('<div>{{ $a }}{{ $b }}</div>', [
            'a' => Blade::render('<x-theme-toggle />'),
            'b' => Blade::render('<x-theme-toggle />'),
        ]);

        // No id attribute is emitted at all, so no collision is possible.
        $this->assertStringNotContainsString('id="', $html);
    }

    public function test_customer_header_uses_the_glass_fade_and_orders_bell_toggle_hamburger(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole('user');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // Glass-fade header, no legacy hard-border surface class.
        $this->assertStringContainsString('nx-header-fade', $html);

        // Order within the mobile header slot: bell (nc-mobile) → toggle → menu.
        $this->assertTrue(
            strpos($html, 'nc-mobile') < strpos($html, 'nx-theme-toggle'),
            'Notification bell should come before the theme toggle.',
        );
    }
}
