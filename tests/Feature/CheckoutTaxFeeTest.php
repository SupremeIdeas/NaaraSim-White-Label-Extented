<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Livewire\GetNumber;
use App\Models\EsimPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 10 — a checkout tax/fee line, even though the real figure is $0.00:
 * NaaraSim charges no separate tax or fee on top of the retail price shown,
 * and the line itself is the trust signal (a customer is never left
 * wondering whether something will be added before Pay).
 */
class CheckoutTaxFeeTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-1', 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00, 'is_active' => true,
        ])->fresh();
    }

    public function test_esim_checkout_states_zero_taxes_and_fees(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Checkout::class, ['plan' => $this->plan()])
            ->assertSee('Taxes & fees')
            ->assertSee('$0.00');
    }

    public function test_naara_verify_modal_states_zero_taxes_and_fees(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(GetNumber::class, ['modal' => 'verify'])
            ->assertSee('Taxes & fees')
            ->assertSee('$0.00');
    }

    public function test_naara_rent_modal_states_zero_taxes_and_fees(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(GetNumber::class, ['modal' => 'rent'])
            ->assertSee('Taxes & fees')
            ->assertSee('$0.00');
    }
}
