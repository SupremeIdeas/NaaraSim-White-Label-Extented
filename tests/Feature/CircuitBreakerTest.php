<?php

namespace Tests\Feature;

use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;
use App\Models\Setting;
use App\Services\Routing\CandidateOrdering;
use App\Services\Routing\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * NAARA-BUILD-15 — the shared circuit breaker + registry-informed ordering.
 * Transitions are driven synchronously by real outcomes; ordering only reorders
 * (never replaces the live call) and falls back to the static order.
 */
class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    private function breaker(): CircuitBreaker
    {
        return app(CircuitBreaker::class);
    }

    private function induceFail(CircuitBreaker $cb, string $p, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $cb->record($p, 'esim', 'failure', 'timeout');
        }
    }

    public function test_five_consecutive_failures_open_the_circuit_and_skip_it(): void
    {
        $cb = $this->breaker();
        $this->assertTrue($cb->allows('esimgo'));       // closed by default

        $this->induceFail($cb, 'esimgo', 5);

        $this->assertSame(CircuitBreaker::OPEN, ProviderRegistry::where('provider_key', 'esimgo')->value('circuit_breaker_state'));
        $this->assertFalse($cb->allows('esimgo'));      // open → skipped, no live call
        $this->assertSame(5, ProviderOutcome::where('provider_key', 'esimgo')->count());
    }

    public function test_open_moves_to_half_open_after_cooldown_and_closes_on_success(): void
    {
        Setting::setValue('routing.cb.cooldown_minutes', 0, 'routing'); // elapse instantly
        $cb = $this->breaker();
        $this->induceFail($cb, 'airalo', 5);
        $this->assertSame(CircuitBreaker::OPEN, ProviderRegistry::where('provider_key', 'airalo')->value('circuit_breaker_state'));

        // Cooldown elapsed → exactly one HALF_OPEN probe is allowed.
        $this->assertTrue($cb->allows('airalo'));
        $this->assertSame(CircuitBreaker::HALF_OPEN, ProviderRegistry::where('provider_key', 'airalo')->value('circuit_breaker_state'));

        // A successful probe closes the circuit.
        $cb->record('airalo', 'esim', 'success');
        $this->assertSame(CircuitBreaker::CLOSED, ProviderRegistry::where('provider_key', 'airalo')->value('circuit_breaker_state'));
    }

    public function test_a_failed_half_open_probe_reopens_the_circuit(): void
    {
        Setting::setValue('routing.cb.cooldown_minutes', 0, 'routing');
        $cb = $this->breaker();
        $this->induceFail($cb, 'quibity', 5);
        $cb->allows('quibity'); // → half_open

        $cb->record('quibity', 'esim', 'failure', 'timeout');
        $this->assertSame(CircuitBreaker::OPEN, ProviderRegistry::where('provider_key', 'quibity')->value('circuit_breaker_state'));
    }

    public function test_open_circuit_is_denied_during_its_cooldown(): void
    {
        Setting::setValue('routing.cb.cooldown_minutes', 5, 'routing');
        $cb = $this->breaker();
        $this->induceFail($cb, 'zendit', 5);

        $this->assertFalse($cb->allows('zendit')); // just opened → still cooling down
    }

    public function test_record_refreshes_the_registry_rolling_reliability(): void
    {
        $cb = $this->breaker();
        $cb->record('fivesim', 'sms', 'success');
        $cb->record('fivesim', 'sms', 'success');
        $cb->record('fivesim', 'sms', 'failure', 'out_of_stock');

        $row = ProviderRegistry::where('provider_key', 'fivesim')->firstOrFail();
        $this->assertSame(2, $row->success_count_24h);
        $this->assertSame(1, $row->failure_count_24h);
        $this->assertSame('0.6667', (string) $row->success_rate_24h);
    }

    public function test_ordering_excludes_open_and_prefers_faster_more_reliable(): void
    {
        // esimgo: open → excluded. airalo: slow. quibity: fast + reliable → first.
        ProviderRegistry::create(['provider_key' => 'esimgo', 'stack' => 'esim', 'circuit_breaker_state' => 'open', 'latency_ms' => 10]);
        ProviderRegistry::create(['provider_key' => 'airalo', 'stack' => 'esim', 'circuit_breaker_state' => 'closed', 'latency_ms' => 900, 'success_rate_24h' => 0.80]);
        ProviderRegistry::create(['provider_key' => 'quibity', 'stack' => 'esim', 'circuit_breaker_state' => 'closed', 'latency_ms' => 120, 'success_rate_24h' => 0.99]);
        ProviderRegistry::flushSnapshot();

        $ordered = app(CandidateOrdering::class)->order(['esimgo', 'airalo', 'quibity'], 'esim');

        $this->assertSame(['quibity', 'airalo'], $ordered); // esimgo dropped, fastest first
    }

    public function test_ordering_falls_back_to_static_order_when_snapshot_empty(): void
    {
        Cache::forget(ProviderRegistry::SNAPSHOT_KEY);
        $ordered = app(CandidateOrdering::class)->order(['esimgo', 'airalo', 'quibity'], 'esim');

        $this->assertSame(['esimgo', 'airalo', 'quibity'], $ordered); // unchanged
    }

    // --- Owner request (2026-09-15): durable provider pause/sleep ---

    public function test_a_paused_provider_is_refused_by_allows_even_with_a_closed_circuit(): void
    {
        ProviderRegistry::create(['provider_key' => 'getatext', 'stack' => 'number', 'circuit_breaker_state' => 'closed', 'paused_at' => now()]);

        $this->assertFalse($this->breaker()->allows('getatext'));
    }

    public function test_a_resumed_provider_is_allowed_again(): void
    {
        $row = ProviderRegistry::create(['provider_key' => 'getatext', 'stack' => 'number', 'circuit_breaker_state' => 'closed', 'paused_at' => now()]);
        $this->assertFalse($this->breaker()->allows('getatext'));

        $row->forceFill(['paused_at' => null])->save();
        $this->assertTrue($this->breaker()->allows('getatext'));
    }

    public function test_ordering_excludes_a_paused_provider_the_same_way_as_an_open_circuit(): void
    {
        ProviderRegistry::create(['provider_key' => 'fivesim', 'stack' => 'number', 'circuit_breaker_state' => 'closed', 'paused_at' => now()]);
        ProviderRegistry::create(['provider_key' => 'herosms', 'stack' => 'number', 'circuit_breaker_state' => 'closed', 'latency_ms' => 200]);
        ProviderRegistry::flushSnapshot();

        $ordered = app(CandidateOrdering::class)->order(['fivesim', 'herosms'], 'sms');

        $this->assertSame(['herosms'], $ordered);
    }

    public function test_last_resort_never_selects_a_paused_provider_during_a_total_outage(): void
    {
        Setting::setValue('routing.cb.cooldown_minutes', 999, 'routing'); // never elapses on its own
        $cb = $this->breaker();
        $this->induceFail($cb, 'herosms', 5); // opens
        $this->induceFail($cb, 'virtsms', 5); // opens

        // Both candidates are OPEN — normally lastResortAmong would pick one.
        // Pause herosms so it can never be chosen even during a total outage.
        ProviderRegistry::where('provider_key', 'herosms')->update(['paused_at' => now()]);

        $this->assertSame('virtsms', $cb->lastResortAmong(['herosms', 'virtsms']));
    }

    public function test_last_resort_returns_null_when_every_open_candidate_is_paused(): void
    {
        Setting::setValue('routing.cb.cooldown_minutes', 999, 'routing');
        $cb = $this->breaker();
        $this->induceFail($cb, 'herosms', 5);
        ProviderRegistry::where('provider_key', 'herosms')->update(['paused_at' => now()]);

        $this->assertNull($cb->lastResortAmong(['herosms']));
    }
}
