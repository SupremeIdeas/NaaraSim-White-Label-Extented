<?php

namespace Tests\Feature;

use App\Livewire\GetNumber;
use App\Models\User;
use App\Support\NumberCatalogue;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The number/rental catalogue (blueprint Section 12) — the storefront lists the
 * COMPLETE set of countries and services, a broad static base extended by a live
 * provider sync, never a hand-curated handful.
 */
class NumberCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        NumberCatalogue::flush();
    }

    public function test_the_static_base_is_broad_not_a_handful(): void
    {
        $this->assertGreaterThan(100, count(NumberCatalogue::baseCountries()));
        $this->assertGreaterThan(90, count(NumberCatalogue::baseServices()));
        // The old hard-coded picker only had ~6 of each.
        $this->assertArrayHasKey('japan', NumberCatalogue::countries());
        $this->assertArrayHasKey('binance', NumberCatalogue::services());
    }

    public function test_service_label_falls_back_to_a_titlecased_slug(): void
    {
        $this->assertSame('WhatsApp', NumberCatalogue::serviceLabel('whatsapp'));
        $this->assertSame('Some New App', NumberCatalogue::serviceLabel('some_new_app'));
    }

    public function test_synced_data_extends_the_catalogue_and_never_shrinks_it(): void
    {
        $before = count(NumberCatalogue::countries());
        NumberCatalogue::storeSynced(['narnia' => 'Narnia'], ['newservice' => 'New Service']);

        $this->assertArrayHasKey('narnia', NumberCatalogue::countries());
        $this->assertArrayHasKey('newservice', NumberCatalogue::services());
        $this->assertGreaterThan($before, count(NumberCatalogue::countries()));

        // An empty sync (a failed provider fetch) must not wipe the stored list.
        NumberCatalogue::storeSynced([], []);
        $this->assertArrayHasKey('narnia', NumberCatalogue::countries());
    }

    public function test_the_sync_command_imports_the_live_provider_catalogue(): void
    {
        config(['services.fivesim.api_key' => 'jwt-token', 'services.fivesim.base_url' => 'https://5sim.net/v1']);
        Http::fake([
            '*/guest/countries' => Http::response([
                'usa' => ['iso' => ['US' => 1], 'text_en' => 'United States'],
                'faroe' => ['text_en' => 'Faroe Islands'],
                'any' => ['text_en' => 'Any'],
            ], 200),
            '*/guest/products/*' => Http::response([
                'whatsapp' => ['Category' => 'activation', 'Price' => 10],
                'brandnewapp' => ['Category' => 'activation', 'Price' => 5],
            ], 200),
        ]);

        $this->artisan('numbers:catalogue-sync')->assertSuccessful();

        // A provider country not in the base is now available.
        $this->assertArrayHasKey('faroe', NumberCatalogue::countries());
        // A provider service not in the base is added (base labels are preserved).
        $this->assertArrayHasKey('brandnewapp', NumberCatalogue::services());
        $this->assertSame('WhatsApp', NumberCatalogue::services()['whatsapp']); // base label kept
    }

    public function test_get_number_page_offers_the_full_catalogue(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_active' => true]);

        // The landing loads the full catalogue…
        Livewire::actingAs($user)->test(GetNumber::class)
            ->assertViewHas('countries', fn ($c) => count($c) > 100)
            ->assertViewHas('services', fn ($s) => count($s) > 90);

        // …and the service picker (opened from the Verify/Rent modals) renders it.
        Livewire::actingAs($user)->test(\App\Livewire\ServicePicker::class)
            ->call('openModal', 'numbers')
            ->assertSee('WhatsApp')
            ->assertSee('Binance');
    }
}
