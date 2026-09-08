<?php

namespace Tests\Feature;

use App\Livewire\Admin\JourneyGoals as AdminJourneyGoals;
use App\Livewire\Journey;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\JourneyGoal;
use App\Models\JourneyGoalClaim;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\Journey\JourneyGoalService;
use App\Support\CreditSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Journey Goals — admin-defined achievements that pay real NaaraCredits the
 * moment a user's OWN activity (never invented) crosses a target. The hard
 * guarantee under test throughout: a (user, goal, period) is never paid twice.
 */
class JourneyGoalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CreditSettings::flush();
    }

    private function plan(string $country = 'NG'): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => $country.' plan',
            'data_mb' => 1024, 'validity_days' => 30, 'countries' => [$country], 'has_voice' => false,
            'cost_price_usd' => 2.00, 'computed_retail_usd' => 5.00, 'is_active' => true,
        ]);
    }

    private function order(User $user, EsimPlan $plan): EsimOrder
    {
        return EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'provider_order_ref' => 'ref-'.uniqid(), 'status' => 'active',
            'price_charged' => 5.00, 'wholesale_cost' => 2.00, 'currency' => 'USD',
        ]);
    }

    public function test_a_lifetime_goal_pays_out_once_when_the_real_target_is_reached(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id]);
        $goal = JourneyGoal::create([
            'title' => 'First Steps', 'description' => 'Buy 2 eSIMs', 'metric' => 'esim_purchases',
            'target' => 2, 'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 25,
            'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);

        $plan = $this->plan();
        $this->order($user, $plan);

        // Only 1 of 2 — not yet reached.
        app(JourneyGoalService::class)->evaluate($user);
        $this->assertSame('0.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(0, JourneyGoalClaim::count());

        $this->order($user, $plan);

        // 2 of 2 — reached, paid exactly once even if evaluated repeatedly.
        app(JourneyGoalService::class)->evaluate($user);
        app(JourneyGoalService::class)->evaluate($user);
        app(JourneyGoalService::class)->evaluate($user);

        $this->assertSame('25.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(1, JourneyGoalClaim::where('journey_goal_id', $goal->id)->count());
    }

    public function test_countries_reached_counts_distinct_countries_only(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id]);
        JourneyGoal::create([
            'title' => 'Explorer', 'description' => 'Reach 3 countries', 'metric' => 'countries_reached',
            'target' => 3, 'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 40,
            'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);

        $ng = $this->plan('NG');
        $this->order($user, $ng);
        $this->order($user, $ng); // same country again — must not double-count
        $this->order($user, $this->plan('GH'));

        app(JourneyGoalService::class)->evaluate($user);
        $this->assertSame('0.00', (string) $user->wallet->fresh()->naara_credits); // only 2 distinct so far

        $this->order($user, $this->plan('KE'));
        app(JourneyGoalService::class)->evaluate($user);
        $this->assertSame('40.00', (string) $user->wallet->fresh()->naara_credits);
    }

    public function test_a_monthly_goal_can_be_reached_again_in_a_later_period(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id]);
        $goal = JourneyGoal::create([
            'title' => 'Monthly Buyer', 'description' => 'Buy 1 eSIM this month', 'metric' => 'esim_purchases',
            'target' => 1, 'period_type' => JourneyGoal::PERIOD_MONTHLY, 'reward_credits' => 10,
            'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);

        // Simulate "last month was already claimed" directly (a different
        // period_key), so this test proves a PAST claim never blocks the
        // CURRENT period rather than relying on wall-clock month rollover.
        $lastMonthKey = now()->subMonthNoOverflow()->format('Y-m');
        JourneyGoalClaim::create([
            'journey_goal_id' => $goal->id, 'user_id' => $user->id, 'period_key' => $lastMonthKey,
            'achieved_value' => 1, 'credits_granted' => 10, 'reference' => 'test:already-claimed-last-month',
        ]);

        // A real purchase THIS month must still pay out under the new key.
        $this->order($user, $this->plan());
        app(JourneyGoalService::class)->evaluate($user);
        $this->assertSame('10.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(2, JourneyGoalClaim::where('journey_goal_id', $goal->id)->count()); // last month + this month

        // And re-evaluating THIS month again must not double-pay it.
        app(JourneyGoalService::class)->evaluate($user);
        $this->assertSame('10.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(2, JourneyGoalClaim::where('journey_goal_id', $goal->id)->count());
    }

    public function test_a_merchant_only_goal_is_invisible_and_zero_for_a_regular_user(): void
    {
        $user = User::factory()->create();
        JourneyGoal::create([
            'title' => 'Networker', 'description' => 'Connect 3 clients', 'metric' => 'merchant_clients_connected',
            'target' => 3, 'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 30,
            'audience' => JourneyGoal::AUDIENCE_MERCHANT_V2, 'is_active' => true,
        ]);

        $engine = app(JourneyGoalService::class);
        $this->assertCount(0, $engine->goalsFor($user));
    }

    public function test_merchant_clients_connected_counts_real_clients_for_the_owner(): void
    {
        $owner = User::factory()->create();
        UserWallet::create(['user_id' => $owner->id]);
        $merchant = Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Test Co', 'slug' => 'test-co-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_V2,
        ]);
        $goal = JourneyGoal::create([
            'title' => 'Networker', 'description' => 'Connect 2 clients', 'metric' => 'merchant_clients_connected',
            'target' => 2, 'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 30,
            'audience' => JourneyGoal::AUDIENCE_MERCHANT_V2, 'is_active' => true,
        ]);

        $engine = app(JourneyGoalService::class);
        $this->assertCount(1, $engine->goalsFor($owner));

        MerchantClient::create(['merchant_id' => $merchant->id, 'name' => 'A', 'is_active' => true]);
        $engine->evaluate($owner);
        $this->assertSame('0.00', (string) $owner->wallet->fresh()->naara_credits);

        MerchantClient::create(['merchant_id' => $merchant->id, 'name' => 'B', 'is_active' => true]);
        $engine->evaluate($owner);
        $this->assertSame('30.00', (string) $owner->wallet->fresh()->naara_credits);
        $this->assertSame(1, JourneyGoalClaim::where('journey_goal_id', $goal->id)->count());
    }

    public function test_a_campaign_goal_is_only_visible_inside_its_own_window(): void
    {
        $user = User::factory()->create();
        JourneyGoal::create([
            'title' => 'Past campaign', 'description' => '...', 'metric' => 'esim_purchases', 'target' => 1,
            'period_type' => JourneyGoal::PERIOD_CAMPAIGN, 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(1),
            'reward_credits' => 5, 'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);
        JourneyGoal::create([
            'title' => 'Live campaign', 'description' => '...', 'metric' => 'esim_purchases', 'target' => 1,
            'period_type' => JourneyGoal::PERIOD_CAMPAIGN, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'reward_credits' => 5, 'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);

        $visible = app(JourneyGoalService::class)->goalsFor($user);
        $this->assertCount(1, $visible);
        $this->assertSame('Live campaign', $visible->first()->title);
    }

    public function test_evaluate_grants_nothing_when_naaracredits_is_disabled(): void
    {
        Setting::setValue('credits.enabled', false, 'credits');
        CreditSettings::flush();
        $user = User::factory()->create();
        JourneyGoal::create([
            'title' => 'X', 'description' => 'Y', 'metric' => 'esim_purchases', 'target' => 1,
            'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 5,
            'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);
        $this->order($user, $this->plan());

        $this->assertSame([], app(JourneyGoalService::class)->evaluate($user));
        $this->assertSame(0, JourneyGoalClaim::count());
    }

    public function test_the_journey_page_lazily_grants_a_goal_reached_on_load(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id]);
        JourneyGoal::create([
            'title' => 'First Steps', 'description' => 'Buy 1 eSIM', 'metric' => 'esim_purchases',
            'target' => 1, 'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 15,
            'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);
        $this->order($user, $this->plan());

        Livewire::actingAs($user)->test(Journey::class)
            ->call('setTab', 'goals')
            ->assertSee('First Steps')
            ->assertSee('Reached');

        $this->assertSame('15.00', (string) $user->wallet->fresh()->naara_credits);
    }

    public function test_admin_can_create_edit_and_pause_a_goal(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(AdminJourneyGoals::class)
            ->set('title', 'World Traveler')
            ->set('description', 'Reach 5 countries')
            ->set('metric', 'countries_reached')
            ->set('target', 5)
            ->set('period_type', 'lifetime')
            ->set('reward_credits', 50)
            ->call('save');

        $goal = JourneyGoal::firstOrFail();
        $this->assertSame('World Traveler', $goal->title);
        $this->assertTrue($goal->is_active);

        Livewire::actingAs($admin)->test(AdminJourneyGoals::class)
            ->call('edit', $goal->id)
            ->set('target', 8)
            ->call('save');
        $this->assertSame('8.00', (string) $goal->fresh()->target);

        Livewire::actingAs($admin)->test(AdminJourneyGoals::class)->call('toggle', $goal->id);
        $this->assertFalse($goal->fresh()->is_active);
    }

    public function test_admin_can_attach_and_remove_a_goal_image(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(AdminJourneyGoals::class)
            ->set('title', 'Weekend Warrior')
            ->set('description', 'Buy 3 eSIMs in a weekend')
            ->set('metric', 'esim_purchases')
            ->set('target', 3)
            ->set('period_type', 'lifetime')
            ->set('reward_credits', 20)
            ->set('image', UploadedFile::fake()->image('goal.jpg', 200, 200))
            ->call('save')
            ->assertHasNoErrors();

        $goal = JourneyGoal::where('title', 'Weekend Warrior')->firstOrFail();
        $this->assertNotNull($goal->image_path);

        // The uploaded image shows on the customer-facing My Journey card.
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Journey::class)
            ->call('setTab', 'goals')
            ->assertSee($goal->image_path, false);

        Livewire::actingAs($admin)->test(AdminJourneyGoals::class)
            ->call('edit', $goal->id)
            ->call('removeImage');

        $this->assertNull($goal->fresh()->image_path);
    }

    public function test_a_goal_with_claims_is_paused_not_deleted(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $user = User::factory()->create();
        $goal = JourneyGoal::create([
            'title' => 'X', 'description' => 'Y', 'metric' => 'esim_purchases', 'target' => 1,
            'period_type' => JourneyGoal::PERIOD_LIFETIME, 'reward_credits' => 5,
            'audience' => JourneyGoal::AUDIENCE_ALL, 'is_active' => true,
        ]);
        JourneyGoalClaim::create([
            'journey_goal_id' => $goal->id, 'user_id' => $user->id, 'period_key' => 'lifetime',
            'achieved_value' => 1, 'credits_granted' => 5, 'reference' => 'test:claim',
        ]);

        Livewire::actingAs($admin)->test(AdminJourneyGoals::class)->call('delete', $goal->id);

        $this->assertNotNull($goal->fresh());
        $this->assertFalse($goal->fresh()->is_active);
    }
}
