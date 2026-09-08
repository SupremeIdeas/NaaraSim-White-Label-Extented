<?php

namespace App\Services\eSIM;

use App\Models\EsimPlan;
use App\Services\AI\AnthropicClient;
use App\Support\CountryNames;
use App\Support\EsimRegions;

/**
 * Claude-assisted margin SUGGESTION for a single eSIM plan (BUILD-8 §4.4).
 *
 * Suggestion-only by contract: this returns a proposed markup % + a one-line
 * rationale for the admin to review. It NEVER writes override_markup_pct — the
 * admin's explicit save does that. The plan's cost is sent to the model
 * (server-side, admin context only — never to a user-facing payload) so the
 * suggestion can respect the real margin; the returned markup still flows through
 * PricingEngine + MarginGuard on save, so a bad suggestion can't underprice.
 */
class EsimMarginAdvisor
{
    public function __construct(private readonly AnthropicClient $ai) {}

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * @return array{markup_pct: float, rationale: string}
     */
    public function suggest(EsimPlan $plan): array
    {
        $coverage = match ($plan->coverage_type) {
            EsimPlan::COVERAGE_GLOBAL => 'global (worldwide)',
            EsimPlan::COVERAGE_REGIONAL => 'regional'.($plan->region_slug ? ' — '.EsimRegions::label($plan->region_slug) : ''),
            default => 'local — '.(CountryNames::name((string) (collect((array) $plan->countries)->first() ?? '')) ?: 'single country'),
        };
        $data = $plan->data_mb ? round($plan->data_mb / 1024, 1).' GB' : 'unlimited';

        $system = 'You are a pricing analyst for a Pan-African travel-eSIM retailer competing with '
            .'Airalo and Holafly. Given a plan\'s wholesale cost and attributes, suggest a single '
            .'retail markup percentage over cost that is competitive yet profitable. Respond ONLY '
            .'with strict JSON: {"markup_pct": <number>, "rationale": "<one short sentence>"}. '
            .'markup_pct is a percentage over cost (e.g. 35 means +35%). No prose outside the JSON.';

        $user = sprintf(
            "Wholesale cost: $%.4f USD. Coverage: %s. Data: %s. Validity: %s days. Voice: %s.",
            (float) $plan->cost_price_usd,
            $coverage,
            $data,
            $plan->validity_days ?: 'n/a',
            $plan->has_voice ? 'yes' : 'no',
        );

        $json = $this->ai->completeJson($system, [['role' => 'user', 'content' => $user]], maxTokens: 300);

        $markup = (float) ($json['markup_pct'] ?? 0);
        // Clamp to a sane band so a malformed response can't propose nonsense.
        $markup = max(0.0, min(500.0, $markup));

        return [
            'markup_pct' => round($markup, 2),
            'rationale' => trim((string) ($json['rationale'] ?? '')) ?: 'Suggested from cost, coverage, data and validity.',
        ];
    }
}
