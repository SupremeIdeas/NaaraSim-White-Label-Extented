<?php

namespace Tests\Feature;

use App\Livewire\MerchantClients;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\MerchantSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Merchant V2 eSIM assign picker — search + country filter (owner request:
 * the flat 200-name dropdown made finding a specific country's plan slow).
 * Mirrors the customer catalogue's whereJsonContains('countries', ...) query
 * so results are exactly what's actually assignable for that country.
 */
class MerchantEsimPickerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue(MerchantSettings::FLAG, true);
    }

    private function merchant(): Merchant
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 50, 'USD', ['reference' => 'seed:'.$owner->id]);

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_V2, 'reseller_margin_pct' => 10,
        ]);
    }

    private function plan(string $name, array $countries, bool $hasVoice = false): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => $name,
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => $countries, 'has_voice' => $hasVoice,
            'is_active' => true, 'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ]);
    }

    public function test_search_filters_the_picker_by_plan_name(): void
    {
        $merchant = $this->merchant();
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);
        $this->plan('France 5GB', ['FR']);
        $this->plan('Germany 5GB', ['DE']);

        $component = Livewire::actingAs($merchant->owner)->test(MerchantClients::class)
            ->call('openAssign', $client->id)
            ->set('assignSearch', 'France');

        $dataPlans = $component->viewData('dataPlans');
        $this->assertCount(1, $dataPlans);
        $this->assertSame('France 5GB', $dataPlans->first()->name);
    }

    public function test_country_filter_shows_only_plans_that_actually_cover_it(): void
    {
        $merchant = $this->merchant();
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);
        $france = $this->plan('Europe Regional', ['FR', 'DE', 'IT']);
        $this->plan('Japan 5GB', ['JP']);

        $component = Livewire::actingAs($merchant->owner)->test(MerchantClients::class)
            ->call('openAssign', $client->id)
            ->set('assignCountry', 'FR');

        $dataPlans = $component->viewData('dataPlans');
        $this->assertCount(1, $dataPlans);
        $this->assertSame($france->id, $dataPlans->first()->id);
    }

    public function test_the_country_dropdown_reflects_real_active_plans_for_the_active_line(): void
    {
        $merchant = $this->merchant();
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);
        $this->plan('France Data', ['FR'], hasVoice: false);
        $this->plan('France Connect', ['FR'], hasVoice: true);

        $component = Livewire::actingAs($merchant->owner)->test(MerchantClients::class)
            ->call('openAssign', $client->id);

        // Naara Data is the default tab.
        $codes = collect($component->viewData('assignCountryOptions'))->pluck('code');
        $this->assertContains('FR', $codes);
    }

    public function test_switching_line_clears_the_stale_country_filter_and_selection(): void
    {
        $merchant = $this->merchant();
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);
        $plan = $this->plan('France Data', ['FR']);

        Livewire::actingAs($merchant->owner)->test(MerchantClients::class)
            ->call('openAssign', $client->id)
            ->set('assignCountry', 'FR')
            ->set('assignPlanId', $plan->id)
            ->set('assignType', 'connect')
            ->assertSet('assignCountry', '')
            ->assertSet('assignPlanId', null);
    }

    public function test_a_search_with_no_matches_returns_an_empty_picker_not_an_error(): void
    {
        $merchant = $this->merchant();
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);
        $this->plan('France 5GB', ['FR']);

        $component = Livewire::actingAs($merchant->owner)->test(MerchantClients::class)
            ->call('openAssign', $client->id)
            ->set('assignSearch', 'Nonexistent Plan Name')
            ->assertOk();

        $this->assertCount(0, $component->viewData('dataPlans'));
    }

    public function test_opening_assign_resets_previous_search_and_country(): void
    {
        $merchant = $this->merchant();
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);

        Livewire::actingAs($merchant->owner)->test(MerchantClients::class)
            ->set('assignSearch', 'leftover')
            ->set('assignCountry', 'FR')
            ->call('openAssign', $client->id)
            ->assertSet('assignSearch', '')
            ->assertSet('assignCountry', '');
    }
}
