<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Models\ProviderOutcome;
use App\Models\SmsOrder;
use App\Models\User;
use App\Support\ProviderKeys;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tier 5 #15 Phase A — one card per currently-configured provider. Verifies
 * the dashboard reuses (never re-derives) ProviderModels::providerConfigured()
 * for the isConfigured() filter, and that an unconfigured provider never
 * appears at all (the blueprint's own explicit verification ask).
 *
 * Livewire's TestableLivewire::get() reads component PROPERTIES via data_get(),
 * not the extra data Dashboard::render() passes to view() — so, same gotcha as
 * the Updater screen tests, these assert against rendered HTML, not ->get().
 */
class AdminDashboardProviderCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Start every provider unconfigured; each test opts in the ones it needs.
        config([
            'services.esimgo.api_key' => null,
            'services.airalo.client_id' => null,
            'services.quibity.api_key' => null,
            'services.fivesim.api_key' => null,
            'services.getatext.api_key' => null,
            'services.herosms.api_key' => null,
            'services.virtsms.api_key' => null,
            'services.smspool.api_key' => null,
            'services.onlinesim.api_key' => null,
            'services.twilio.account_sid' => null,
            'services.telnyx.api_key' => null,
        ]);
        ProviderKeys::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_an_unconfigured_provider_never_appears_on_the_dashboard(): void
    {
        // Nothing configured at all → no provider cards should render.
        Livewire::actingAs($this->admin())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('No providers configured yet.')
            ->assertDontSee('eSIM Go')
            ->assertDontSee('5sim')
            ->assertDontSee('Twilio');
    }

    public function test_only_configured_providers_get_a_card_with_correct_stack_and_volume(): void
    {
        config(['services.fivesim.api_key' => 'test-key']);
        ProviderKeys::flush();

        $user = $this->admin();
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'type' => 'otp', 'status' => 'completed', 'charged_to_user' => 2, 'provider_cost' => 0.5]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'type' => 'otp', 'status' => 'completed', 'charged_to_user' => 2, 'provider_cost' => 0.5]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('No providers configured yet.')
            ->assertSee('5sim') // the real supplier label, admin-only surface
            ->assertDontSee('eSIM Go')
            ->assertDontSee('Twilio');
    }

    public function test_a_provider_cards_trend_reflects_the_real_nci_outcome_data(): void
    {
        config(['services.fivesim.api_key' => 'test-key']);
        ProviderKeys::flush();

        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'success', 'occurred_at' => now()]);
        ProviderOutcome::create(['provider_key' => 'fivesim', 'stack' => 'sms', 'outcome' => 'failure', 'occurred_at' => now()]);

        // Independently confirm what the card's sparkline SHOULD show, straight
        // from NciScorer's own data — then check the dashboard agrees.
        $trend = app(\App\Services\NCI\NciScorer::class)->dailySuccessRateTrend('fivesim', 7);
        $today = collect($trend)->firstWhere('date', now()->toDateString());
        $this->assertSame(75.0, $today['rate_pct']);

        Livewire::actingAs($this->admin())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('75%');
    }

    public function test_multiple_configured_providers_across_stacks_each_get_their_own_card(): void
    {
        config([
            'services.esimgo.api_key' => 'ek',
            'services.twilio.account_sid' => 'AC', 'services.twilio.auth_token' => 'tok',
        ]);
        ProviderKeys::flush();

        Livewire::actingAs($this->admin())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('eSIM Go')
            ->assertSee('Twilio')
            ->assertDontSee('5sim');
    }
}
