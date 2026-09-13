<?php

namespace Tests\Feature;

use App\Livewire\Admin\EsimControlCenter;
use App\Livewire\Catalogue;
use App\Livewire\Checkout;
use App\Models\EsimPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 10 — the last item: any "unlimited" eSIM plan is structurally
 * required to disclose its fair-usage threshold. No provider integration
 * currently exposes a machine-readable per-plan FUP value, so a plan without
 * an admin-entered real threshold falls back to an honest, non-numeric
 * disclosure rather than a fabricated figure — but the disclosure itself is
 * never optional for an unlimited plan, and never shown for a capped one.
 */
class UnlimitedFairUsageDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private function unlimitedPlan(array $extra = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'unl-'.uniqid(), 'name' => 'USA Unlimited',
            'data_mb' => null, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 8.00, 'computed_retail_usd' => 20.00, 'is_active' => true,
        ], $extra))->fresh();
    }

    private function cappedPlan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'cap-'.uniqid(), 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00, 'is_active' => true,
        ])->fresh();
    }

    public function test_an_unlimited_plan_carries_the_generic_disclosure_by_default(): void
    {
        $plan = $this->unlimitedPlan();

        $this->assertSame(EsimPlan::DEFAULT_FAIR_USAGE_NOTE, $plan->display_fair_usage_note);
    }

    public function test_an_admin_entered_real_threshold_wins_over_the_generic_note(): void
    {
        $plan = $this->unlimitedPlan(['fair_usage_note' => '3GB/day at full speed, then 1Mbps until reset.']);

        $this->assertSame('3GB/day at full speed, then 1Mbps until reset.', $plan->display_fair_usage_note);
    }

    public function test_a_data_capped_plan_never_carries_a_fair_usage_note(): void
    {
        $plan = $this->cappedPlan();

        $this->assertNull($plan->display_fair_usage_note);
    }

    public function test_the_checkout_screen_discloses_fair_usage_for_an_unlimited_plan(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Checkout::class, ['plan' => $this->unlimitedPlan()])
            ->assertSee('fair usage policy');
    }

    public function test_the_checkout_screen_never_shows_a_fair_usage_note_for_a_capped_plan(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Checkout::class, ['plan' => $this->cappedPlan()])
            ->assertDontSee('fair usage policy');
    }

    public function test_the_plan_detail_screen_discloses_fair_usage_for_an_unlimited_plan(): void
    {
        $plan = $this->unlimitedPlan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->call('openPlan', $plan->id)
            ->assertSee('fair usage policy');
    }

    public function test_admin_can_set_a_real_fair_usage_threshold(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plan = $this->unlimitedPlan();

        Livewire::actingAs($admin)->test(EsimControlCenter::class)
            ->set('section', 'plans')
            ->call('editFairUsage', $plan->id)
            ->set('fairUsageValue', '2GB/day at full speed, then 512Kbps.')
            ->call('saveFairUsage', $plan->id);

        $this->assertSame('2GB/day at full speed, then 512Kbps.', $plan->fresh()->fair_usage_note);
    }

    public function test_admin_cannot_set_a_fair_usage_threshold_on_a_capped_plan(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plan = $this->cappedPlan();

        Livewire::actingAs($admin)->test(EsimControlCenter::class)
            ->set('section', 'plans')
            ->call('editFairUsage', $plan->id)
            ->set('fairUsageValue', 'should never be stored')
            ->call('saveFairUsage', $plan->id);

        $this->assertNull($plan->fresh()->fair_usage_note);
    }
}
