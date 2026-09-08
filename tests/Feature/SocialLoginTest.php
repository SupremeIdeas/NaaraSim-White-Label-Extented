<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Module 23 — Google sign-in.
 */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function configureGoogle(): void
    {
        config(['services.google.client_id' => 'gid', 'services.google.client_secret' => 'gsecret']);
    }

    private function fakeGoogleUser(string $id, string $email, string $name = 'Ada'): void
    {
        $socialUser = (new SocialiteUser)->map([
            'id' => $id, 'name' => $name, 'email' => $email, 'avatar' => 'https://img/a.png',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_routes_404_until_configured(): void
    {
        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_login_page_shows_google_only_when_configured(): void
    {
        $this->get('/login')->assertDontSee('Continue with Google');

        $this->configureGoogle();
        $this->get('/login')->assertSee('Continue with Google');
    }

    public function test_callback_creates_a_new_verified_user_and_logs_in(): void
    {
        Notification::fake();
        $this->configureGoogle();
        $this->fakeGoogleUser('G-1', 'newbie@gmail.com', 'New Bie');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $user = User::where('email', 'newbie@gmail.com')->firstOrFail();
        $this->assertSame('G-1', $user->google_id);
        $this->assertNotNull($user->email_verified_at); // Google-verified
        $this->assertTrue($user->hasRole('user'));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_callback_links_google_to_an_existing_account_by_email(): void
    {
        $this->configureGoogle();
        $existing = User::factory()->create(['email' => 'me@gmail.com', 'google_id' => null]);
        $this->fakeGoogleUser('G-2', 'me@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame('G-2', $existing->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'me@gmail.com')->count()); // no duplicate
        $this->assertAuthenticatedAs($existing->fresh());
    }

    public function test_callback_logs_in_a_known_google_user(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['google_id' => 'G-3']);
        $this->fakeGoogleUser('G-3', $user->email);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user->fresh());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
