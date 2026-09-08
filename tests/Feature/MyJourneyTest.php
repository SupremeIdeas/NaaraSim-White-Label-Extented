<?php

namespace Tests\Feature;

use App\Livewire\Journey;
use App\Models\CreditLedger;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserWallet;
use App\Support\CreditSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "My Journey" — both tabs are read-only views over the user's OWN real data
 * (CreditLedger for milestones, EsimOrder for the travel timeline). These
 * tests assert the derived numbers (streak, totals) match what was actually
 * seeded, and that nothing is shown that didn't really happen.
 */
class MyJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CreditSettings::flush();
    }

    private function ledger(User $user, string $source, float $amount, \DateTimeInterface $at): CreditLedger
    {
        UserWallet::firstOrCreate(['user_id' => $user->id]);

        $row = CreditLedger::create([
            'user_id' => $user->id, 'type' => 'earn', 'source' => $source, 'amount' => $amount,
            'balance_after' => $amount, 'reference' => $source.':'.uniqid(),
        ]);
        // created_at/updated_at aren't fillable, and a normal save() re-touches
        // updated_at to "now" — disable timestamp management for this one
        // write so streak/history tests can control real dates.
        $row->timestamps = false;
        $row->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        return $row->fresh();
    }

    public function test_milestones_reflect_the_users_real_ledger_history(): void
    {
        $user = User::factory()->create();
        $this->ledger($user, 'signup', 10, now()->subDays(30));
        $this->ledger($user, 'first_purchase', 50, now()->subDays(20));
        $this->ledger($user, 'ad_reward', 2, now()->subDays(1));
        $this->ledger($user, 'ad_reward', 2, now());
        $this->ledger($user, 'social_instagram', 5, now()->subDays(5));

        Livewire::actingAs($user)->test(Journey::class)
            ->assertSet('tab', 'milestones')
            ->assertSee('Welcome bonus')
            ->assertSee('+10 credits')
            ->assertSee('First purchase')
            ->assertSee('+50 credits')
            ->assertSee('2 watched')
            ->assertSee('+4 credits total')
            ->assertSee('Other rewards')
            ->assertSee('1 reward');
    }

    public function test_a_locked_milestone_shows_no_earned_meta(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Journey::class)
            ->assertSee('Welcome bonus')
            ->assertDontSee('+0 credits');
    }

    public function test_checkin_streak_counts_consecutive_days_and_breaks_on_a_gap(): void
    {
        $user = User::factory()->create();
        // Today, yesterday, day-before -> a 3-day streak.
        $this->ledger($user, 'checkin', 5, now());
        $this->ledger($user, 'checkin', 5, now()->subDay());
        $this->ledger($user, 'checkin', 5, now()->subDays(2));
        // A gap, then an older isolated check-in that must NOT extend the streak.
        $this->ledger($user, 'checkin', 5, now()->subDays(10));

        Livewire::actingAs($user)->test(Journey::class)
            ->assertSee('3-day streak')
            ->assertSee('4 total check-ins');
    }

    public function test_switching_tabs_updates_the_url_bound_tab_property(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Journey::class)
            ->assertSet('tab', 'milestones')
            ->call('setTab', 'travel')
            ->assertSet('tab', 'travel')
            ->call('setTab', 'not-a-real-tab')
            ->assertSet('tab', 'milestones');
    }

    public function test_travel_tab_shows_the_users_own_esim_orders_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $plan = EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Nigeria 5GB',
            'data_mb' => 5120, 'validity_days' => 30, 'countries' => ['NG'], 'has_voice' => false,
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 12.00, 'is_active' => true,
        ]);
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'provider_order_ref' => 'ref-1', 'status' => 'active', 'activated_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28), 'price_charged' => 12.00, 'wholesale_cost' => 4.00, 'currency' => 'USD',
        ]);
        EsimOrder::create([
            'user_id' => $other->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'provider_order_ref' => 'ref-2', 'status' => 'active', 'price_charged' => 12.00,
            'wholesale_cost' => 4.00, 'currency' => 'USD',
        ]);

        Livewire::actingAs($user)->test(Journey::class)
            ->call('setTab', 'travel')
            ->assertSee('Nigeria 5GB')
            ->assertSee('Active')
            ->assertSee('5.0 GB')
            ->assertDontSee('ref-2');
    }

    public function test_travel_tab_empty_state_when_the_user_has_no_esims(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Journey::class)
            ->call('setTab', 'travel')
            ->assertSee('No eSIMs yet');
    }

    public function test_milestones_tab_is_hidden_when_naaracredits_is_disabled(): void
    {
        Setting::setValue('credits.enabled', false, 'credits');
        CreditSettings::flush();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Journey::class)
            ->assertSee("NaaraCredits isn't enabled", false);
    }
}
