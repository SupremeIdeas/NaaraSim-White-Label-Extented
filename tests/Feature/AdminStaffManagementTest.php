<?php

namespace Tests\Feature;

use App\Livewire\Admin\Staff;
use App\Models\User;
use App\Notifications\StaffAccountNotification;
use App\Services\Staff\StaffService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stronger staff management (owner request — fix_admin.md Part 5). Edit a staff
 * member, deactivate/reactivate, and delete — all super_admin-only and guarded
 * so an admin/super_admin account can never be touched via this surface.
 */
class AdminStaffManagementTest extends TestCase
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

    private function staffMember(): User
    {
        $u = User::factory()->create(['is_active' => true, 'password' => Hash::make('old')]);
        $u->assignRole('staff');

        return $u->fresh();
    }

    public function test_super_admin_can_edit_a_staff_members_details(): void
    {
        $staff = $this->staffMember();

        Livewire::actingAs($this->superAdmin())->test(Staff::class)
            ->call('editStaff', $staff->id)
            ->set('edit_name', 'Ada Staff')
            ->set('edit_email', 'ada.staff@example.com')
            ->set('edit_password', 'a-fresh-password')
            ->call('saveStaff')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertSame('Ada Staff', $staff->name);
        $this->assertSame('ada.staff@example.com', $staff->email);
        $this->assertTrue(Hash::check('a-fresh-password', $staff->password));
    }

    public function test_super_admin_can_deactivate_and_delete_staff(): void
    {
        Notification::fake();
        $staff = $this->staffMember();

        Livewire::actingAs($this->superAdmin())->test(Staff::class)
            ->call('toggleStaffActive', $staff->id);
        $this->assertTrue($staff->fresh()->isDeactivated());

        Livewire::actingAs($this->superAdmin())->test(Staff::class)
            ->call('deleteStaff', $staff->id);
        $this->assertNull(User::find($staff->id));
        // Sent synchronously (notifyNow) before the row was deleted.
        Notification::assertSentTo($staff, StaffAccountNotification::class, fn ($n) => $n->action === StaffAccountNotification::REMOVED);
    }

    public function test_staff_service_refuses_to_manage_an_admin(): void
    {
        $service = app(StaffService::class);
        $super = $this->superAdmin();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // An admin is not a manageable staff target — guarded 403.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->updateStaff($super, $admin, ['name' => 'x']);
    }

    public function test_a_plain_admin_cannot_manage_staff(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        // Staff page is super_admin-only — mount() aborts for a plain admin.
        Livewire::actingAs($admin)->test(Staff::class)->assertForbidden();
    }
}
