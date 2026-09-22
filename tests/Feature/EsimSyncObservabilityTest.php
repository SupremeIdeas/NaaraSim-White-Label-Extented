<?php

namespace Tests\Feature;

use App\Services\eSIM\CatalogueSyncService;
use App\Support\ProviderKeys;
use App\Support\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * eSIM catalogue live-data correctness (esim_upgrade Part 1). Saving a provider
 * key signals long-running workers to restart (so they stop using stale config),
 * and every sync records its outcome so a broken provider is visible, not silent.
 */
class EsimSyncObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_provider_key_signals_a_queue_restart(): void
    {
        // Laravel's queue:restart sets this cache key to a timestamp. A worker
        // checks it after each job and gracefully exits, re-booting with fresh
        // config — the fix for admin-pasted keys not reaching queued syncs.
        Cache::forget('illuminate:queue:restart');
        $this->assertNull(Cache::get('illuminate:queue:restart'));

        ProviderKeys::save(['esimgo_api_key' => 'new-key-value']);

        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
    }

    public function test_a_successful_sync_records_its_outcome(): void
    {
        SyncStatus::flush();
        config(['services.esimgo.api_key' => 'test-key']);
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: []));
        // FakeEsimProvider::getCatalogue() returns [] → zero plans, but a SUCCESS.
        app(CatalogueSyncService::class)->sync('esimgo');

        $status = SyncStatus::for('esimgo');
        $this->assertNotNull($status);
        $this->assertTrue($status['ok']);
        $this->assertSame(0, $status['count']);
        $this->assertFalse($status['skipped']);
    }

    public function test_a_failing_sync_is_recorded_not_silent(): void
    {
        SyncStatus::flush();
        config(['services.esimgo.api_key' => 'test-key']);
        // A provider whose catalogue call throws (e.g. a 404/auth failure) → the
        // sync records a failure and rethrows so the job/command still knows.
        app()->instance('esim.esimgo', new class extends FakeEsimProvider {
            public function getCatalogue(): array
            {
                throw new \RuntimeException('HTTP 404 from provider');
            }
        });

        try {
            app(CatalogueSyncService::class)->sync('esimgo');
            $this->fail('sync should have thrown');
        } catch (\Throwable) {
            // expected
        }

        $status = SyncStatus::for('esimgo');
        $this->assertNotNull($status);
        $this->assertFalse($status['ok']);
        $this->assertNotEmpty($status['error']);
    }

    /**
     * Tier 4 #10 Phase A1 — the confirmed root cause: an unconfigured
     * provider (no API key entered — not onboarded yet, not a failure) was
     * attempted every single sync cycle, guaranteeing a permanent, zero-value
     * failure. It must now be skipped BEFORE any HTTP call is attempted (no
     * provider bound in the container here — a real call would throw a
     * binding-resolution error, proving doSync() was never reached), and
     * recorded as a distinct, neutral "skipped" state — never as a failure.
     */
    public function test_an_unconfigured_provider_is_skipped_not_attempted(): void
    {
        SyncStatus::flush();
        config(['services.zendit.api_key' => null]);

        $count = app(CatalogueSyncService::class)->sync('zendit');

        $this->assertSame(0, $count);
        $status = SyncStatus::for('zendit');
        $this->assertNotNull($status);
        $this->assertTrue($status['ok']);
        $this->assertTrue($status['skipped']);
    }
}
