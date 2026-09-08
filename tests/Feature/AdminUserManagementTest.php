<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Full user management (owner request — fix_admin.md Part 4). Admins can edit a
 * user's profile, reset their password, force-logout, and (super_admin) manage
 * the admin role — all guarded and audited.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(string $role = 'admin'): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole($role);

        return $u->fresh();
    }

    public function test_admin_can_edit_a_users_profile_and_email_change_re_verifies(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

        Livewire::actingAs($admin)->test(Users::class)
            ->call('editUser', $target->id)
            ->set('edit_name', 'New Name')
            ->set('edit_email', 'new@example.com')
            ->call('saveUser')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertNull($target->email_verified_at);
    }

    public function test_admin_can_generate_a_temp_password_and_force_logout(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['password' => Hash::make('old-pass')]);
        DB::table('sessions')->insert([
            'id' => 'sess-1', 'user_id' => $target->id, 'ip_address' => '1.2.3.4',
            'user_agent' => 'Test', 'payload' => 'x', 'last_activity' => time(),
        ]);

        Livewire::actingAs($admin)->test(Users::class)
            ->call('generateTempPassword', $target->id)
            ->assertSet('tempPassword', fn ($p) => is_string($p) && strlen($p) >= 12);

        // Password changed and the user's sessions were cleared.
        $this->assertFalse(Hash::check('old-pass', $target->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_only_super_admin_can_toggle_the_admin_role(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');

        // A plain admin cannot (403).
        Livewire::actingAs($this->admin('admin'))->test(Users::class)
            ->call('toggleAdmin', $target->id)
            ->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('admin'));

        // A super_admin can.
        Livewire::actingAs($this->admin('super_admin'))->test(Users::class)
            ->call('toggleAdmin', $target->id);
        $this->assertTrue($target->fresh()->hasRole('admin'));
    }

    public function test_an_admin_cannot_manage_a_super_admin(): void
    {
        $admin = $this->admin('admin');
        $super = $this->admin('super_admin');

        Livewire::actingAs($admin)->test(Users::class)->call('editUser', $super->id);

        // Guard blocked it — edit buffer never opened for the super admin.
        Livewire::actingAs($admin)->test(Users::class)
            ->call('editUser', $super->id)
            ->assertSet('editing', false);
    }

    public function test_email_reset_link_is_sent(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)->test(Users::class)->call('sendPasswordReset', $target->id);

        Notification::assertSentTo($target, \App\Notifications\ResetPasswordNotification::class);
    }
}
