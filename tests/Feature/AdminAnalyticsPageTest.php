<?php

namespace Tests\Feature;

use App\Livewire\Admin\Analytics;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\GiftCardOrder;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantClientSubscription;
use App\Models\PaymentCharge;
use App\Models\ProviderRegistry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Analytics blueprint §7.3 — the dedicated Admin\Analytics deep-dive page:
 * wallet/FX, merchant leaderboard, operational health, provider reliability.
 * Admin-only, and every figure comes from PlatformAnalyticsService.
 */
class AdminAnalyticsPageTest extends TestCase
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

    public function test_the_page_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Analytics::class)->assertStatus(403);
    }

    public function test_an_admin_sees_every_analytics_section(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Analytics::class)
            ->assertOk()
            ->assertSee('Wallet & FX', false)
            ->assertSee('Merchants')
            ->assertSee('Operational health')
            ->assertSee('Provider reliability & eSIM usage', false);
    }

    public function test_deposit_and_merchant_volume_render_real_figures(): void
    {
        $admin = $this->admin();
        PaymentCharge::create(['gateway' => 'paystack', 'reference' => 'ref-1', 'amount' => 40, 'currency' => 'USD']);

        $owner = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $owner->id, 'business_name' => 'Alpha Travel', 'slug' => 'alpha-travel-'.uniqid(), 'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2]);
        $client = MerchantClient::create(['merchant_id' => $merchant->id, 'name' => 'Client A']);
        $plan = EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7, 'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ]);
        $order = EsimOrder::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 33, 'wholesale_cost' => 3, 'currency' => 'USD']);
        MerchantClientSubscription::create(['merchant_id' => $merchant->id, 'merchant_client_id' => $client->id, 'esim_order_id' => $order->id, 'plan_id' => $plan->id, 'esim_type' => 'data', 'status' => 'active']);

        Livewire::actingAs($admin)->test(Analytics::class)
            ->assertSee('paystack')
            ->assertSee('$40.00')
            ->assertSee('Alpha Travel')
            ->assertSee('$33.00');
    }

    public function test_provider_reliability_shows_degraded_providers(): void
    {
        $admin = $this->admin();
        ProviderRegistry::create(['provider_key' => 'flaky-provider', 'stack' => 'esim', 'success_rate_24h' => 0.40, 'circuit_breaker_state' => 'open', 'enabled' => true]);

        Livewire::actingAs($admin)->test(Analytics::class)
            ->assertSee('flaky-provider')
            ->assertSee('40.0%');
    }

    public function test_gift_card_revenue_never_appears_as_a_raw_provider_slug_here_either(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        GiftCardOrder::create([
            'user_id' => $user->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 50, 'currency' => 'USD', 'price_charged' => 52, 'status' => GiftCardOrder::STATUS_DELIVERED,
            'transaction_ref' => 'gift-analytics-page',
        ]);

        $component = Livewire::actingAs($admin)->test(Analytics::class)->assertOk();

        $this->assertStringNotContainsString('reloadly', json_encode($component->get('merchantLeaderboard')));
    }
}
