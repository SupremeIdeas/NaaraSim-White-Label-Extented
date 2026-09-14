<?php

namespace Tests\Feature;

use App\Models\KnownDevice;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sept-14 owner request (2FA/device-login alerts): a user is told when their
 * account is signed into from a device (IP+user-agent) never seen before.
 * Unlike the `sessions` table (live sessions only, pruned over time),
 * `known_devices` is a durable record App\Listeners\RecordLoginDevice checks
 * on every login.
 */
class NewDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function login(User $user, string $ip, string $userAgent): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => $userAgent])
            ->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_users_very_first_login_records_the_device_but_never_alerts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->login($user, '10.0.0.1', 'Mozilla/5.0 (Test Browser 1)');

        $this->assertDatabaseHas('known_devices', ['user_id' => $user->id, 'ip_address' => '10.0.0.1']);
        Notification::assertNothingSentTo($user);
    }

    public function test_a_repeat_login_from_the_same_device_never_alerts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->login($user, '10.0.0.1', 'Mozilla/5.0 (Test Browser 1)');
        $this->post('/logout');
        $this->login($user, '10.0.0.1', 'Mozilla/5.0 (Test Browser 1)');

        $this->assertDatabaseCount('known_devices', 1);
        Notification::assertNothingSentTo($user);
    }

    public function test_a_login_from_a_genuinely_new_device_alerts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->login($user, '10.0.0.1', 'Mozilla/5.0 (Test Browser 1)');
        $this->post('/logout');
        $this->login($user, '203.0.113.9', 'Mozilla/5.0 (Test Browser 2)');

        $this->assertDatabaseCount('known_devices', 2);
        $this->assertDatabaseHas('known_devices', ['user_id' => $user->id, 'ip_address' => '203.0.113.9']);
        Notification::assertSentTo(
            $user,
            NewDeviceLoginNotification::class,
            fn ($n) => $n->ipAddress === '203.0.113.9' && $n->userAgent === 'Mozilla/5.0 (Test Browser 2)'
        );
    }

    public function test_the_fingerprint_helper_is_a_stable_sha256_of_ip_and_user_agent(): void
    {
        $this->assertSame(
            hash('sha256', '1.2.3.4|Agent-X'),
            KnownDevice::fingerprint('1.2.3.4', 'Agent-X')
        );
    }
}
