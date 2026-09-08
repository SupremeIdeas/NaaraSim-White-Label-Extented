<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Models\User;
use App\Support\MarketingCoupons;
use Database\Seeders\MarketingCouponsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coupon marketing nudges (owner request). A friendly first-purchase / comeback
 * discount is surfaced to accounts that haven't transacted — off by default, and
 * never shown to someone who already bought.
 */
class MarketingCouponsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketingCouponsSeeder::class);
        Cache::flush();
    }

    private function enable(): void
    {
        Setting::setValue(MarketingCoupons::FLAG, true, 'marketing');
    }

    public function test_no_nudge_when_the_feature_is_off(): void
    {
        $this->assertNull(MarketingCoupons::nudgeFor(User::factory()->create()));
    }

    public function test_a_brand_new_user_gets_the_welcome_offer(): void
    {
        $this->enable();
        $user = User::factory()->create(['name' => 'Ada Obi', 'created_at' => now()]);

        $nudge = MarketingCoupons::nudgeFor($user);
        $this->assertSame(MarketingCoupons::WELCOME_CODE, $nudge['code']);
        $this->assertStringContainsString('Ada', $nudge['title']);
        $this->assertSame(10, $nudge['percent']);
    }

    public function test_a_settled_idle_user_gets_the_comeback_offer(): void
    {
        $this->enable();
        $user = User::factory()->create(['created_at' => now()->subDays(10)]);

        $this->assertSame(MarketingCoupons::COMEBACK_CODE, MarketingCoupons::nudgeFor($user)['code']);
    }

    public function test_a_user_who_has_purchased_sees_no_nudge(): void
    {
        $this->enable();
        $user = User::factory()->create(['created_at' => now()->subDays(10)]);
        $plan = EsimPlan::create(['provider' => 'esimgo', 'provider_plan_id' => 'P1', 'name' => 'X', 'cost_price_usd' => 4]);
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'status' => 'processing', 'price_charged' => 10, 'wholesale_cost' => 4, 'currency' => 'USD',
        ]);
        Cache::flush();

        $this->assertNull(MarketingCoupons::nudgeFor($user->fresh()));
    }

    public function test_the_dashboard_shows_the_nudge_with_the_code(): void
    {
        $this->enable();
        $user = User::factory()->create(['name' => 'Ada', 'is_active' => true, 'created_at' => now()]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Welcome, Ada')
            ->assertSee(MarketingCoupons::WELCOME_CODE);
    }
}
