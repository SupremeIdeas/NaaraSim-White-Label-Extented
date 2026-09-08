<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Services\eSIM\CatalogueSyncService;
use App\Services\eSIM\EsimProviderInterface;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
    }

    private function bindProvider(string $name, array $catalogue): void
    {
        app()->instance("esim.{$name}", new class($catalogue) implements EsimProviderInterface
        {
            public function __construct(private array $catalogue)
            {
            }

            public function getCatalogue(): array
            {
                return $this->catalogue;
            }

            public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
            {
                return [];
            }

            public function getEsim(string $iccid): array
            {
                return [];
            }

            public function getUsage(string $iccid, string $bundleName): array
            {
                return [];
            }

            public function revoke(string $iccid, string $bundleName): array
            {
                return [];
            }

            public function getBalance(): float
            {
                return 0.0;
            }
        });
    }

    public function test_airalo_sync_stores_net_price_as_cost_and_minimum_selling_price_as_floor(): void
    {
        $this->bindProvider('airalo', [
            [
                'operators' => [[
                    'countries' => [['country_code' => 'US'], ['country_code' => 'CA']],
                    'packages' => [[
                        'id' => 'us-1gb-30',
                        'title' => 'US 1GB 30D',
                        'type' => 'sim',
                        'amount' => 1000,
                        'day' => 30,
                        'net_price' => 3.50,               // our cost
                        'minimum_selling_price' => 4.90,   // contractual floor
                        'price' => 9.99,                   // Airalo's own retail — must NOT be used
                    ]],
                ]],
            ],
        ]);

        $count = app(CatalogueSyncService::class)->sync('airalo');
        $this->assertSame(1, $count);

        $plan = EsimPlan::where('provider', 'airalo')->firstOrFail();
        $this->assertSame('us-1gb-30', $plan->provider_plan_id);
        $this->assertSame('3.5000', (string) $plan->cost_price_usd);
        $this->assertSame('4.9000', (string) $plan->airalo_min_price);
        $this->assertSame(1000, $plan->data_mb);
        $this->assertSame(30, $plan->validity_days);
        $this->assertSame(['US', 'CA'], $plan->countries);

        // Retail recomputed: 3.50 * 1.30 = 4.55, but below the 4.90 floor, so
        // the Airalo min guard lifts final_retail to 4.90.
        $this->assertSame(4.90, (float) $plan->fresh()->final_retail_usd);

        // Airalo's own retail (9.99) is never our retail.
        $this->assertNotSame(9.99, (float) $plan->fresh()->final_retail_usd);
    }

    public function test_esimgo_sync_maps_price_to_cost_and_recomputes_retail(): void
    {
        $this->bindProvider('esimgo', [
            'bundles' => [[
                'name' => 'esim_1GB_US_30D',
                'description' => 'USA 1GB 30 Days',
                'type' => 'data',
                'dataAmount' => 1000,
                'duration' => 30,
                'countries' => [['iso' => 'US']],
                'price' => 2.00,
            ]],
        ]);

        app(CatalogueSyncService::class)->sync('esimgo');

        $plan = EsimPlan::where('provider', 'esimgo')->firstOrFail();
        $this->assertSame('2.0000', (string) $plan->cost_price_usd);
        $this->assertSame(['US'], $plan->countries);
        // 2.00 * 1.30 = 2.60 (above the 0.50 floor, no guard).
        $this->assertSame(2.60, (float) $plan->fresh()->final_retail_usd);
    }

    public function test_synced_plan_never_leaks_cost_in_serialized_output(): void
    {
        $this->bindProvider('quibity', [
            'data' => [[
                'id' => 99,
                'name' => 'Global 5GB',
                'data_mb' => 5000,
                'validity_days' => 30,
                'countries' => ['US', 'GB'],
                'price' => 7.25,
            ]],
        ]);

        app(CatalogueSyncService::class)->sync('quibity');

        $payload = EsimPlan::where('provider', 'quibity')->firstOrFail()->toArray();
        $this->assertArrayNotHasKey('cost_price_usd', $payload);
        $this->assertArrayHasKey('final_retail_usd', $payload);
    }

    public function test_zendit_sync_flags_voice_offers_and_stores_fixed_cost_as_private(): void
    {
        $this->bindProvider('zendit', ['list' => [
            [
                'offerId' => 'ng-voice-5gb',
                'brandName' => 'MTN Nigeria',
                'country' => 'NG',
                'regions' => [],
                'dataGB' => 5,
                'dataUnlimited' => false,
                'durationDays' => 30,
                'voiceMinutes' => 100,          // → has_voice = true (Naara Connect)
                'voiceUnlimited' => false,
                'smsNumber' => 50,
                'enabled' => true,
                // Cost is minor units / divisor: 400 / 100 = $4.00 wholesale (PRIVATE).
                'cost' => ['fixed' => 400, 'currency' => 'USD', 'currencyDivisor' => 100],
                // Zendit's suggested retail — must never become our retail.
                'price' => ['suggestedFixed' => 999, 'currencyDivisor' => 100],
            ],
            [
                'offerId' => 'ng-data-3gb',
                'brandName' => 'MTN Nigeria',
                'country' => 'NG',
                'dataGB' => 3,
                'durationDays' => 30,
                'voiceMinutes' => 0,            // → has_voice = false (data tab)
                'enabled' => true,
                'cost' => ['fixed' => 250, 'currency' => 'USD', 'currencyDivisor' => 100],
            ],
        ]]);

        // Both offers are ingested; has_voice routes them (voice → Naara Connect,
        // data → Naara Data). Zendit serves both lines.
        $count = app(CatalogueSyncService::class)->sync('zendit');
        $this->assertSame(2, $count);

        $voice = EsimPlan::where('provider_plan_id', 'ng-voice-5gb')->firstOrFail();
        $this->assertTrue($voice->has_voice);
        $this->assertSame(5120, $voice->data_mb);
        $this->assertSame(['NG'], $voice->countries);
        $this->assertSame('4.0000', (string) $voice->cost_price_usd);
        // Retail is OURS (cost * markup), never Zendit's suggested 9.99.
        $this->assertNotSame(9.99, (float) $voice->fresh()->final_retail_usd);

        // The data-only Zendit offer is stored on the data line.
        $data = EsimPlan::where('provider_plan_id', 'ng-data-3gb')->firstOrFail();
        $this->assertFalse($data->has_voice);

        // Cost never leaks in a serialized plan.
        $this->assertArrayNotHasKey('cost_price_usd', $voice->toArray());
    }

    public function test_a_supplier_brand_in_a_catalogue_name_is_scrubbed_on_sync(): void
    {
        // A provider whose catalogue title embeds its own brand.
        $this->bindProvider('esimgo', [
            'bundles' => [[
                'name' => 'esim_1GB_US', 'description' => 'eSIM Go USA 1GB', 'dataAmount' => 1000,
                'duration' => 30, 'countries' => [['iso' => 'US']], 'price' => 2.00,
            ]],
        ]);

        app(CatalogueSyncService::class)->sync('esimgo');

        $plan = EsimPlan::where('provider', 'esimgo')->firstOrFail();
        $this->assertSame('USA 1GB', $plan->name);           // brand removed
        $this->assertStringNotContainsStringIgnoringCase('esim go', $plan->name);
    }

    public function test_full_esim_providers_ingest_voice_plans_with_private_cost(): void
    {
        // 1GLOBAL / Monty / Gigs are voice+data MVNO providers — every plan is a
        // Full eSIM (has_voice = true). The defensive mapper reads common field
        // names; the wholesale cost stays private.
        $this->bindProvider('oneglobal', ['data' => [
            ['id' => 'og-eu-10gb', 'name' => 'Europe 10GB + calls', 'dataGB' => 10,
                'durationDays' => 30, 'countries' => ['FR', 'DE'], 'wholesale' => 6.0],
        ]]);

        $count = app(CatalogueSyncService::class)->sync('oneglobal');
        $this->assertSame(1, $count);

        $plan = EsimPlan::where('provider', 'oneglobal')->firstOrFail();
        $this->assertTrue($plan->has_voice);
        $this->assertSame('Voice + Data', $plan->type);
        $this->assertSame(10240, $plan->data_mb);
        $this->assertSame(30, $plan->validity_days);
        $this->assertSame(['FR', 'DE'], $plan->countries);
        $this->assertSame('6.0000', (string) $plan->cost_price_usd);
        $this->assertArrayNotHasKey('cost_price_usd', $plan->toArray());
    }
}
