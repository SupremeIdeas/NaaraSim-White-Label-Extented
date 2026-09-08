<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * BUILD-1 §3.1/§3.3 — an admin must never be locked out of login (200/min),
 * while everyone else keeps the tight 5/min brute-force guard. Same
 * super_admin/admin role as the single source of truth (no is_admin flag).
 */
class AdminLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function loginLimitFor(string $email): int
    {
        $limiter = RateLimiter::limiter('login');
        $limit = $limiter(Request::create('/login', 'POST', ['email' => $email]));

        return $limit->maxAttempts;
    }

    public function test_admin_gets_a_high_login_cap(): void
    {
        $admin = User::factory()->create(['email' => 'boss@naara.test']);
        $admin->assignRole('admin');

        $this->assertSame(200, $this->loginLimitFor('boss@naara.test'));
    }

    public function test_super_admin_gets_a_high_login_cap(): void
    {
        $admin = User::factory()->create(['email' => 'root@naara.test']);
        $admin->assignRole('super_admin');

        $this->assertSame(200, $this->loginLimitFor('root@naara.test'));
    }

    public function test_normal_user_keeps_the_tight_cap(): void
    {
        User::factory()->create(['email' => 'user@naara.test']);

        $this->assertSame(5, $this->loginLimitFor('user@naara.test'));
    }

    public function test_unknown_email_keeps_the_tight_cap(): void
    {
        $this->assertSame(5, $this->loginLimitFor('nobody@naara.test'));
    }
}
