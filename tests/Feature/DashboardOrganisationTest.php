<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\MyLines;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\SmsOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The reorganised "My Connectivity" hub: numbers grouped by public Model,
 * expired/finished items in an Archive, the raw supplier never rendered. The
 * full grouping now lives on the dedicated My Lines page; the dashboard keeps a
 * slim summary that links to it.
 */
class DashboardOrganisationTest extends TestCase
{
    use RefreshDatabase;

    private function number(User $u, string $provider, ?string $type, string $status, string $service): SmsOrder
    {
        return SmsOrder::create([
            'user_id' => $u->id, 'provider' => $provider, 'service_name' => $service,
            'type' => $type, 'phone_number' => '+1555000'.rand(1000, 9999), 'status' => $status,
            'provider_cost' => 0.2, 'charged_to_user' => 0.5, 'profit' => 0.3, 'ordered_at' => now(),
        ]);
    }

    public function test_numbers_group_by_model_and_finished_ones_go_to_archive(): void
    {
        $u = User::factory()->create();
        $this->number($u, 'fivesim', 'otp', 'completed', 'whatsapp');   // Naara Verify (active)
        $this->number($u, 'fivesim', 'rental', 'waiting', 'telegram');  // Naara Rent (active)
        $this->number($u, 'herosms', 'otp', 'cancelled', 'google'); // archived (cancelled)

        Livewire::actingAs($u)->test(MyLines::class)
            ->assertSee('Naara Verify')
            ->assertSee('Naara Rent')
            ->assertSee('Archive (1)')          // the cancelled number
            // Suppliers are never rendered.
            ->assertDontSee('fivesim')
            ->assertDontSee('herosms')
            ->assertDontSee('5sim');

        // The dashboard keeps a slim summary that links to the full My Lines page.
        Livewire::actingAs($u)->test(Dashboard::class)
            ->assertSee('My Lines')
            ->assertSee('Active numbers');
    }

    public function test_expired_esims_move_to_the_archive(): void
    {
        $u = User::factory()->create();
        $plan = EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'd-'.uniqid(), 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4, 'computed_retail_usd' => 10,
        ]);
        EsimOrder::create([
            'user_id' => $u->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 10, 'wholesale_cost' => 4, 'currency' => 'USD',
        ]);
        EsimOrder::create([
            'user_id' => $u->id, 'plan_id' => $plan->id, 'provider' => 'airalo',
            'status' => 'expired', 'price_charged' => 10, 'wholesale_cost' => 4, 'currency' => 'USD',
        ]);

        Livewire::actingAs($u)->test(MyLines::class)
            ->assertSee('Naara Data')
            ->assertSee('Archive (1 expired)')
            ->assertDontSee('esimgo')
            ->assertDontSee('airalo');
    }

    public function test_a_brand_new_user_sees_the_showcase_not_empty_lists(): void
    {
        $u = User::factory()->create();
        Livewire::actingAs($u)->test(Dashboard::class)
            ->assertSee('eSIM Data Plans')   // the first-visit showcase
            ->assertSee('Verification Numbers');
    }
}
