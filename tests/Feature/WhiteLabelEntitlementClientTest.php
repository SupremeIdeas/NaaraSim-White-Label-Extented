<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\WhiteLabelUpdateClient;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 8B (fork side), White-Label EXTENDED variant — WhiteLabelUpdateClient::
 * refreshEntitlement(). Proves the client still caches the raw lock list the
 * master resolves, writing ONLY on a clean well-formed response and keeping the
 * last-known list on any transient failure (never wiped, never invented) — the
 * Batch 5/8 resilience posture — AND proves the EXTENDED guarantee: no matter
 * what the master sends, this build applies zero per-feature locks
 * (`FeatureEntitlements::all()` is hard-empty here — see that class + blueprint
 * §0.4: "Extended ships with zero feature locks, every feature live from
 * install"). So the assertions read the RAW stored list (what the client wrote)
 * to check caching, and separately assert the gate stays fully unlocked.
 */
class WhiteLabelEntitlementClientTest extends TestCase
{
    use RefreshDatabase;

    private const CHECK_URL = 'master.example.test/api/v1/white-label/updates/check*';

    private const ENTITLEMENT_URL = 'master.example.test/api/v1/white-label/entitlement*';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('updater.product_identifier', 'naarasim-whitelabel-extended');
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'test-token');

        Setting::setValue(UpdateApplier::VERSION_SETTING, '2026.09.05-1');
        FeatureEntitlements::bust();
    }

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    private function client(): WhiteLabelUpdateClient
    {
        return app(WhiteLabelUpdateClient::class);
    }

    /** The raw lock list the client persisted (what refreshEntitlement stores). */
    private function storedLocks(): array
    {
        return Setting::getValue(FeatureEntitlements::STORE_KEY, []);
    }

    // --- refreshEntitlement: the happy path caches the resolved lock list ---

    public function test_refresh_entitlement_caches_the_resolved_lock_list(): void
    {
        Http::fake([self::ENTITLEMENT_URL => Http::response([
            'level' => 'standard',
            'locks' => [FeatureLocks::F_GIFT_CARDS],
        ])]);

        $result = $this->client()->refreshEntitlement();

        $this->assertTrue($result['ok']);
        $this->assertSame('standard', $result['level']);
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], $result['locks']);
        // The client persisted the raw list it received.
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], $this->storedLocks());
        // EXTENDED guarantee: the gate applies zero per-feature locks regardless
        // of what the master sent — nothing is ever locked on this build.
        $this->assertSame([], FeatureEntitlements::all());
        $this->assertFalse(FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS));
    }

    public function test_refresh_entitlement_caches_an_empty_lock_list_for_a_full_license(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_GIFT_CARDS]);

        Http::fake([self::ENTITLEMENT_URL => Http::response([
            'level' => 'full',
            'locks' => [],
        ])]);

        $result = $this->client()->refreshEntitlement();

        $this->assertTrue($result['ok']);
        $this->assertSame([], $this->storedLocks());
        $this->assertSame([], FeatureEntitlements::all());
    }

    // --- refreshEntitlement: every failure keeps the last-known list ---

    public function test_refresh_entitlement_keeps_last_known_locks_when_not_configured(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_BRAND_HUNT]);
        config()->set('updater.api_token', null);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not configured', $result['error']);
        $this->assertSame([FeatureLocks::F_BRAND_HUNT], $this->storedLocks());
    }

    public function test_refresh_entitlement_keeps_last_known_locks_on_a_non_2xx(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_ESIM_VOICE]);
        Http::fake([self::ENTITLEMENT_URL => Http::response(['message' => 'nope'], 500)]);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertSame([FeatureLocks::F_ESIM_VOICE], $this->storedLocks());
    }

    public function test_refresh_entitlement_keeps_last_known_locks_on_a_connection_error(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_PRELOADER]);
        Http::fake([self::ENTITLEMENT_URL => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Could not reach', $result['error']);
        $this->assertSame([FeatureLocks::F_PRELOADER], $this->storedLocks());
    }

    public function test_refresh_entitlement_keeps_last_known_locks_on_a_malformed_payload(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_GIFT_CARDS]);
        Http::fake([self::ENTITLEMENT_URL => Http::response(['level' => 'standard'])]);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], $this->storedLocks());
    }

    public function test_refresh_entitlement_drops_non_string_lock_entries(): void
    {
        Http::fake([self::ENTITLEMENT_URL => Http::response([
            'level' => 'basic',
            'locks' => [FeatureLocks::F_GIFT_CARDS, 123, null, FeatureLocks::F_BRAND_HUNT],
        ])]);

        $this->client()->refreshEntitlement();

        $this->assertSame([FeatureLocks::F_GIFT_CARDS, FeatureLocks::F_BRAND_HUNT], $this->storedLocks());
        // Still fully unlocked here regardless of what was stored.
        $this->assertSame([], FeatureEntitlements::all());
    }

    // --- opportunistic refresh on check-in ---

    public function test_a_code_check_in_also_refreshes_entitlement(): void
    {
        Http::fake([
            self::CHECK_URL => Http::response(['packages' => []]),
            self::ENTITLEMENT_URL => Http::response([
                'level' => 'standard',
                'locks' => [FeatureLocks::F_GIFT_CARDS],
            ]),
        ]);

        $result = $this->client()->checkForUpdates('code');

        $this->assertTrue($result['ok']);
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], $this->storedLocks());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/white-label/entitlement'));
    }

    public function test_a_theme_check_does_not_refresh_entitlement(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_BRAND_HUNT]);

        Http::fake([
            'master.example.test/api/v1/white-label/themes/check*' => Http::response(['packages' => []]),
            self::ENTITLEMENT_URL => Http::response(['level' => 'full', 'locks' => []]),
        ]);

        $this->client()->checkForUpdates('theme');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/white-label/entitlement'));
        $this->assertSame([FeatureLocks::F_BRAND_HUNT], $this->storedLocks());
    }

    // --- the Extended always-unlocked guarantee, stated directly ---

    public function test_extended_never_locks_a_feature_even_if_a_list_is_stored(): void
    {
        FeatureEntitlements::store([
            FeatureLocks::F_GIFT_CARDS,
            FeatureLocks::F_ESIM_VOICE,
            FeatureLocks::F_PRELOADER,
            FeatureLocks::F_BRAND_HUNT,
        ]);

        $this->assertSame([], FeatureEntitlements::all());
        foreach (array_keys(FeatureLocks::catalog()) as $key) {
            $this->assertFalse(FeatureEntitlements::locked($key), "$key must never be locked on Extended");
            $this->assertTrue(FeatureEntitlements::allowed($key));
        }
    }
}
