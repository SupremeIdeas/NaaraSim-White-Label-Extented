<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Users;
use App\Models\SmsOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin analytics + user management (owner request). super_admin/admin get
 * oversight metrics on the overview and a Users screen to search + activate /
 * deactivate accounts — but never delete (that stays the S26 lifecycle).
 */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_a_non_admin_cannot_open_the_users_screen(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Users::class)->assertStatus(404);
    }

    public function test_the_users_screen_lists_and_searches(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Ada Obi', 'email' => 'ada@example.com']);
        User::factory()->create(['name' => 'Ben Roy', 'email' => 'ben@example.com']);

        Livewire::actingAs($admin)->test(Users::class)
            ->assertSee('Ada Obi')
            ->assertSee('Ben Roy')
            ->set('search', 'ada')
            ->assertSee('Ada Obi')
            ->assertDontSee('Ben Roy');
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = $this->admin();
        $victim = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)->test(Users::class)
            ->call('toggleActive', $victim->id);
        $this->assertTrue($victim->fresh()->isDeactivated());

        Livewire::actingAs($admin)->test(Users::class)
            ->call('toggleActive', $victim->id);
        $this->assertFalse($victim->fresh()->isDeactivated());
    }

    public function test_an_admin_cannot_deactivate_themselves_or_a_super_admin(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(Users::class)->call('toggleActive', $admin->id);
        $this->assertFalse($admin->fresh()->isDeactivated()); // untouched

        $super = User::factory()->create(['is_active' => true]);
        $super->assignRole('super_admin');
        Livewire::actingAs($admin)->test(Users::class)->call('toggleActive', $super->id);
        $this->assertFalse($super->fresh()->isDeactivated()); // an admin can't touch a super admin
    }

    public function test_the_overview_shows_oversight_metrics(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();
        SmsOrder::create([
            'user_id' => $admin->id, 'provider' => 'fivesim', 'service_name' => 'whatsapp',
            'country' => 'nigeria', 'type' => 'otp', 'phone_number' => '+2348010000000',
            'status' => 'completed', 'provider_cost' => 0.2, 'charged_to_user' => 0.5, 'profit' => 0.3,
        ]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertSee('Registered users')
            ->assertSee('Profit — this week')
            ->assertSee('Most-bought numbers by country')
            ->assertSee('nigeria');
    }
}
