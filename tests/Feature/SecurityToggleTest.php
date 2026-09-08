<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security as SecurityPanel;
use App\Models\Setting;
use App\Models\User;
use App\Support\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-toggleable runtime security controls (blueprint Section 30) — the
 * non-technical operator can switch CSP/HSTS off if they clash with their host.
 */
class SecurityToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_turn_off_the_csp_header_and_it_applies_live(): void
    {
        // On by default.
        $this->get('/login')->assertHeader('Content-Security-Policy');

        // Toggle off — the Setting::saved hook busts the cache, no redeploy.
        Setting::setValue('security.csp_enabled', false);

        $this->assertFalse(SecuritySettings::cspEnabled());
        $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_only_a_super_admin_can_change_site_protection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SecurityPanel::class)
            ->call('saveSiteProtection')
            ->assertStatus(403);

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        Livewire::actingAs($super)->test(SecurityPanel::class)
            ->set('csp_enabled', false)
            ->set('hsts_enabled', true)
            ->call('saveSiteProtection')
            ->assertHasNoErrors();

        $this->assertFalse(SecuritySettings::cspEnabled());
        $this->assertTrue(SecuritySettings::hstsEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.settings_updated']);
    }
}
