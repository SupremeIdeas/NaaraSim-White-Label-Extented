<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Readiness-audit fix (2026-09-07): Fortify ships POST /register and
 * POST /forgot-password with no rate limit at all in this Fortify version.
 * ThrottleUnprotectedAuthRoutes closes that gap without touching Fortify's
 * own route registration.
 */
class ThrottleUnprotectedAuthRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_throttled_after_five_attempts_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'Test User',
                'email' => "user{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'user-overflow@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'user-overflow@example.com']);
    }

    public function test_forgot_password_is_throttled_after_five_attempts_per_email_and_ip(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'target@example.com']);
        }

        $response = $this->post('/forgot-password', ['email' => 'target@example.com']);

        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_throttle_is_scoped_per_email_not_global(): void
    {
        User::factory()->create(['email' => 'first@example.com']);
        User::factory()->create(['email' => 'second@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'first@example.com']);
        }

        // A different email from the same IP is a separate bucket.
        $response = $this->post('/forgot-password', ['email' => 'second@example.com']);

        $response->assertSessionDoesntHaveErrors('email');
    }

    public function test_get_requests_are_never_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/register')->assertOk();
            $this->get('/forgot-password')->assertOk();
        }
    }
}
