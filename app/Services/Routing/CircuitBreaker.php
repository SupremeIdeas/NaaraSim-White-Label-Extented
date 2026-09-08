<?php

namespace App\Services\Routing;

use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * NAARA-BUILD-15 — the ONE circuit breaker, used identically by all three
 * routers (eSIM / SMS / permanent). It sits in FRONT of each router's existing,
 * unchanged failover loop:
 *   - allows() lets the loop skip a likely-failing provider before wasting a
 *     live call on it;
 *   - record() logs every real attempt and, synchronously, moves the circuit
 *     state on real transaction outcomes (the fastest, most honest signal — not
 *     waiting for the 15-minute health tick).
 *
 * State lives in provider_registry.circuit_breaker_state — this class is the
 * ONLY writer to that column (Layer 2 owns circuit breakers). Reads/writes here
 * hit the registry directly; ROUTER code only ever reads the cached snapshot
 * (via CandidateOrdering), so the caching discipline is never bypassed.
 *
 * NCI (BUILD-16) will subscribe to the transitions this class makes; this batch
 * emits no events and contains zero learning logic.
 */
class CircuitBreaker
{
    public const CLOSED = 'closed';

    public const OPEN = 'open';

    public const HALF_OPEN = 'half_open';

    /** How long an OPEN circuit waits before allowing one HALF_OPEN probe. */
    private function cooldownMinutes(): int
    {
        return (int) Setting::getValue('routing.cb.cooldown_minutes', 5);
    }

    /** Consecutive failures that trip CLOSED → OPEN. */
    private function failureThreshold(): int
    {
        return (int) Setting::getValue('routing.cb.consecutive_failures', 5);
    }

    /** Sliding window size for the failure-rate rule. */
    private function window(): int
    {
        return (int) Setting::getValue('routing.cb.window', 20);
    }

    /** Failure rate over the window that also trips CLOSED → OPEN (0–1). */
    private function windowRate(): float
    {
        return (float) Setting::getValue('routing.cb.window_failure_rate', 0.5);
    }

    /** Can this provider be attempted right now? Handles the OPEN→HALF_OPEN cooldown. */
    public function allows(string $providerKey): bool
    {
        $state = $this->stateOf($providerKey);
        if ($state !== self::OPEN) {
            return true; // CLOSED, HALF_OPEN, or unknown → attempt normally
        }

        // OPEN: allow exactly when the cooldown has elapsed, transitioning to a
        // single HALF_OPEN probe. A lost opened-at timestamp is treated as elapsed.
        // Elapsed is computed from timestamps to avoid Carbon's signed-diff pitfall.
        $openedAt = Cache::get($this->openedKey($providerKey));
        $elapsedSeconds = $openedAt === null
            ? PHP_INT_MAX
            : now()->timestamp - \Illuminate\Support\Carbon::parse($openedAt)->timestamp;

        if ($elapsedSeconds >= $this->cooldownMinutes() * 60) {
            $this->setState($providerKey, self::HALF_OPEN);

            return true;
        }

        return false;
    }

    /**
     * Record one real attempt and, synchronously, evaluate the state machine.
     * Called from inside each router's loop after the actual purchase call —
     * a local DB write, never a remote call, so it's safe on the request path.
     */
    public function record(string $providerKey, string $stack, string $outcome, ?string $errorCode = null, ?string $orderRef = null): void
    {
        ProviderOutcome::create([
            'provider_key' => $providerKey,
            'stack' => $stack,
            'outcome' => $outcome === ProviderOutcome::SUCCESS ? ProviderOutcome::SUCCESS : ProviderOutcome::FAILURE,
            // BUILD-19 §9 — redact any PII a provider inlined into the error.
            'error_code' => \App\Support\PiiRedactor::redact($errorCode),
            'order_ref' => $orderRef,
            'occurred_at' => now(),
        ]);

        $this->refreshRolling($providerKey);
        $this->evaluate($providerKey, $outcome === ProviderOutcome::SUCCESS);

        ProviderRegistry::flushSnapshot();

        // BUILD-16 §1 — announce the outcome AFTER the synchronous work above.
        // NCI's listener is queued, so this does not add NCI work to the request.
        \App\Events\ProviderOutcomeRecorded::dispatch(
            $providerKey, $stack,
            $outcome === ProviderOutcome::SUCCESS ? ProviderOutcome::SUCCESS : ProviderOutcome::FAILURE,
            $errorCode, now(),
        );
    }

    /**
     * NAARA-BUILD-17 — manual admin controls for the Operations Center. These are
     * the ONLY sanctioned way the Ops Center changes circuit state: it calls these
     * transition methods directly (§0) rather than reimplementing the logic. Both
     * flush the snapshot and fire the same CircuitOpened/CircuitClosed events an
     * automatic transition does (so NCI + alerts react identically).
     */
    public function forceOpen(string $providerKey): void
    {
        $this->setState($providerKey, self::OPEN);
        ProviderRegistry::flushSnapshot();
    }

    public function forceClose(string $providerKey): void
    {
        $this->setState($providerKey, self::CLOSED);
        ProviderRegistry::flushSnapshot();
    }

    /** Move the state machine after an outcome. */
    private function evaluate(string $providerKey, bool $success): void
    {
        $state = $this->stateOf($providerKey);

        if ($state === self::HALF_OPEN) {
            // The single probe resolves the circuit immediately.
            $this->setState($providerKey, $success ? self::CLOSED : self::OPEN);

            return;
        }

        if ($state === self::CLOSED && ! $success) {
            $recent = ProviderOutcome::where('provider_key', $providerKey)
                ->latest('occurred_at')->limit($this->window())->get();

            // Consecutive failures counting back from the most recent attempt.
            $consecutive = 0;
            foreach ($recent as $row) {
                if ($row->outcome === ProviderOutcome::FAILURE) {
                    $consecutive++;
                } else {
                    break;
                }
            }

            $count = $recent->count();
            $failRate = $count > 0
                ? $recent->where('outcome', ProviderOutcome::FAILURE)->count() / $count
                : 0.0;

            if ($consecutive >= $this->failureThreshold()
                || ($count >= $this->window() && $failRate > $this->windowRate())) {
                $this->setState($providerKey, self::OPEN);
            }
        }
    }

    /** Recompute the registry's rolling 24h reliability from the outcome log. */
    private function refreshRolling(string $providerKey): void
    {
        $since = now()->subDay();
        $base = ProviderOutcome::where('provider_key', $providerKey)->where('occurred_at', '>=', $since);
        $succ = (clone $base)->where('outcome', ProviderOutcome::SUCCESS)->count();
        $fail = (clone $base)->where('outcome', ProviderOutcome::FAILURE)->count();
        $total = $succ + $fail;

        // updateOrCreate so the row exists even before any health tick / state
        // transition — a provider's very first attempt should still record its
        // reliability.
        $meta = ProviderRegistry::deriveMeta($providerKey);
        ProviderRegistry::updateOrCreate(
            ['provider_key' => $providerKey],
            [
                'success_count_24h' => $succ,
                'failure_count_24h' => $fail,
                'success_rate_24h' => $total > 0 ? round($succ / $total, 4) : null,
                'stack' => $meta['stack'],
                'product_families' => $meta['product_families'],
            ],
        );
    }

    /** Current circuit state (registry is authoritative; default CLOSED). */
    private function stateOf(string $providerKey): string
    {
        $state = ProviderRegistry::where('provider_key', $providerKey)->value('circuit_breaker_state');

        return $state ?: self::CLOSED;
    }

    private function setState(string $providerKey, string $state): void
    {
        $previous = $this->stateOf($providerKey);

        // updateOrCreate so a not-yet-registered provider (fresh install before the
        // first health tick) still gets a state row rather than silently no-op'ing.
        $meta = ProviderRegistry::deriveMeta($providerKey);
        ProviderRegistry::updateOrCreate(
            ['provider_key' => $providerKey],
            ['circuit_breaker_state' => $state, 'stack' => $meta['stack'], 'product_families' => $meta['product_families']],
        );

        if ($state === self::OPEN) {
            Cache::put($this->openedKey($providerKey), now(), now()->addDay());
        } elseif ($state === self::CLOSED) {
            Cache::forget($this->openedKey($providerKey));
        }

        // BUILD-16 §1 — announce the transition (NCI listens, queued). Only on a
        // genuine change, so a re-affirmed state doesn't spam the bus.
        if ($state !== $previous) {
            if ($state === self::OPEN) {
                \App\Events\CircuitOpened::dispatch($providerKey);
                $this->alertTransition($providerKey, true);
            } elseif ($state === self::CLOSED) {
                \App\Events\CircuitClosed::dispatch($providerKey);
                $this->alertTransition($providerKey, false);
            }
        }
    }

    /**
     * BUILD-19 §2 — close the silent-failure gap: a circuit opening/closing is
     * alerted through the existing AlertAdminJob (not a second alerting path),
     * naming the real provider and the affected Naara product family. Recovery is
     * a lower-urgency notice so a paged admin knows it self-healed.
     */
    private function alertTransition(string $providerKey, bool $opened): void
    {
        $families = collect(ProviderRegistry::deriveMeta($providerKey)['product_families'])
            ->map(fn ($f) => \App\Support\OperationsCenter::familyName($f))->implode(', ') ?: 'unknown';

        if ($opened) {
            \App\Jobs\AlertAdminJob::dispatch(
                code: 'circuit-open-'.$providerKey,
                message: "Circuit opened: {$providerKey} (affects {$families}) — routing is skipping it until it recovers.",
                context: ['provider' => $providerKey, 'families' => $families],
                severity: 'critical',
            );
        } else {
            \App\Jobs\AlertAdminJob::dispatch(
                code: 'circuit-recovered-'.$providerKey,
                message: "Circuit recovered: {$providerKey} (affects {$families}) is back in rotation.",
                context: ['provider' => $providerKey, 'families' => $families],
                severity: 'info',
            );
        }
    }

    /**
     * BUILD-19 §5 — total-outage last resort. When EVERY candidate for a request
     * has an open circuit, pick the one whose most recent failure is OLDEST (been
     * failing longest ago → most likely to have recovered) so the customer still
     * gets one real attempt instead of an immediate hard failure. Returns null if
     * any candidate is actually attemptable (then there is no outage).
     */
    public function lastResortAmong(array $candidates): ?string
    {
        if ($candidates === []) {
            return null;
        }
        // If anything is attemptable, this is not a total outage.
        foreach ($candidates as $c) {
            if ($this->stateOf($c) !== self::OPEN) {
                return null;
            }
        }

        $best = null;
        $bestFailedAt = null;
        foreach ($candidates as $c) {
            $lastFail = ProviderOutcome::where('provider_key', $c)
                ->where('outcome', ProviderOutcome::FAILURE)->max('occurred_at');
            // No recorded failure → treat as the freshest candidate (try it first).
            $ts = $lastFail !== null ? strtotime((string) $lastFail) : 0;
            if ($bestFailedAt === null || $ts < $bestFailedAt) {
                $bestFailedAt = $ts;
                $best = $c;
            }
        }

        return $best;
    }

    private function openedKey(string $providerKey): string
    {
        return 'cb:opened:'.$providerKey;
    }
}
