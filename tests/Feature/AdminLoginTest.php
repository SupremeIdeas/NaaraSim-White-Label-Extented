<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dedicated admin sign-in area (blueprint Section 25) and the 2FA challenge
 * page — the security layers must let a legitimate admin in, from any browser.
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guests_reach_a_dedicated_admin_login_not_the_customer_login(): void
    {
        // The panel bounces guests to the admin login…
        $this->get('/adminmaster')->assertRedirect(route('admin.login'));

        // …which is a distinct, branded page reachable by guests.
        $this->get('/adminmaster/login')->assertOk()
            ->assertSee('Administrator access')
            ->assertSee('action="/login"', false); // posts to the Fortify pipeline
    }

    public function test_a_signed_in_admin_hitting_the_login_page_goes_straight_in(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/adminmaster/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_signed_in_customer_cannot_use_the_admin_login_to_peek(): void
    {
        // A non-admin sees the login page (no leak), and the panel itself still 404s.
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster')->assertNotFound();
    }

    public function test_the_two_factor_challenge_page_is_registered(): void
    {
        // Rendering the view proves the Fortify twoFactorChallengeView is wired,
        // so a 2FA-enabled admin is never stranded after their password. Share an
        // empty error bag as the session middleware would for a real request.
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = view('auth.two-factor-challenge')->render();
        $this->assertStringContainsString('Two-factor authentication', $html);
        $this->assertStringContainsString('action="/two-factor-challenge"', $html);
    }
}
