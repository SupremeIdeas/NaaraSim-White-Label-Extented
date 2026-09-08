<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security as AdminSecurity;
use App\Models\Setting;
use App\Models\User;
use App\Support\SecuritySettings;
use App\Support\Turnstile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Module 33 — Cloudflare Turnstile bot protection. */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SecuritySettings::flush();
    }

    private function activate(): void
    {
        config([
            'services.turnstile.site_key' => 'site-abc',
            'services.turnstile.secret_key' => 'secret-xyz',
        ]);
        Setting::setValue('security.turnstile_enabled', true, 'security');
        SecuritySettings::flush();
    }

    public function test_it_is_inactive_until_enabled_and_configured(): void
    {
        $this->assertFalse(Turnstile::active());

        // Enabled but no keys → still inactive (fail-open).
        Setting::setValue('security.turnstile_enabled', true, 'security');
        SecuritySettings::flush();
        $this->assertTrue(Turnstile::enabled());
        $this->assertFalse(Turnstile::active());

        // Keys but not enabled → inactive.
        Setting::setValue('security.turnstile_enabled', false, 'security');
        SecuritySettings::flush();
        config(['services.turnstile.site_key' => 'k', 'services.turnstile.secret_key' => 's']);
        $this->assertTrue(Turnstile::configured());
        $this->assertFalse(Turnstile::active());
    }

    public function test_widget_and_csp_only_appear_when_active(): void
    {
        // Inactive: no widget, CSP has no cloudflare origin.
        $res = $this->get('/login')->assertOk();
        $res->assertDontSee('cf-turnstile', false);
        $this->assertStringNotContainsString(Turnstile::ORIGIN, (string) $res->headers->get('Content-Security-Policy'));

        // Active: widget rendered, CSP whitelists the cloudflare origin.
        $this->activate();
        $res = $this->get('/login')->assertOk()->assertSee('cf-turnstile', false)->assertSee('site-abc', false);
        $csp = (string) $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('script-src', $csp);
        $this->assertStringContainsString(Turnstile::ORIGIN, $csp);
    }

    public function test_login_is_rejected_without_a_token_when_active(): void
    {
        User::factory()->create(['email' => 'a@b.com', 'password' => bcrypt('password12')]);
        $this->activate();

        // No token → blocked before Fortify, with the human-check error.
        $this->from('/login')->post('/login', ['email' => 'a@b.com', 'password' => 'password12'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_passes_when_the_token_verifies(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);
        $user = User::factory()->create(['email' => 'c@d.com', 'password' => bcrypt('password12')])->fresh();
        $this->activate();

        $this->post('/login', [
            'email' => 'c@d.com', 'password' => 'password12', 'cf-turnstile-response' => 'tok',
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_failed_verification_blocks_login(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);
        User::factory()->create(['email' => 'e@f.com', 'password' => bcrypt('password12')]);
        $this->activate();

        $this->from('/login')->post('/login', [
            'email' => 'e@f.com', 'password' => 'password12', 'cf-turnstile-response' => 'bad',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_turnstile_does_not_touch_login(): void
    {
        // Turnstile off entirely — login works with no token, unchanged.
        $user = User::factory()->create(['email' => 'g@h.com', 'password' => bcrypt('password12')])->fresh();

        $this->post('/login', ['email' => 'g@h.com', 'password' => 'password12']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_toggles_turnstile_from_the_security_page(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(AdminSecurity::class)
            ->set('turnstile_enabled', true)
            ->call('saveSiteProtection')
            ->assertHasNoErrors();

        SecuritySettings::flush();
        $this->assertTrue(SecuritySettings::turnstileEnabled());
    }
}
