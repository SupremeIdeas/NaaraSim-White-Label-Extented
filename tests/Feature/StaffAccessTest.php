<?php

namespace Tests\Feature;

use App\Livewire\Admin\AccountDeletions;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Staff as StaffPanel;
use App\Models\User;
use App\Notifications\StaffAccountNotification;
use App\Services\Staff\StaffService;
use App\Support\StaffScopes;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function withRole(string $role, array $scopes = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($scopes) {
            $user->givePermissionTo($scopes);
        }
        // Confirmed 2FA so full-page admin requests pass the Module 14 gate.
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_a_super_admin_can_create_staff_with_scopes(): void
    {
        Notification::fake();
        $super = $this->withRole('super_admin');

        Livewire::actingAs($super)->test(StaffPanel::class)
            ->set('name', 'Kofi Staff')
            ->set('email', 'kofi@naara.test')
            ->set('password', 'secret-pass-1')
            ->set('scopes', ['kyc.review', 'tickets.manage'])
            ->call('createStaff')
            ->assertHasNoErrors();

        $staff = User::where('email', 'kofi@naara.test')->firstOrFail();
        $this->assertTrue($staff->hasRole('staff'));
        $this->assertTrue($staff->hasPermissionTo('kyc.review'));
        $this->assertTrue($staff->hasPermissionTo('tickets.manage'));
        $this->assertFalse($staff->hasPermissionTo('refunds.process'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created']);
        Notification::assertSentTo($staff, StaffAccountNotification::class, fn ($n) => $n->action === StaffAccountNotification::GRANTED);
    }

    public function test_staff_enter_the_panel_but_not_admin_config_or_staff_management(): void
    {
        $staff = $this->withRole('staff', ['orders.assist']);

        // Panel entry (Overview) is reachable…
        $this->actingAs($staff)->get('/adminmaster')->assertOk();
        // …but admin configuration and staff management are not.
        $this->actingAs($staff)->get('/adminmaster/pricing')->assertForbidden();
        $this->actingAs($staff)->get('/adminmaster/deletions')->assertForbidden();
        $this->actingAs($staff)->get('/adminmaster/staff')->assertForbidden();
    }

    public function test_the_staff_overview_hides_business_figures(): void
    {
        $staff = $this->withRole('staff', ['providers.view']);

        Livewire::actingAs($staff)->test(Dashboard::class)
            ->assertSet('privileged', false)
            ->assertSee('Your access')
            ->assertDontSee('Gross profit')
            ->assertDontSee('Revenue (30d)');
    }

    public function test_only_a_super_admin_can_open_staff_management(): void
    {
        // A plain admin is refused at the route level…
        $admin = $this->withRole('admin');
        $this->actingAs($admin)->get('/adminmaster/staff')->assertForbidden();

        // …and the component itself refuses a non-super actor.
        Livewire::actingAs($admin)->test(StaffPanel::class)->assertStatus(403);

        // A super admin gets in.
        $super = $this->withRole('super_admin');
        $this->actingAs($super)->get('/adminmaster/staff')->assertOk();
    }

    public function test_a_non_super_admin_cannot_manage_staff_or_escalate(): void
    {
        $service = new StaffService;
        $admin = $this->withRole('admin');
        $staff = $this->withRole('staff');

        // The management guard refuses any non-super actor.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->syncScopes($admin, $staff, ['kyc.review']);
    }

    public function test_grantable_scopes_are_bounded_by_what_the_actor_holds(): void
    {
        $service = new StaffService;

        // super_admin can grant everything.
        $this->assertSame(StaffScopes::all(), $service->grantableScopes($this->withRole('super_admin')));

        // A staff member can only grant the scopes they themselves hold.
        $staff = $this->withRole('staff', ['kyc.review', 'orders.assist']);
        $this->assertEqualsCanonicalizing(['kyc.review', 'orders.assist'], $service->grantableScopes($staff));
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $service = new StaffService;
        $super = $this->withRole('super_admin');
        $staff = $this->withRole('staff');

        try {
            $service->syncScopes($super, $staff, ['not.a.real.scope']);
            $this->fail('Expected an HttpException for an unknown scope.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_staff_cannot_delete_a_user_even_with_every_scope(): void
    {
        // A staff member holding all scopes still cannot approve a deletion.
        $staff = $this->withRole('staff', StaffScopes::all());

        $victim = User::factory()->create();
        $victim->assignRole('user');
        $victim->forceFill(['deletion_requested_at' => now()])->save();

        Livewire::actingAs($staff)->test(AccountDeletions::class)
            ->call('approve', $victim->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_a_super_admin_promotes_an_existing_active_user_to_staff(): void
    {
        Notification::fake();
        $super = $this->withRole('super_admin');

        $customer = User::factory()->create(['is_active' => true]);
        $customer->assignRole('user');

        (new StaffService)->promote($super, $customer, ['kyc.review', 'orders.assist']);

        $customer->refresh();
        // Gains staff + scopes…
        $this->assertTrue($customer->hasRole('staff'));
        $this->assertTrue($customer->hasPermissionTo('kyc.review'));
        // …but keeps the `user` role, so they still enjoy the end-user app.
        $this->assertTrue($customer->hasRole('user'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.promoted']);
        Notification::assertSentTo($customer, StaffAccountNotification::class, fn ($n) => $n->action === StaffAccountNotification::GRANTED);
    }

    public function test_a_staff_member_can_still_use_the_end_user_app(): void
    {
        $super = $this->withRole('super_admin');
        $customer = User::factory()->create(['is_active' => true]);
        $customer->assignRole('user');
        (new StaffService)->promote($super, $customer, ['orders.assist']);

        // The end-user app has no admin 2FA gate — a staff member reaches it.
        $this->actingAs($customer->fresh())->get('/dashboard')->assertOk();
    }

    public function test_login_uses_the_single_user_facing_route_and_lands_on_the_app(): void
    {
        $this->assertSame('/dashboard', config('fortify.home'));

        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $user->assignRole('user');

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/dashboard');
    }

    public function test_an_admin_or_inactive_account_cannot_be_promoted(): void
    {
        $service = new StaffService;
        $super = $this->withRole('super_admin');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        try {
            $service->promote($super, $admin, []);
            $this->fail('Expected an admin to be un-promotable.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $inactive = User::factory()->create(['is_active' => false]);
        $inactive->assignRole('user');
        try {
            $service->promote($super, $inactive, []);
            $this->fail('Expected an inactive user to be un-promotable.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_revoking_staff_keeps_the_account_but_strips_role_and_scopes(): void
    {
        Notification::fake();
        $service = new StaffService;
        $super = $this->withRole('super_admin');
        $staff = $this->withRole('staff', ['kyc.review']);

        $service->revokeStaff($super, $staff);

        $staff->refresh();
        $this->assertTrue(User::whereKey($staff->id)->exists()); // account kept
        $this->assertFalse($staff->hasRole('staff'));
        $this->assertFalse($staff->hasPermissionTo('kyc.review'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.revoked']);
        Notification::assertSentTo($staff, StaffAccountNotification::class, fn ($n) => $n->action === StaffAccountNotification::REVOKED);
    }
}
