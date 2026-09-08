<?php

namespace App\Services\eSIM;

use App\Models\EsimPlan;
use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Log;

/**
 * Generates the short, customer-facing plan description shown as the AI tooltip
 * (BUILD-8 §5). Same masking discipline as everywhere else: the prompt carries
 * NO price, NO cost, and NO provider/brand name — only what the plan does
 * (data amount, validity, coverage). Degrades to null on any failure or when no
 * Anthropic key is configured, never blocking a sync (§5.4).
 */
class EsimTooltipService
{
    public function __construct(private readonly AnthropicClient $ai) {}

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * Generate + store a tooltip for one plan. Returns true if it wrote one.
     * A manual admin override always wins for display, so we skip generation for
     * an overridden plan unless $force (an explicit admin "regenerate").
     */
    public function generateFor(EsimPlan $plan, bool $force = false): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        if (! $force && filled($plan->ai_tooltip_override)) {
            return false; // override already wins — don't spend tokens
        }

        try {
            $text = $this->ai->complete($this->system(), [
                ['role' => 'user', 'content' => $this->prompt($plan)],
            ], maxTokens: 200, temperature: 0.4);

            $text = $this->clean($text);
            if ($text === '') {
                return false;
            }

            $plan->forceFill([
                'ai_tooltip' => $text,
                'ai_tooltip_generated_at' => now(),
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning("[esim] Tooltip generation failed for plan {$plan->id}: ".$e->getMessage());

            return false; // leave ai_tooltip null; never block the sync
        }
    }

    private function system(): string
    {
        return 'You write ultra-short, plain-English descriptions of prepaid travel eSIM '
            .'data plans for online shoppers. Reply with 1–2 sentences, max ~30 words. '
            .'Describe what the data allowance and validity are realistically good for '
            .'(maps, messaging, browsing, occasional video). NEVER mention any price, cost, '
            .'currency, discount, brand, carrier or provider name. No emojis, no hype, no '
            .'quotation marks — just the description.';
    }

    private function prompt(EsimPlan $plan): string
    {
        $data = $plan->data_mb
            ? rtrim(rtrim(number_format($plan->data_mb / 1024, 1), '0'), '.').' GB'
            : 'Unlimited data';
        $validity = $plan->validity_days ? "{$plan->validity_days} days" : 'flexible validity';

        $where = match ($plan->coverage_type) {
            EsimPlan::COVERAGE_GLOBAL => 'worldwide, across many countries',
            EsimPlan::COVERAGE_REGIONAL => $plan->region_slug
                ? 'across the '.\App\Support\EsimRegions::label($plan->region_slug).' region'
                : 'across multiple countries',
            default => 'in '.(\App\Support\CountryNames::name((string) (collect((array) $plan->countries)->first() ?? '')) ?: 'the destination'),
        };

        $voice = $plan->has_voice ? ' It also includes calls and SMS.' : '';

        return "Describe this eSIM plan: {$data} over {$validity}, usable {$where}.{$voice}";
    }

    /** Strip stray wrapping quotes/whitespace a model sometimes adds. */
    private function clean(string $text): string
    {
        return trim(trim($text), "\"'");
    }
}
