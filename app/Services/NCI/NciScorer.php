<?php

namespace App\Services\NCI;

use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * NAARA-BUILD-16 — Naara Core Intelligence, Layer 3 (learning). The scoring
 * brain: an asynchronous OBSERVER of completed outcomes that computes a
 * reliability score, a confidence, and a risk rating, and writes them to the
 * five NCI-owned registry columns.
 *
 * The non-negotiable boundary: nothing here may run inside a live customer
 * purchase. Every entry point is invoked only from a QUEUED listener or the
 * scheduled nci:recompute command — never synchronously from a router. NCI
 * writes ONLY its five columns and never reads or writes another layer's.
 */
class NciScorer
{
    /**
     * EMA smoothing for the per-outcome nudge (§3.1). Small on purpose: at 0.05,
     * a single bad transaction moves the score at most 5% toward 0, so one blip
     * can't tank a provider that is otherwise reliable — the "don't let one bad
     * transaction decide a provider's standing" property a real score needs.
     */
    private const ALPHA = 0.05;

    /** Trailing window for the full recompute (§3.2). */
    private const WINDOW_DAYS = 30;

    /**
     * Sample size at which confidence saturates to 1.0. Below it, confidence
     * scales linearly, so a provider with 10 outcomes this month is never as
     * trusted as one with thousands (§3.2).
     */
    private const CONFIDENCE_FULL_SAMPLE = 200;

    /** Below this many outcomes, risk stays 'medium' (not enough to judge). */
    private const MIN_SAMPLE_FOR_RISK = 10;

    /**
     * Prompt 10 — below this many outcomes in the window, publicSuccessRate()
     * returns null rather than a rate built on too little data to be honest.
     */
    private const PUBLIC_MIN_SAMPLE = 20;

    /** How long a public success rate is cached before recomputing from outcomes. */
    private const PUBLIC_RATE_TTL_MINUTES = 15;

    /**
     * §6 — the MOST a healthy margin can lift a provider's score. Deliberately
     * sub-resolution (0.003 ≪ any meaningful reliability gap) so reliability
     * always dominates: margin only ever decides between providers whose
     * reliability is a hair apart. A secondary tie-break, never a driver.
     */
    private const MARGIN_TIEBREAK = 0.003;

    /** Failure-rate bands for the risk rating (over the window). */
    private const RISK_HIGH_RATE = 0.35;

    private const RISK_MEDIUM_RATE = 0.15;

    /** A single error code owning at least this share of failures reads as "concentrated". */
    private const CONCENTRATION = 0.7;

    /** Error-code fragments that are HARD provider faults (vs. expected inventory). */
    private const HARD_ERROR_HINTS = ['timeout', 'auth', 'connection', 'refused', '500', '502', '503', '504'];

    private const INVENTORY_HINTS = ['out_of_stock', 'outofstock', 'maintenance', 'low_balance'];

    /**
     * §3.1 — incremental, per-outcome EMA nudge. Called from the QUEUED
     * ProviderOutcomeRecorded listener. Writes only nci_score / nci_sample_size /
     * nci_computed_at, and only if the registry row already exists (CircuitBreaker
     * creates it on the same outcome, synchronously, before this runs).
     */
    public function applyOutcome(string $providerKey, bool $success): void
    {
        $row = ProviderRegistry::where('provider_key', $providerKey)->first();
        if ($row === null) {
            return;
        }

        $target = $success ? 1.0 : 0.0;
        $old = $row->nci_score;
        $new = $old === null ? $target : $old + self::ALPHA * ($target - $old);

        ProviderRegistry::where('provider_key', $providerKey)->update([
            'nci_score' => round($new, 4),
            'nci_sample_size' => (int) $row->nci_sample_size + 1,
            'nci_computed_at' => now(),
        ]);
    }

    /**
     * §3.2 — full recompute for one provider over the trailing window: writes all
     * five NCI columns in one pass. Called from queued listeners and nci:recompute.
     */
    public function recompute(string $providerKey): void
    {
        // Only ever touch an existing registry row — never create one (that would
        // mean writing non-NCI columns, which NCI must not do).
        if (! ProviderRegistry::where('provider_key', $providerKey)->exists()) {
            return;
        }

        $since = now()->subDays(self::WINDOW_DAYS);
        $rows = ProviderOutcome::where('provider_key', $providerKey)
            ->where('occurred_at', '>=', $since)->get(['outcome', 'error_code']);

        $total = $rows->count();
        $failures = $rows->where('outcome', ProviderOutcome::FAILURE);
        $failCount = $failures->count();
        $succCount = $total - $failCount;
        $failRate = $total > 0 ? $failCount / $total : 0.0;

        // §6 — reliability is the score; margin is a tiny secondary tie-break laid
        // on top (capped at MARGIN_TIEBREAK), so two providers of near-equal
        // reliability are separated by which one earns NaaraSim more, but a
        // genuinely more reliable provider always outranks a more profitable one.
        $reliability = $total > 0 ? $succCount / $total : null;
        $score = $reliability === null
            ? null
            : min(1.0, round($reliability + $this->marginTieBreak($providerKey, $since), 4));

        ProviderRegistry::where('provider_key', $providerKey)->update([
            'nci_score' => $score,
            'nci_confidence' => round(min(1.0, $total / self::CONFIDENCE_FULL_SAMPLE), 4),
            'nci_risk_rating' => $this->risk($total, $failRate, $failures->pluck('error_code')->all()),
            'nci_sample_size' => $total,
            'nci_computed_at' => now(),
        ]);
    }

    /**
     * §6 — a bounded [0, MARGIN_TIEBREAK] bonus from the provider's realised
     * margin over the window. NCI reads its OWN margin picture from the sales
     * ledgers (OrderLog for eSIM, SmsOrder for numbers) — cost never leaves Layer
     * 3, and this influence is far too small to override reliability. A provider
     * with no recorded sales gets zero bonus (no opinion), never a penalty.
     */
    private function marginTieBreak(string $providerKey, \Illuminate\Support\Carbon $since): float
    {
        $fractions = [];

        // eSIM sales — profit / provider_cost per order (cost > 0 only).
        foreach (\App\Models\OrderLog::where('provider', $providerKey)
            ->where('created_at', '>=', $since)
            ->where('provider_cost', '>', 0)
            ->get(['provider_cost', 'profit']) as $o) {
            $fractions[] = (float) $o->profit / (float) $o->provider_cost;
        }

        // Number sales — same shape from the SMS ledger.
        foreach (\App\Models\SmsOrder::where('provider', $providerKey)
            ->where('ordered_at', '>=', $since)
            ->where('provider_cost', '>', 0)
            ->get(['provider_cost', 'profit']) as $o) {
            $fractions[] = (float) $o->profit / (float) $o->provider_cost;
        }

        if ($fractions === []) {
            return 0.0; // no sales to judge margin on — no nudge either way
        }

        $avg = array_sum($fractions) / count($fractions);

        // Clamp: a negative margin never drags reliability down (the MarginGuard
        // and circuit breaker own loss-prevention); a 100%+ margin caps the bonus.
        return self::MARGIN_TIEBREAK * max(0.0, min(1.0, $avg));
    }

    /**
     * Prompt 10 — a read-only, customer-safe success-rate projection for the
     * Country/Service pickers. Deliberately NOT `nci_score`: that column folds
     * in the §6 margin tie-break (an internal ranking nudge, however tiny) and
     * is influenced by the NCI kill switch and confidence weighting — neither
     * belongs in a number a customer reads as "how often did this work." This
     * is the plain success/total ratio over the same trailing window, nothing
     * else, computed directly from the outcome log NCI already learns from.
     *
     * Below PUBLIC_MIN_SAMPLE outcomes it returns null (not enough data to
     * judge honestly) rather than a number built on a handful of tries — the
     * caller must treat null as "don't show a rate," never as zero.
     *
     * A pure read: it never writes, and it is safe to call from page render
     * (unlike recompute()/applyOutcome(), which stay queued-listener-only) —
     * it only ever reads the outcome log, cached briefly so a picker showing
     * many rows doesn't run this query once per row per request.
     */
    public function publicSuccessRate(string $providerKey): ?float
    {
        return Cache::remember(
            "nci:public_success_rate:{$providerKey}",
            now()->addMinutes(self::PUBLIC_RATE_TTL_MINUTES),
            function () use ($providerKey) {
                $since = now()->subDays(self::WINDOW_DAYS);
                $base = ProviderOutcome::where('provider_key', $providerKey)->where('occurred_at', '>=', $since);

                $total = (clone $base)->count();
                if ($total < self::PUBLIC_MIN_SAMPLE) {
                    return null;
                }

                $success = (clone $base)->where('outcome', ProviderOutcome::SUCCESS)->count();

                return round($success / $total, 4);
            }
        );
    }

    /** Best (highest) public success rate among a lane of candidate providers, or null if none qualify. */
    public function bestPublicSuccessRate(array $providerKeys): ?float
    {
        $rates = array_filter(array_map(fn ($key) => $this->publicSuccessRate($key), $providerKeys), fn ($r) => $r !== null);

        return $rates === [] ? null : max($rates);
    }

    /** Drop a provider's cached public success rate (tests / an admin manual recompute). */
    public static function flushPublicSuccessRate(string $providerKey): void
    {
        Cache::forget("nci:public_success_rate:{$providerKey}");
    }

    /** Recompute every provider (nci:recompute, and the health-tick refresh). */
    public function recomputeAll(): void
    {
        foreach (ProviderRegistry::pluck('provider_key') as $key) {
            $this->recompute($key);
        }
    }

    /**
     * Risk from failure-rate AND error-code diversity: a burst of concentrated
     * HARD failures (timeouts/auth) reads as high risk even at a moderate rate,
     * while the same rate made of expected inventory failures (out-of-stock) is
     * gentler — the distinction §3.2 asks for.
     *
     * @param  list<?string>  $failureCodes
     */
    private function risk(int $total, float $failRate, array $failureCodes): string
    {
        if ($total < self::MIN_SAMPLE_FOR_RISK) {
            return 'medium'; // too little data to judge — neither trust nor condemn
        }

        $concentratedHard = $this->concentratedHardFailure($failureCodes);

        if ($failRate >= self::RISK_HIGH_RATE || ($concentratedHard && $failRate >= self::RISK_MEDIUM_RATE)) {
            return 'high';
        }
        if ($failRate >= self::RISK_MEDIUM_RATE || $concentratedHard) {
            return 'medium';
        }

        return 'low';
    }

    /** True when one HARD error code owns most failures (a systemic fault, not noise). */
    private function concentratedHardFailure(array $failureCodes): bool
    {
        $codes = array_filter($failureCodes, fn ($c) => $c !== null && $c !== '');
        if ($codes === []) {
            return false;
        }

        $counts = array_count_values($codes);
        arsort($counts);
        $topCode = (string) array_key_first($counts);
        $share = reset($counts) / count($codes);

        if ($share < self::CONCENTRATION) {
            return false;
        }

        $lower = strtolower($topCode);
        foreach (self::INVENTORY_HINTS as $inv) {
            if (str_contains($lower, $inv)) {
                return false; // an expected inventory failure — not a systemic fault
            }
        }
        foreach (self::HARD_ERROR_HINTS as $hard) {
            if (str_contains($lower, $hard)) {
                return true;
            }
        }

        // A concentrated but unclassified code: treat as a soft signal, not hard.
        return false;
    }
}
