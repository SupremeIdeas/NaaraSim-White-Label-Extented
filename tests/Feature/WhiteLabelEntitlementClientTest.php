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
 * Batch 8B (fork side) — WhiteLabelUpdateClient::refreshEntitlement(). Proves
 * this fork caches the lock list the master resolves for it, that the cache is
 * written ONLY on a clean, well-formed response, and that any transient failure
 * leaves the last-known list untouched — never wiped (no accidental unlock) and
 * never invented (no surprise hard-lock), matching the Batch 5/8 resilience
 * posture. Also proves a code check-in opportunistically refreshes entitlement
 * while a theme check does not.
 */
class WhiteLabelEntitlementClientTest extends TestCase
{
    use RefreshDatabase;

    private const CHECK_URL = 'master.example.test/api/v1/white-label/updates/check*';

    private const ENTITLEMENT_URL = 'master.example.test/api/v1/white-label/entitlement*';

    protected function setUp(): void
    {
        parent::setUp();

        // Behave as a fork (any non-core product identity), so the universal
        // resolver actually caches locks rather than no-opping as it does on
        // the master.
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
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
        // The gates read the cache, not the fetch result — prove the cache took.
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], FeatureEntitlements::all());
        $this->assertTrue(FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS));
    }

    public function test_refresh_entitlement_caches_an_empty_lock_list_for_a_full_license(): void
    {
        // Extended forks resolve to 'full' — nothing locked. An explicit empty
        // list must overwrite any stale locks, so a real upgrade actually frees
        // the features.
        FeatureEntitlements::store([FeatureLocks::F_GIFT_CARDS]);

        Http::fake([self::ENTITLEMENT_URL => Http::response([
            'level' => 'full',
            'locks' => [],
        ])]);

        $result = $this->client()->refreshEntitlement();

        $this->assertTrue($result['ok']);
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
        $this->assertSame([FeatureLocks::F_BRAND_HUNT], FeatureEntitlements::all());
    }

    public function test_refresh_entitlement_keeps_last_known_locks_on_a_non_2xx(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_ESIM_VOICE]);
        Http::fake([self::ENTITLEMENT_URL => Http::response(['message' => 'nope'], 500)]);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertSame([FeatureLocks::F_ESIM_VOICE], FeatureEntitlements::all());
    }

    public function test_refresh_entitlement_keeps_last_known_locks_on_a_connection_error(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_PRELOADER]);
        Http::fake([self::ENTITLEMENT_URL => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Could not reach', $result['error']);
        $this->assertSame([FeatureLocks::F_PRELOADER], FeatureEntitlements::all());
    }

    public function test_refresh_entitlement_keeps_last_known_locks_on_a_malformed_payload(): void
    {
        FeatureEntitlements::store([FeatureLocks::F_GIFT_CARDS]);
        // 200 but no usable 'locks' array — treated as a failure, cache untouched.
        Http::fake([self::ENTITLEMENT_URL => Http::response(['level' => 'standard'])]);

        $result = $this->client()->refreshEntitlement();

        $this->assertFalse($result['ok']);
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], FeatureEntitlements::all());
    }

    public function test_refresh_entitlement_drops_non_string_lock_entries(): void
    {
        Http::fake([self::ENTITLEMENT_URL => Http::response([
            'level' => 'basic',
            'locks' => [FeatureLocks::F_GIFT_CARDS, 123, null, FeatureLocks::F_BRAND_HUNT],
        ])]);

        $this->client()->refreshEntitlement();

        $this->assertSame([FeatureLocks::F_GIFT_CARDS, FeatureLocks::F_BRAND_HUNT], FeatureEntitlements::all());
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
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], FeatureEntitlements::all());
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
        // Theme check left the cached lock list exactly as it was.
        $this->assertSame([FeatureLocks::F_BRAND_HUNT], FeatureEntitlements::all());
    }
}
