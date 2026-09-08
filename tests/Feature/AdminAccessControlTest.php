<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security;
use App\Models\User;
use App\Support\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel-managed admin access control (owner request — fix_admin.md Part 3).
 * Country + IP allow-lists, off by default, admin-controlled, fail-open on
 * unknowns, with a self-lockout confirmation guard.
 */
class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('super_admin');
        $u->forceFill([
            'two_factor_secret' => encrypt('S'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
        ])->save();

        return $u->fresh();
    }

    public function test_country_allowlist_blocks_a_disallowed_country(): void
    {
        SecuritySettings::flush();
        \App\Models\Setting::setValue('security.admin_country_allowlist_enabled', true, 'security');
        \App\Models\Setting::setValue('security.admin_allowed_countries', ['NG'], 'security');
        SecuritySettings::flush();

        $admin = $this->superAdmin();

        // A request from GB (Cloudflare header) is blocked → plain 404.
        $this->actingAs($admin)
            ->withHeaders(['CF-IPCountry' => 'GB'])
            ->get('/'.config('admin.path'))
            ->assertNotFound();

        // A request from NG is allowed.
        $this->actingAs($admin)
            ->withHeaders(['CF-IPCountry' => 'NG'])
            ->get('/'.config('admin.path'))
            ->assertOk();
    }

    public function test_country_allowlist_fails_open_when_country_is_unknown(): void
    {
        SecuritySettings::flush();
        \App\Models\Setting::setValue('security.admin_country_allowlist_enabled', true, 'security');
        \App\Models\Setting::setValue('security.admin_allowed_countries', ['NG'], 'security');
        SecuritySettings::flush();

        // No CF-IPCountry header → country can't be resolved → allowed (no lockout).
        $this->actingAs($this->superAdmin())
            ->get('/'.config('admin.path'))
            ->assertOk();
    }

    public function test_saving_rules_that_would_lock_out_requires_acknowledgement(): void
    {
        $admin = $this->superAdmin();

        // Resolve the current request's country to GB for the self-lockout guard.
        $this->app->bind(\App\Support\Geo\GeoResolver::class, fn () => new class implements \App\Support\Geo\GeoResolver {
            public function country(\Illuminate\Http\Request $request): ?string
            {
                return 'GB';
            }
        });

        // Allow only NG while we're in GB → risky → needs ack.
        Livewire::actingAs($admin)
            ->test(Security::class)
            ->set('country_allowlist_enabled', true)
            ->set('allowed_countries', 'NG')
            ->set('lockout_ack', false)
            ->call('saveAccessControl')
            ->assertHasErrors('lockout_ack');

        $this->assertFalse(SecuritySettings::adminCountryAllowlistEnabled());

        // With the acknowledgement, it saves.
        Livewire::actingAs($admin)
            ->test(Security::class)
            ->set('country_allowlist_enabled', true)
            ->set('allowed_countries', 'NG')
            ->set('lockout_ack', true)
            ->call('saveAccessControl')
            ->assertHasNoErrors();

        SecuritySettings::flush();
        $this->assertTrue(SecuritySettings::adminCountryAllowlistEnabled());
        $this->assertSame(['NG'], SecuritySettings::adminAllowedCountries());
    }

    public function test_panel_ip_allowlist_blocks_other_ips(): void
    {
        SecuritySettings::flush();
        \App\Models\Setting::setValue('security.admin_ip_allowlist_enabled', true, 'security');
        \App\Models\Setting::setValue('security.admin_ip_allowlist', ['203.0.113.9'], 'security');
        SecuritySettings::flush();

        // Test requests come from 127.0.0.1 → not in the list → 404.
        $this->actingAs($this->superAdmin())
            ->get('/'.config('admin.path'))
            ->assertNotFound();
    }
}
