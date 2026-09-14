<?php

namespace Tests\Feature;

use App\Livewire\Admin\NavSettings;
use App\Models\NavItemOverride;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Bottom nav (owner request). Reorder/replace/hide catalog items for
 * the main nav and the Numbers section nav, persisted as NavItemOverride rows.
 */
class BottomNavSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u->fresh();
    }

    public function test_a_non_admin_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())->test(NavSettings::class)->assertStatus(403);
    }

    public function test_reordering_the_main_nav_persists_positions(): void
    {
        Livewire::actingAs($this->admin())->test(NavSettings::class)
            ->call('reorderMain', ['wallet', 'catalogue', 'numbers']);

        $this->assertSame('wallet', NavItemOverride::where('nav', 'main')->orderBy('position')->first()->item_key);
    }

    public function test_toggling_an_item_off_marks_it_inactive(): void
    {
        Livewire::actingAs($this->admin())->test(NavSettings::class)
            ->call('toggleMain', 'wallet')
            ->assertSet('mainHidden', ['wallet']);

        $this->assertFalse(NavItemOverride::where('nav', 'main')->where('item_key', 'wallet')->first()->is_active);
    }

    public function test_toggling_an_item_back_on_reactivates_it(): void
    {
        $component = Livewire::actingAs($this->admin())->test(NavSettings::class)
            ->call('toggleMain', 'wallet')
            ->call('toggleMain', 'wallet');

        $component->assertSet('mainHidden', []);
        $this->assertTrue(NavItemOverride::where('nav', 'main')->where('item_key', 'wallet')->first()->is_active);
    }

    public function test_reset_main_clears_all_overrides(): void
    {
        $admin = $this->admin();
        // A single toggle persists the whole catalog's current arrangement
        // (so future eligible-pool changes append gracefully) — not just the
        // one row touched.
        Livewire::actingAs($admin)->test(NavSettings::class)->call('toggleMain', 'wallet');
        $this->assertSame(count(\App\Support\BottomNav::MAIN_CATALOG), NavItemOverride::where('nav', 'main')->count());

        Livewire::actingAs($admin)->test(NavSettings::class)->call('resetMain');

        $this->assertSame(0, NavItemOverride::where('nav', 'main')->count());
    }

    public function test_numbers_nav_reorder_and_toggle_are_independent_of_main(): void
    {
        Livewire::actingAs($this->admin())->test(NavSettings::class)
            ->call('reorderNumbers', ['numbers.messages', 'numbers.contacts', 'numbers.dialer', 'numbers.forwarding'])
            ->call('toggleNumbers', 'numbers.dialer');

        $this->assertSame(0, NavItemOverride::where('nav', 'main')->count());
        // The full 5-key catalog (including Port In) gets tracked once the
        // admin touches this nav at all — Port In just starts out unmentioned.
        $this->assertSame(count(\App\Support\BottomNav::NUMBERS_CATALOG), NavItemOverride::where('nav', 'numbers')->count());
        $this->assertFalse(NavItemOverride::where('nav', 'numbers')->where('item_key', 'numbers.dialer')->first()->is_active);
    }
}
