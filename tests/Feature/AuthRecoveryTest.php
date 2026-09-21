<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Two operator-reported production fixes:
 *  1. Login returns the user to where they were HEADING, so the admin path is
 *     reachable from any device (no more "always bounced to the user login").
 *  2. A stale CSRF token auto-recovers to the form instead of the raw 419 page.
 */
class AuthRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_path_sends_a_guest_to_login_then_back_to_admin(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('admin');

        // A guest hitting the secret admin path is sent to the dedicated admin
        // login, with the admin path remembered.
        $this->get('/adminmaster')->assertRedirect(route('admin.login'));

        // After logging in, they land BACK on the admin path (not /dashboard).
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/adminmaster');
    }

    public function test_a_normal_login_still_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
    }

    public function test_an_expired_csrf_token_recovers_to_the_form_not_a_419(): void
    {
        // A route that simulates the CSRF middleware rejecting a stale token.
        Route::middleware('web')->post('/__csrf_probe', function () {
            throw new TokenMismatchException;
        });

        $this->from('/register')
            ->post('/__csrf_probe')
            ->assertRedirect('/register')                 // back to the form, not a 419
            ->assertSessionHas('status');                 // with a gentle retry message
    }

    /**
     * Tier 4 #10 Phase A2 — a session payload encrypted under a stale/rotated
     * APP_KEY (or a genuinely corrupted row) throws DecryptException from
     * deep inside the encrypted session store's read. It must recover the
     * same way as a stale CSRF token above: drop the poisoned session cookie
     * and send the visitor to a fresh one, never a hard "MAC is invalid" page.
     */
    public function test_a_decrypt_exception_resets_the_session_instead_of_a_hard_error(): void
    {
        // A two-segment path — a single segment would collide with the
        // catch-all {adminGateway} route registered at boot.
        Route::middleware('web')->get('/__test/decrypt_probe', function () {
            throw new \Illuminate\Contracts\Encryption\DecryptException('MAC is invalid.');
        });

        $response = $this->get('/__test/decrypt_probe');

        // The request completes as a normal redirect (not a 500), which
        // means Laravel's own StartSession middleware ran its course and
        // queued a genuinely fresh session cookie for the visitor — the
        // whole point, since the old one was the poisoned/undecryptable one.
        $response->assertRedirect('/__test/decrypt_probe'); // GET retries the same URL fresh
        $response->assertSessionHas('status');
        $this->assertNotNull(
            collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie')),
            'a fresh session cookie must still be issued',
        );
    }

    public function test_a_decrypt_exception_on_a_json_request_returns_a_clean_419(): void
    {
        Route::middleware('web')->post('/__test/decrypt_probe_json', function () {
            throw new \Illuminate\Contracts\Encryption\DecryptException('MAC is invalid.');
        });

        $this->postJson('/__test/decrypt_probe_json')
            ->assertStatus(419)
            ->assertJson(['message' => 'Your session could not be verified. Please refresh and try again.']);
    }
}
