<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;
use App\Models\Setting;
use App\Services\NCI\NciScorer;
use App\Services\Routing\CandidateOrdering;
use App\Services\Routing\CircuitBreaker;
use App\Support\PiiRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * NAARA-BUILD-19 — NCI safety & hygiene. Covers the kill switch, the
 * confidence-weighted ordering, the circuit-transition alerts, the total-outage
 * last resort, the margin tie-break, and PII redaction.
 */
class NciSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $key, array $attrs = []): ProviderRegistry
    {
        return ProviderRegistry::create(array_merge([
            'provider_key' => $key,
            'stack' => 'esim',
            'circuit_breaker_state' => 'closed',
        ], $attrs));
    }

    // §1 — the kill switch: when nci.enabled is false, ordering ignores nci_score.
    public function test_kill_switch_makes_ordering_ignore_nci_score(): void
    {
        // A weaker provider that NCI rates highly vs a stronger one NCI dislikes.
        $this->provider('smart', ['nci_score' => 0.99, 'nci_confidence' => 1.0, 'success_rate_24h' => 0.50, 'latency_ms' => 100]);
        $this->provider('plain', ['nci_score' => 0.10, 'nci_confidence' => 1.0, 'success_rate_24h' => 0.99, 'latency_ms' => 100]);
        ProviderRegistry::flushSnapshot();

        $ordering = app(CandidateOrdering::class);

        Setting::setValue('nci.enabled', true);
        ProviderRegistry::flushSnapshot();
        $this->assertSame('smart', $ordering->order(['plain', 'smart'], 'esim')[0], 'NCI on → its favourite leads.');

        Setting::setValue('nci.enabled', false);
        ProviderRegistry::flushSnapshot();
        // NCI off → fall back to success-rate; the reliable one leads instead.
        $this->assertSame('plain', $ordering->order(['plain', 'smart'], 'esim')[0], 'NCI off → success-rate decides.');
    }

    // §3 — a score from few outcomes is weighted down by low confidence.
    public function test_low_confidence_score_does_not_front_run(): void
    {
        Setting::setValue('nci.enabled', true);
        // 'thin' has a perfect score but almost no confidence; 'solid' a good
        // score with full confidence → confidence-weighting must prefer 'solid'.
        $this->provider('thin', ['nci_score' => 1.0, 'nci_confidence' => 0.02, 'success_rate_24h' => 0.90, 'latency_ms' => 100]);
        $this->provider('solid', ['nci_score' => 0.90, 'nci_confidence' => 1.0, 'success_rate_24h' => 0.90, 'latency_ms' => 100]);
        ProviderRegistry::flushSnapshot();

        $order = app(CandidateOrdering::class)->order(['thin', 'solid'], 'esim');
        $this->assertSame('solid', $order[0], 'Confidence-weighted: 0.90×1.0 beats 1.0×0.02.');
    }

    // §2 — opening a circuit fires an admin alert naming the provider.
    public function test_circuit_open_dispatches_admin_alert(): void
    {
        Queue::fake();
        $this->provider('airalo');

        $cb = app(CircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $cb->record('airalo', 'esim', 'failure', 'timeout');
        }

        Queue::assertPushed(AlertAdminJob::class, fn ($job) => str_contains($job->code, 'circuit-open-airalo'));
    }

    // §5 — when every candidate is open, lastResortAmong picks one; if any is
    // attemptable it is NOT a total outage and returns null.
    public function test_last_resort_only_fires_on_total_outage(): void
    {
        $this->provider('a', ['circuit_breaker_state' => 'open']);
        $this->provider('b', ['circuit_breaker_state' => 'open']);
        // 'a' failed longer ago → it is the least-recently-failed → chosen.
        ProviderOutcome::create(['provider_key' => 'a', 'stack' => 'esim', 'outcome' => 'failure', 'occurred_at' => now()->subHours(3)]);
        ProviderOutcome::create(['provider_key' => 'b', 'stack' => 'esim', 'outcome' => 'failure', 'occurred_at' => now()->subMinutes(1)]);

        $cb = app(CircuitBreaker::class);
        $this->assertSame('a', $cb->lastResortAmong(['a', 'b']));

        // One closed → not a total outage → no last resort.
        $this->provider('c', ['circuit_breaker_state' => 'closed']);
        $this->assertNull($cb->lastResortAmong(['a', 'b', 'c']));
    }

    // §6 — margin lifts a tie but never overturns reliability.
    public function test_margin_is_only_a_tie_break(): void
    {
        // Two providers, IDENTICAL reliability (18/20 = 0.90, below 1.0 so the
        // sub-resolution margin bonus isn't clamped); only 'rich' has margin.
        $this->provider('rich');
        $this->provider('poor');
        foreach (['rich', 'poor'] as $key) {
            foreach (range(1, 18) as $i) {
                ProviderOutcome::create(['provider_key' => $key, 'stack' => 'esim', 'outcome' => 'success', 'occurred_at' => now()]);
            }
            foreach (range(1, 2) as $i) {
                ProviderOutcome::create(['provider_key' => $key, 'stack' => 'esim', 'outcome' => 'failure', 'error_code' => 'out_of_stock', 'occurred_at' => now()]);
            }
        }
        // Give 'rich' a healthy realised margin via the eSIM ledger.
        \App\Models\OrderLog::create([
            'user_id' => null, 'naarasim_plan_id' => 'P1', 'provider' => 'rich',
            'provider_cost' => 1.0, 'charged_to_user' => 2.0, 'profit' => 1.0, 'profit_pct' => 100, 'result' => 'success',
        ]);

        $scorer = app(NciScorer::class);
        $scorer->recompute('rich');
        $scorer->recompute('poor');

        $rich = ProviderRegistry::where('provider_key', 'rich')->value('nci_score');
        $poor = ProviderRegistry::where('provider_key', 'poor')->value('nci_score');
        $this->assertGreaterThan($poor, $rich, 'Margin breaks the reliability tie.');
        // …but the lift is sub-resolution, never a reliability-sized swing.
        $this->assertLessThanOrEqual(0.003 + 1e-9, $rich - $poor);
    }

    // §9 — PII in an error string is redacted before it is persisted.
    public function test_pii_is_redacted_from_error_codes(): void
    {
        $this->assertSame('failed for [email]', PiiRedactor::redact('failed for user@example.com'));
        $this->assertStringContainsString('[redacted]', PiiRedactor::redact('no stock for +2348012345678'));

        $this->provider('esimgo');
        app(CircuitBreaker::class)->record('esimgo', 'esim', 'failure', 'blocked +2348012345678');
        $stored = ProviderOutcome::where('provider_key', 'esimgo')->latest('id')->value('error_code');
        $this->assertStringNotContainsString('2348012345678', (string) $stored);
    }

    // §8 — a genuinely fresh install (empty registry + outcomes) must route, not
    // crash: ordering returns the static list, circuits allow, no last resort, and
    // the prune command runs clean against empty tables.
    public function test_fresh_install_empty_tables_behave_safely(): void
    {
        $this->assertSame(0, ProviderRegistry::count());
        $this->assertSame(0, ProviderOutcome::count());

        // Ordering with an empty snapshot returns the caller's static order verbatim.
        $order = app(CandidateOrdering::class)->order(['esimgo', 'airalo'], 'esim');
        $this->assertSame(['esimgo', 'airalo'], $order);

        // With no registry row a provider defaults to CLOSED → attemptable.
        $cb = app(CircuitBreaker::class);
        $this->assertTrue($cb->allows('esimgo'));
        // Nothing is open, so there is no total-outage last resort to pick.
        $this->assertNull($cb->lastResortAmong(['esimgo', 'airalo']));

        // Recompute + prune against empty tables must not throw.
        app(NciScorer::class)->recomputeAll();
        $this->artisan('nci:prune-outcomes')->assertSuccessful();
        $this->artisan('nci:recompute')->assertSuccessful();
    }

    // §4 — the prune command removes only rows past the retention horizon.
    public function test_prune_deletes_only_old_outcomes(): void
    {
        ProviderOutcome::create(['provider_key' => 'x', 'stack' => 'esim', 'outcome' => 'success', 'occurred_at' => now()->subDays(120)]);
        ProviderOutcome::create(['provider_key' => 'x', 'stack' => 'esim', 'outcome' => 'success', 'occurred_at' => now()->subDays(10)]);

        $this->artisan('nci:prune-outcomes')->assertSuccessful();

        $this->assertSame(1, ProviderOutcome::count(), 'Only the 120-day-old row is pruned.');
    }
}
