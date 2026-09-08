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
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: []));
        // FakeEsimProvider::getCatalogue() returns [] → zero plans, but a SUCCESS.
        app(CatalogueSyncService::class)->sync('esimgo');

        $status = SyncStatus::for('esimgo');
        $this->assertNotNull($status);
        $this->assertTrue($status['ok']);
        $this->assertSame(0, $status['count']);
    }

    public function test_a_failing_sync_is_recorded_not_silent(): void
    {
        SyncStatus::flush();
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
}
