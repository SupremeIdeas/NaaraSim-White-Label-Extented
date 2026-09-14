<?php

namespace Tests\Feature;

use App\Models\ProviderRegistry;
use App\Support\ProviderHealth;
use Database\Seeders\ProviderRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * NAARA-BUILD-14 — the shared Provider Registry (NCI Layer 1): one row per
 * provider across all three stacks, live-truth written by ProviderHealth, read
 * only through the short-TTL cached snapshot.
 */
class ProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEsim(float $balance): object
    {
        return new class($balance)
        {
            public function __construct(private float $balance) {}

            public function getBalance(): float
            {
                return $this->balance;
            }
        };
    }

    public function test_meta_is_derived_from_the_existing_lanes(): void
    {
        $this->assertSame('esim', ProviderRegistry::deriveMeta('esimgo')['stack']);
        $this->assertContains('naara_data', ProviderRegistry::deriveMeta('esimgo')['product_families']);

        $this->assertSame('permanent', ProviderRegistry::deriveMeta('twilio')['stack']);
        $this->assertContains('naara_line', ProviderRegistry::deriveMeta('twilio')['product_families']);

        // A shared SMS/OTP provider serves both Verify and Rent.
        $fivesim = ProviderRegistry::deriveMeta('fivesim');
        $this->assertSame('sms', $fivesim['stack']);
        $this->assertEqualsCanonicalizing(['naara_verify', 'naara_rent'], $fivesim['product_families']);
    }

    public function test_the_seeder_populates_metadata_and_leaves_unconfirmed_urls_null(): void
    {
        $this->seed(ProviderRegistrySeeder::class);

        $twilio = ProviderRegistry::where('provider_key', 'twilio')->firstOrFail();
        $this->assertSame('permanent', $twilio->stack);
        $this->assertSame('individual_kyc', $twilio->onboarding_tier);
        $this->assertSame('https://console.twilio.com/', $twilio->dashboard_login_url);

        // An unconfirmed provider is seeded with metadata but a null login URL.
        $herosms = ProviderRegistry::where('provider_key', 'herosms')->firstOrFail();
        $this->assertSame('sms', $herosms->stack);
        $this->assertNull($herosms->dashboard_login_url);
    }

    public function test_health_check_upserts_live_truth_and_busts_the_snapshot(): void
    {
        config(['services.esimgo.api_key' => 'k']);
        $this->app->instance('esim.esimgo', $this->fakeEsim(200.0));

        app(ProviderHealth::class)->checkAll();

        $row = ProviderRegistry::where('provider_key', 'esimgo')->firstOrFail();
        $this->assertSame('ok', $row->status);
        $this->assertSame('200.0000', (string) $row->balance);
        $this->assertNotNull($row->latency_ms);          // latency is genuinely captured
        $this->assertNotNull($row->last_checked_at);
        $this->assertNotNull($row->last_success_at);
        $this->assertTrue($row->enabled);

        // Every provider across the three stacks got a row (shared registry).
        // Derived, not hardcoded — ProviderHealth::PROVIDERS legitimately grows
        // as new providers are wired in (e.g. Prompt 12's Plivo/Vonage/Sinch).
        $providersCount = (new \ReflectionClass(ProviderHealth::class))->getConstant('PROVIDERS');
        $this->assertSame(count($providersCount), ProviderRegistry::count());
    }

    public function test_snapshot_is_cached_and_flushable(): void
    {
        $this->seed(ProviderRegistrySeeder::class);
        Cache::forget(ProviderRegistry::SNAPSHOT_KEY);

        $snap = ProviderRegistry::snapshot();
        $this->assertArrayHasKey('twilio', $snap);
        $this->assertTrue(Cache::has(ProviderRegistry::SNAPSHOT_KEY));

        ProviderRegistry::flushSnapshot();
        $this->assertFalse(Cache::has(ProviderRegistry::SNAPSHOT_KEY));
    }
}
