<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Models\EsimPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * eSIM Data / Full eSIMs tabs (esim_upgrade Part 2). The storefront filters on
 * has_voice; the Full tab is deep-linkable via ?tab=full and shows a coming-soon
 * state until voice plans exist.
 */
class EsimCatalogueTabsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, bool $voice): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => $name,
            'has_voice' => $voice, 'is_active' => true, 'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ]);
    }

    public function test_the_data_tab_shows_data_only_plans(): void
    {
        $this->plan('USA Data 3GB', voice: false);
        $this->plan('Europe Voice 5GB', voice: true);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSet('tab', 'data')
            ->assertSee('USA Data 3GB')
            ->assertDontSee('Europe Voice 5GB');
    }

    public function test_the_full_tab_shows_voice_plans_and_is_deep_linkable(): void
    {
        $this->plan('USA Data 3GB', voice: false);
        $this->plan('Europe Voice 5GB', voice: true);

        // Deep link ?tab=full lands on the Full tab.
        Livewire::actingAs(User::factory()->create())->withQueryParams(['tab' => 'full'])
            ->test(Catalogue::class)
            ->assertSet('tab', 'full')
            ->assertSee('Europe Voice 5GB')
            ->assertDontSee('USA Data 3GB');
    }

    public function test_the_full_tab_shows_coming_soon_when_empty(): void
    {
        $this->plan('USA Data 3GB', voice: false); // only data plans

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->call('setTab', 'full')
            ->assertSee('Naara Connect is coming soon');
    }
}
