<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Models\GiftCardOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Analytics blueprint §7.1/§7.3 — the confirmed gap this PR closes: Naara
 * Gift revenue used to be invisible on the Admin Dashboard entirely. Now
 * PlatformAnalyticsService feeds the revenue hero + split donut, so a gift
 * card sale must actually show up there — end-to-end, not just unit-tested
 * on the service.
 */
class AdminDashboardAnalyticsTest extends TestCase
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

        return $u;
    }

    public function test_naara_gift_revenue_appears_on_the_admin_dashboard(): void
    {
        $admin = $this->admin();
        GiftCardOrder::create([
            'user_id' => $admin->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 75, 'currency' => 'USD', 'price_charged' => 78, 'status' => GiftCardOrder::STATUS_DELIVERED,
            'transaction_ref' => 'gift-dashboard-1',
        ]);

        // The revenue split segments never carry a raw provider slug (unlike the
        // legitimate "Products" section further down, which names real
        // providers like "reloadly" for admin configuration purposes).
        $component = Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertSee('Naara Gift')
            ->assertSee('$78.00');

        $split = collect($component->get('split') ?? [])->toArray();
        $this->assertStringNotContainsString('reloadly', json_encode($split));
    }

    public function test_the_revenue_hero_still_renders_correctly_with_zero_gift_card_sales(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Revenue — last 30 days')
            ->assertSee('Naara Gift');
    }
}
