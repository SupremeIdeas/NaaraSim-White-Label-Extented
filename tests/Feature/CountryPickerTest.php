<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Livewire\CountryPicker;
use App\Models\EsimPlan;
use App\Models\User;
use App\Support\CountryPickerSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ONE shared country picker (S31) + its eSIM wiring. The picker is a dumb
 * view over CountryPickerSources; the catalogue opens it, filters on the pick,
 * and ignores picks meant for another opener.
 */
class CountryPickerTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, array $countries, bool $voice = false): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => $name,
            'has_voice' => $voice, 'is_active' => true, 'countries' => $countries,
            'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ]);
    }

    public function test_esim_source_lists_countries_with_live_plan_counts(): void
    {
        $this->plan('NG A', ['NG']);
        $this->plan('NG B', ['NG', 'GH']);
        $this->plan('Voice NG', ['NG'], voice: true); // other line — excluded from data

        CountryPickerSources::flush();
        $options = CountryPickerSources::options('esim', ['has_voice' => false]);

        $byCode = collect($options)->keyBy('code');
        $this->assertSame(2, $byCode['NG']['count']);          // two data plans reach NG
        $this->assertSame('Nigeria', $byCode['NG']['name']);   // intl name resolution
        $this->assertSame(1, $byCode['GH']['count']);
        // Sorted by name: Ghana before Nigeria.
        $this->assertSame(['GH', 'NG'], array_column($options, 'code'));
    }

    public function test_the_picker_emits_the_pick_with_its_opener_token(): void
    {
        Livewire::actingAs(User::factory()->create())->test(CountryPicker::class)
            ->call('openModal', 'esim', ['has_voice' => false], 'catalogue', 'Browse')
            ->assertSet('open', true)
            ->call('pick', 'NG', 'Nigeria')
            ->assertDispatched('country-picked', code: 'NG', name: 'Nigeria', for: 'catalogue')
            ->assertSet('open', false);
    }

    public function test_the_catalogue_filters_on_a_pick_meant_for_it(): void
    {
        $this->plan('Nigeria 3GB', ['NG']);
        $this->plan('Ghana 3GB', ['GH']);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('Nigeria 3GB')
            ->assertSee('Ghana 3GB')
            // A pick for another opener must NOT touch the catalogue.
            ->call('onCountryPicked', 'GH', 'Ghana', 'somewhere-else')
            ->assertSet('country', '')
            // A pick addressed to the catalogue filters it.
            ->call('onCountryPicked', 'NG', 'Nigeria', 'catalogue')
            ->assertSet('country', 'NG')
            ->assertSee('Nigeria 3GB')
            ->assertDontSee('Ghana 3GB');
    }

    public function test_switching_line_clears_the_country_filter(): void
    {
        $this->plan('Nigeria 3GB', ['NG']);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->call('onCountryPicked', 'NG', 'Nigeria', 'catalogue')
            ->assertSet('country', 'NG')
            ->call('setTab', 'full')
            ->assertSet('country', '');
    }
}
