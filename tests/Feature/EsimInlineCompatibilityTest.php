<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Livewire\Checkout;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Prompt 10 §3 — eSIM compatibility moved inline to the purchase flow:
 *   1) A "Check compatibility" prompt sits right at plan selection (Catalogue's
 *      plan-detail screen), reusing the same EsimCompatibility modal as the
 *      marketing hero, instead of only being reachable before a plan is chosen.
 *   2) Checkout's own device gate is a WARNING, not a hard block — a
 *      known-incompatible device no longer hides the acknowledgment checkbox,
 *      since a buyer may be purchasing for a second device or gifting it.
 */
class EsimInlineCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-1', 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00, 'is_active' => true,
        ])->fresh();
    }

    public function test_the_plan_detail_screen_offers_an_inline_compatibility_check(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $plan = $this->plan();

        Livewire::actingAs($user)->test(Catalogue::class)
            ->set('planId', $plan->id)
            ->assertSee('Is your device eSIM-ready?')
            ->assertSee('Check compatibility')
            ->assertSeeHtml("dispatch('open-compatibility')");
    }

    public function test_an_unsupported_device_shows_a_warning_not_a_block(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $this->plan()])
            ->set('device', 'iPhone 7')
            ->call('checkDevice')
            ->assertSet('deviceResult', false)
            ->assertSet('deviceConfirmed', false)
            ->assertSee('you can still continue')
            ->assertSee('I understand this device may not support eSIM and want to buy anyway');
    }

    public function test_a_purchase_succeeds_after_explicitly_acknowledging_an_incompatible_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        app(WalletService::class)->credit($user, 100, 'USD');

        $fake = new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']);
        app()->instance('esim.esimgo', $fake);

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $this->plan()])
            ->set('device', 'iPhone 7')
            ->call('checkDevice')
            ->assertSet('deviceResult', false)
            ->set('deviceConfirmed', true) // the explicit "buy anyway" acknowledgment click
            ->call('purchase')
            ->assertSet('done', true);

        $this->assertSame(1, EsimOrder::count());
    }
}
