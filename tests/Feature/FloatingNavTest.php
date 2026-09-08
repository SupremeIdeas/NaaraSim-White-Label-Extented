<?php

namespace Tests\Feature;

use App\Livewire\Admin\NavSlots as AdminNavSlots;
use App\Livewire\Wizard;
use App\Models\NavSlot;
use App\Models\User;
use App\Support\NavSlots;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Floating navigation pill: admin-assignable NavSlots, auth-state visibility,
 * the Wizard centrepiece merge, and rendering on the (public) marketing layout.
 */
class FloatingNavTest extends TestCase
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

    public function test_defaults_seed_on_first_read_with_a_centerpiece(): void
    {
        $this->assertSame(0, NavSlot::count());

        $bar = NavSlots::bar(authed: false);

        $this->assertGreaterThan(0, NavSlot::count());
        $this->assertNotNull($bar['center']);
        $this->assertTrue($bar['center']->opensWizard());
    }

    public function test_visibility_splits_guest_and_auth_slots(): void
    {
        // A guest-only and an auth-only slot alongside a shared one.
        NavSlot::create(['position' => 1, 'label' => 'Home', 'icon' => 'globe', 'target' => 'home', 'visibility' => 'all', 'is_center' => false, 'is_active' => true]);
        NavSlot::create(['position' => 2, 'label' => 'Get started', 'icon' => 'id-card', 'target' => 'register', 'visibility' => 'guest', 'is_center' => false, 'is_active' => true]);
        NavSlot::create(['position' => 2, 'label' => 'Account', 'icon' => 'id-card', 'target' => 'dashboard', 'visibility' => 'auth', 'is_center' => false, 'is_active' => true]);
        NavSlots::flush();

        $guestLabels = collect(array_merge(NavSlots::bar(false)['left'], NavSlots::bar(false)['right']))->pluck('label');
        $authLabels = collect(array_merge(NavSlots::bar(true)['left'], NavSlots::bar(true)['right']))->pluck('label');

        $this->assertTrue($guestLabels->contains('Get started'));
        $this->assertFalse($guestLabels->contains('Account'));
        $this->assertTrue($authLabels->contains('Account'));
        $this->assertFalse($authLabels->contains('Get started'));
    }

    public function test_the_bar_is_capped_to_three_regular_items(): void
    {
        NavSlots::ensureSeeded(); // 3 items + wizard centre by default
        foreach (range(1, 4) as $i) {
            NavSlot::create(['position' => 10 + $i, 'label' => "Extra {$i}", 'icon' => 'globe', 'target' => 'home', 'visibility' => 'all', 'is_center' => false, 'is_active' => true]);
        }
        NavSlots::flush();

        $bar = NavSlots::bar(false);
        $this->assertCount(3, array_merge($bar['left'], $bar['right']));
        $this->assertNotNull($bar['center']);
    }

    public function test_marketing_page_renders_the_floating_nav(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Ask NaaraSim')   // the glowing centrepiece
            ->assertSee('Home');          // a regular slot
    }

    public function test_admin_can_repoint_a_slot(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AdminNavSlots::class)->assertStatus(403);

        $slot = NavSlot::create(['label' => 'Blog', 'icon' => 'file-text', 'target' => 'home', 'visibility' => 'all', 'position' => 9, 'is_active' => true]);
        NavSlots::flush();

        Livewire::actingAs($this->admin())->test(AdminNavSlots::class)
            ->set("slots.{$slot->id}.target", 'about')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('about', $slot->fresh()->target);
    }

    public function test_only_one_centerpiece_survives_a_save(): void
    {
        NavSlots::ensureSeeded();
        // Force a second centre, then save should demote all but one.
        $extra = NavSlot::create(['label' => 'Two', 'icon' => 'grid', 'target' => '/', 'visibility' => 'all', 'position' => 8, 'is_active' => true, 'is_center' => true]);

        Livewire::actingAs($this->admin())->test(AdminNavSlots::class)->call('save');

        $this->assertSame(1, NavSlot::where('is_center', true)->count());
    }

    public function test_wizard_opens_from_the_nav_event(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(Wizard::class)
            ->assertSet('open', false)
            ->call('openFromNav')
            ->assertSet('open', true);
    }
}
