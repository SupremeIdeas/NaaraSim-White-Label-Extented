<?php

namespace Tests\Feature;

use App\Events\CircuitOpened;
use App\Events\ProviderOutcomeRecorded;
use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;
use App\Services\NCI\Listeners\IncrementNciScore;
use App\Services\NCI\Listeners\RecomputeProviderScore;
use App\Services\NCI\Listeners\RefreshNciOnHealthCheck;
use App\Services\NCI\NciScorer;
use App\Services\Routing\CircuitBreaker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * NAARA-BUILD-16 — NCI Layer 3 (learning). Verifies the async boundary (every
 * listener is queued; no router calls NCI), the gentle EMA, and the periodic
 * risk/confidence recompute.
 */
class NciLearningTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $key = 'esimgo'): ProviderRegistry
    {
        return ProviderRegistry::create(['provider_key' => $key, 'stack' => 'esim']);
    }

    private function outcomes(string $key, int $success, int $failure, string $errorCode = 'timeout'): void
    {
        foreach (range(1, $success) as $i) {
            ProviderOutcome::create(['provider_key' => $key, 'stack' => 'esim', 'outcome' => 'success', 'occurred_at' => now()]);
        }
        foreach (range(1, $failure) as $i) {
            ProviderOutcome::create(['provider_key' => $key, 'stack' => 'esim', 'outcome' => 'failure', 'error_code' => $errorCode, 'occurred_at' => now()]);
        }
    }

    public function test_every_nci_listener_is_queued(): void
    {
        foreach ([IncrementNciScore::class, RecomputeProviderScore::class, RefreshNciOnHealthCheck::class] as $listener) {
            $this->assertTrue(
                (new \ReflectionClass($listener))->implementsInterface(ShouldQueue::class),
                "$listener must be queued — NCI never runs on the request path.",
            );
        }
    }

    public function test_no_router_imports_the_nci_namespace(): void
    {
        foreach ([
            app_path('Services/eSIM/ProviderRouter.php'),
            app_path('Services/SMS/SmsNumberRouter.php'),
            app_path('Services/SMS/PermanentNumberRouter.php'),
        ] as $file) {
            $this->assertStringNotContainsString('App\\Services\\NCI', file_get_contents($file),
                "$file must not depend on NCI — the boundary is one-way.");
        }
    }

    public function test_the_ema_nudges_gently_and_counts_the_sample(): void
    {
        $this->row();
        $scorer = app(NciScorer::class);

        $scorer->applyOutcome('esimgo', true);   // null → 1.0
        $this->assertSame(1.0, ProviderRegistry::where('provider_key', 'esimgo')->value('nci_score'));

        $scorer->applyOutcome('esimgo', false);  // 1.0 + 0.05*(0 - 1.0) = 0.95
        $row = ProviderRegistry::where('provider_key', 'esimgo')->first();
        $this->assertSame(0.95, $row->nci_score);
        $this->assertSame(2, $row->nci_sample_size);
    }

    public function test_recompute_sets_confidence_and_a_high_risk_for_concentrated_timeouts(): void
    {
        $this->row();
        $this->outcomes('esimgo', success: 60, failure: 40, errorCode: 'timeout'); // 40% fail, all timeouts

        app(NciScorer::class)->recompute('esimgo');

        $row = ProviderRegistry::where('provider_key', 'esimgo')->first();
        $this->assertSame(100, $row->nci_sample_size);
        $this->assertSame(0.6, round((float) $row->nci_score, 4));
        $this->assertSame(0.5, round((float) $row->nci_confidence, 4)); // 100 / 200
        $this->assertSame('high', $row->nci_risk_rating);               // concentrated timeouts
    }

    public function test_recompute_is_gentle_on_expected_inventory_failures(): void
    {
        $this->row('fivesim');
        // 20% failures, but all out-of-stock (expected inventory, not a fault).
        $this->outcomes('fivesim', success: 80, failure: 20, errorCode: 'out_of_stock');

        app(NciScorer::class)->recompute('fivesim');

        $row = ProviderRegistry::where('provider_key', 'fivesim')->first();
        $this->assertSame('medium', $row->nci_risk_rating); // 0.20 rate, not hard-concentrated
    }

    public function test_recording_an_outcome_dispatches_the_queued_event(): void
    {
        Event::fake([ProviderOutcomeRecorded::class]);

        app(CircuitBreaker::class)->record('esimgo', 'esim', 'success');

        Event::assertDispatched(ProviderOutcomeRecorded::class,
            fn ($e) => $e->providerKey === 'esimgo' && $e->outcome === 'success');
    }

    public function test_tripping_the_circuit_dispatches_circuit_opened(): void
    {
        Event::fake([CircuitOpened::class]);
        $cb = app(CircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $cb->record('airalo', 'esim', 'failure', 'timeout');
        }

        Event::assertDispatched(CircuitOpened::class, fn ($e) => $e->providerKey === 'airalo');
    }

    public function test_the_recompute_command_runs(): void
    {
        $this->row();
        $this->outcomes('esimgo', success: 30, failure: 5);

        $this->artisan('nci:recompute')->assertSuccessful();

        $this->assertNotNull(ProviderRegistry::where('provider_key', 'esimgo')->value('nci_computed_at'));
    }
}
