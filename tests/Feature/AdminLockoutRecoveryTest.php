<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin lockout recovery (owner request — fix_admin.md Part 1). The panel must
 * never become a silent trap: 2FA-required can't lock out every admin, and
 * break-glass Artisan commands recover a locked-out operator without touching
 * the database by hand.
 */
class AdminLockoutRecoveryTest extends TestCase
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

        return $u->fresh();
    }

    public function test_2fa_required_does_not_lock_out_when_nobody_has_enrolled(): void
    {
        SecuritySettings::flush();
        \App\Models\Setting::setValue('security.admin_2fa_required', true, 'security');
        SecuritySettings::flush();

        // No super_admin has confirmed 2FA → the panel must still open (anti-trap).
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get('/'.config('admin.path'))->assertOk();
    }

    public function test_2fa_required_is_enforced_once_a_super_admin_has_enrolled(): void
    {
        SecuritySettings::flush();
        \App\Models\Setting::setValue('security.admin_2fa_required', true, 'security');
        SecuritySettings::flush();

        // One super_admin IS enrolled → enforcement is real for everyone else.
        $enrolled = $this->superAdmin();
        $enrolled->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        $other = $this->superAdmin(); // not enrolled
        $this->actingAs($other->fresh())->get('/'.config('admin.path'))
            ->assertRedirect(route('admin.security'));
    }

    public function test_reset_2fa_command_clears_enrolment(): void
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt('[]')])->save();

        $this->artisan('admin:reset-2fa', ['email' => $admin->email])->assertSuccessful();

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
    }

    public function test_reset_password_command_sets_a_new_password_and_reactivates(): void
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['is_active' => false])->save();

        $this->artisan('admin:reset-password', ['email' => $admin->email, '--password' => 'BrandNewPass123!'])
            ->assertSuccessful();

        $admin->refresh();
        $this->assertTrue(Hash::check('BrandNewPass123!', $admin->password));
        $this->assertTrue((bool) $admin->is_active);
    }

    public function test_recovery_commands_fail_cleanly_for_an_unknown_email(): void
    {
        $this->artisan('admin:reset-2fa', ['email' => 'nobody@example.com'])->assertFailed();
        $this->artisan('admin:reset-password', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
