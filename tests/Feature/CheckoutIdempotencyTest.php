<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Double-submit money-safety (security audit — rule 7: "money actions never
 * blind-retry"). The wallet debit is idempotent on the per-purchase reference,
 * but placing the provider order is NOT. A same-second re-submit must be
 * recognised (the debit returns the existing row) and must NOT buy a second
 * eSIM at our cost against a single charge.
 */
class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'idem-'.uniqid(), 'name' => 'USA 3GB 30D',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    public function test_a_same_second_double_submit_charges_and_orders_exactly_once(): void
    {
        // Freeze time so both submits share the same reference (the real race is
        // two requests in the same second).
        Carbon::setTestNow(Carbon::create(2026, 7, 22, 12, 0, 0));

        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        $fake = new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']);
        app()->instance('esim.esimgo', $fake);

        $component = Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true);

        $component->call('purchase')->assertSet('done', true);
        // The second (racing) submit lands on the same reference.
        $component->call('purchase')->assertSet('done', true);

        // Provider ordered ONCE, order persisted ONCE, wallet charged ONCE.
        $this->assertSame(1, $fake->orderCalls, 'provider must not be ordered twice');
        $this->assertSame(1, EsimOrder::where('user_id', $user->id)->count());
        $this->assertSame('10.0000', (string) $user->wallet->fresh()->usd_balance);

        Carbon::setTestNow();
    }
}
