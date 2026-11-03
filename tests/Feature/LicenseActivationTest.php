<?php

namespace Tests\Feature;

use App\Livewire\Admin\LicenseActivation;
use App\Models\Setting;
use App\Models\User;
use App\Support\Activation\LicenseState;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LicenseActivationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin'): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function fakeMaster(): void
    {
        Http::fake([
            '*/api/v1/white-label/activate' => Http::response(['message' => 'License activated.', 'token' => '7|secrettoken', 'instance' => ['brand_name' => 'Acme', 'slug' => 'acme', 'tier' => 'normal', 'status' => 'active']]),
            '*/api/v1/white-label/entitlement' => Http::response(['level' => 'basic', 'locks' => []]),
        ]);
    }

    public function test_a_fresh_install_shows_not_activated_and_the_key_form(): void
    {
        $this->actingAs($this->admin())->get(route('admin.license', ['adminGateway' => config('admin.path')]))
            ->assertOk()->assertSee('data-license-status="none"', false)->assertSee('data-license-form', false);
        $this->assertSame('none', LicenseState::status());
    }

    public function test_activating_stores_an_encrypted_token_registers_the_domain_and_unlocks(): void
    {
        $this->fakeMaster();
        Livewire::actingAs($this->admin())->test(LicenseActivation::class)
            ->set('licenseKey', 'WL-ABCD-1234')->set('platformUrl', 'https://platform.example')->call('activate')
            ->assertSet('error', null)->assertSee('Activated');

        $this->assertSame('active', LicenseState::status());
        $this->assertSame('7|secrettoken', LicenseState::token());
        $this->assertNotSame('7|secrettoken', Setting::getValue('activation.token'));   // stored encrypted
        $this->assertSame('1234', LicenseState::get('key_last4'));
        $this->assertNull(Setting::getValue('activation.license_key'));                  // the key itself is never kept

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v1/white-label/activate')
            && $r['license_key'] === 'WL-ABCD-1234' && $r['domain'] !== '' && isset($r['current_version']));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/entitlement') && $r->hasHeader('X-Instance-Domain') && $r->hasHeader('Authorization'));
    }

    public function test_a_refused_key_changes_nothing_and_says_why(): void
    {
        Http::fake(['*' => Http::response(['message' => 'nope'], 403)]);
        Livewire::actingAs($this->admin())->test(LicenseActivation::class)
            ->set('licenseKey', 'BAD')->set('platformUrl', 'https://platform.example')->call('activate')
            ->assertSee('was not accepted');
        $this->assertSame('none', LicenseState::status());
    }

    public function test_an_unreachable_platform_is_explained(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        Livewire::actingAs($this->admin())->test(LicenseActivation::class)
            ->set('licenseKey', 'K')->set('platformUrl', 'https://platform.example')->call('activate')
            ->assertSee('Could not reach the platform');
        $this->assertSame('none', LicenseState::status());
    }

    public function test_unsafe_platform_addresses_are_refused(): void
    {
        Http::fake();
        app()->detectEnvironment(fn () => 'production');
        foreach (['http://platform.example', 'https://10.0.0.5', 'https://user:pw@platform.example', 'ftp://x'] as $url) {
            $this->assertFalse(LicenseState::validPlatformUrl($url), $url);
        }
        $this->assertTrue(LicenseState::validPlatformUrl('https://platform.example'));
        Http::assertNothingSent();
    }

    public function test_the_heartbeat_reports_in_and_a_revoked_token_is_shown_without_locking_the_site(): void
    {
        $this->fakeMaster();
        Livewire::actingAs($this->admin())->test(LicenseActivation::class)->set('licenseKey', 'K1234')->set('platformUrl', 'https://platform.example')->call('activate');

        $this->artisan('whitelabel:check-in')->assertSuccessful();
        $this->assertNotSame('', (string) LicenseState::get('last_checkin_at'));

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        $this->artisan('whitelabel:check-in')->assertFailed();
        $this->assertSame('revoked', LicenseState::status());
        $this->get('/')->assertOk();   // the public site keeps working
    }

    public function test_the_check_in_does_nothing_before_activation(): void
    {
        Http::fake();
        $this->artisan('whitelabel:check-in')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_only_admins_reach_the_screen_and_only_a_super_admin_can_disconnect(): void
    {
        $user = User::factory()->create();
        $this->seed(RoleSeeder::class);
        $user->assignRole('user');
        $this->actingAs($user)->get(route('admin.license', ['adminGateway' => config('admin.path')]))->assertNotFound();

        $this->fakeMaster();
        Livewire::actingAs($this->admin('admin'))->test(LicenseActivation::class)
            ->set('licenseKey', 'K9999')->set('platformUrl', 'https://platform.example')->call('activate')
            ->call('disconnect')->assertForbidden();
        $this->assertSame('active', LicenseState::status());
    }
}
