<?php

namespace App\Services\Routing;

use App\Models\ProviderRegistry;

/**
 * NAARA-BUILD-15 §4 — registry-informed candidate ordering. Given a router's
 * existing static candidate list, it drops providers whose circuit is OPEN and
 * orders the rest by the live registry snapshot (fastest + most reliable first),
 * keeping the caller's original order as the final tiebreaker so an admin/static
 * priority is still honoured.
 *
 * Two hard guarantees:
 *  - It only ever CHANGES THE ORDER (and skips open circuits) — never whether the
 *    live purchase call happens. Even the top candidate still gets a real attempt.
 *  - It reads ONLY the cached snapshot (ProviderRegistry::snapshot()), never a raw
 *    query, and falls back to the caller's original order verbatim if the snapshot
 *    is empty/unavailable — so a registry problem can never break routing.
 *
 * BUILD-16 extends the sort here to also weigh nci_score, from the same snapshot.
 */
class CandidateOrdering
{
    /**
     * @param  list<string>  $candidates  the router's static order (priority)
     * @return list<string>  reordered, open circuits removed
     */
    public function order(array $candidates, string $stack): array
    {
        $snapshot = ProviderRegistry::snapshot();
        if ($snapshot === []) {
            return $candidates; // fresh install / cache miss → today's static order
        }

        // Preserve original index as the priority tiebreaker.
        $withIndex = [];

        // NCI kill switch (BUILD-19 §1): when nci.enabled is false, NCI's score is
        // ignored entirely and ordering falls back to the pre-NCI latency /
        // success-rate ordering. Circuit breakers stay fully active regardless.
        $nciOn = (bool) \App\Models\Setting::getValue('nci.enabled', true);

        foreach ($candidates as $i => $key) {
            $row = $snapshot[$key] ?? null;

            // Exclude a provider whose circuit is OPEN outright.
            if ($row !== null && ($row['circuit_breaker_state'] ?? 'closed') === CircuitBreaker::OPEN) {
                continue;
            }

            // NCI influence weighted by its own confidence (§3): a score from 5
            // outcomes nudges gently, one from 5,000 carries real weight —
            // multiply score by confidence before it competes with latency/rate.
            // -1 = "no opinion" (NCI off, or no score yet), so it never front-runs.
            $score = $row !== null && ($row['nci_score'] ?? null) !== null ? (float) $row['nci_score'] : null;
            $conf = $row !== null && ($row['nci_confidence'] ?? null) !== null ? (float) $row['nci_confidence'] : 0.0;
            $nciEffective = ($nciOn && $score !== null) ? $score * $conf : -1.0;

            $withIndex[] = [
                'key' => $key,
                'priority' => $i,
                // Missing metrics sort last within their tier, never ahead of a
                // proven provider — but still ahead of nothing.
                'latency' => $row['latency_ms'] ?? PHP_INT_MAX,
                'success_rate' => $row !== null && $row['success_rate_24h'] !== null
                    ? (float) $row['success_rate_24h'] : -1.0,
                'nci_score' => $nciEffective,
            ];
        }

        // Everything open? Fall back to the original list rather than emptying the
        // lane — the breaker's HALF_OPEN cooldown still gates the actual attempt.
        if ($withIndex === []) {
            return $candidates;
        }

        // Admin manual preference (BUILD-17 §1.4): if an admin has pinned a
        // provider for this stack (and it isn't open), it sorts first — the
        // "admin-set manual priority" slot BUILD-15 §4.1 reserved.
        $preferred = \App\Support\RoutingPreference::preferred($stack);

        usort($withIndex, function ($a, $b) use ($preferred) {
            $ap = $a['key'] === $preferred ? 1 : 0;
            $bp = $b['key'] === $preferred ? 1 : 0;

            // Preference first, then NCI score (populated in BUILD-16; -1 until
            // then, a no-op nudge), then latency, then 24h success rate, then the
            // caller's original priority.
            return [$bp, $b['nci_score'], $a['latency'], $b['success_rate'], $a['priority']]
                <=> [$ap, $a['nci_score'], $b['latency'], $a['success_rate'], $b['priority']];
        });

        return array_map(fn ($r) => $r['key'], $withIndex);
    }
}
