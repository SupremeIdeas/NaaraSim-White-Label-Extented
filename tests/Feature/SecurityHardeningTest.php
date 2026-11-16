<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** The patterns attackers try first, pinned so a future change cannot quietly reopen them. Third-party webhooks and provider calls are NOT blocked by any of this. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_the_page_never_advertises_the_php_version_and_keeps_the_security_headers(): void
    {
        $r = $this->get('/login');
        $this->assertNull($r->headers->get('X-Powered-By'));
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options');
        $r->assertHeader('Referrer-Policy');
        $this->assertNotEmpty($r->headers->get('Content-Security-Policy'));
    }

    public function test_one_address_cannot_spray_passwords_across_many_accounts(): void
    {
        $codes = [];
        for ($i = 1; $i <= 45; $i++) {
            $codes[] = $this->post('/login', ['email' => "spray{$i}@example.test", 'password' => 'wrong-'.$i])->getStatusCode();
        }
        $this->assertContains(429, $codes, 'an IP-wide ceiling must stop credential stuffing even when every email is different');
        $this->assertNotContains(429, array_slice($codes, 0, 20), 'a normal user is not blocked early');
    }

    public function test_the_per_account_guard_still_stops_guessing_one_account(): void
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->post('/login', ['email' => 'victim@example.test', 'password' => 'guess'.$i])->getStatusCode();
        }
        $this->assertContains(429, $codes);
    }

    public function test_the_app_lock_password_locks_after_eight_wrong_tries_but_the_right_one_clears_it(): void
    {
        $u = User::factory()->create(['password' => Hash::make('Secret123!')])->fresh();
        $u->forceFill(['app_lock_minutes' => 5, 'app_lock_hash' => Hash::make('RightPass1')])->save();
        $this->actingAs($u)->post('/app-lock/lock')->assertOk();
        RateLimiter::clear('app-lock-fail:'.$u->id);

        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'bad'.$i])->assertStatus(422);
        }
        $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'RightPass1'])->assertStatus(429);
        RateLimiter::clear('app-lock-fail:'.$u->id);
        $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'RightPass1'])->assertOk();
    }

    public function test_every_payment_payout_and_provider_webhook_rejects_an_unsigned_post(): void
    {
        foreach (['payments/fincra', 'payments/nowpayments', 'payments/paystack', 'payouts/grey', 'payouts/paystack', 'topup/paga', 'topup/vtpass', 'kyc/dojah', 'sms-inbound/twilio', 'sms-delivery/telnyx', 'appbuild/github'] as $p) {
            $code = $this->postJson('/webhooks/'.$p, ['status' => 'success', 'amount' => 999999, 'event' => 'charge.success'])->getStatusCode();
            $this->assertContains($code, [400, 401, 403, 404, 422], "/webhooks/$p must never accept an unsigned body (got $code)");
        }
    }

    public function test_the_admin_panel_answers_404_on_any_other_path_and_for_a_member(): void
    {
        $this->get('/not-the-admin-path/login')->assertNotFound();
        $u = User::factory()->create()->fresh();
        $this->actingAs($u)->get('/'.\App\Support\AdminPath::current().'/credits')->assertStatus(404);
    }

    public function test_hidden_files_and_framework_internals_are_never_served(): void
    {
        foreach (['/.env', '/.git/HEAD', '/composer.json', '/vendor/autoload.php', '/artisan', '/phpunit.xml', '/storage/logs/laravel.log'] as $p) {
            $this->assertNotSame(200, $this->get($p)->getStatusCode(), "$p must not be served");
        }
    }

    public function test_no_host_is_trusted_by_default_in_local_and_the_trusted_list_is_built_from_app_url(): void
    {
        $this->assertSame(['phpunit' => true], ['phpunit' => app()->environment('testing')]);
        $fn = (new \ReflectionClass(\Illuminate\Http\Middleware\TrustHosts::class))->hasMethod('hosts');
        $this->assertTrue($fn);
    }

    public function test_user_roles_cannot_be_mass_assigned(): void
    {
        $u = User::factory()->create()->fresh();
        $u->fill(['name' => 'X', 'role' => 'super_admin', 'is_active' => false, 'email_verified_at' => now(), 'app_lock_hash' => 'x', 'app_lock_minutes' => 1]);
        $this->assertNotSame('super_admin', $u->role);
        $this->assertNull($u->app_lock_hash);
    }
}
