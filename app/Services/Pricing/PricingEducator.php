<?php

namespace App\Services\Pricing;

use App\Services\Support\Contracts\ChatModel;
use App\Support\Niche\DataEstimator;
use Illuminate\Support\Facades\Cache;

/**
 * PricingEducator (Module 29) — plain-language "what does this plan mean for me
 * long-term" education. Uses the Anthropic model when it is configured
 * (services.anthropic.api_key), and a knowledgeable deterministic explainer
 * otherwise, so the feature works with or without keys.
 *
 * Money-safety: this only ever receives PUBLIC plan facts (name, data, validity,
 * countries, the display price string). Provider cost is never passed in and can
 * never be surfaced. AI output is cached per plan signature so a public page
 * never hammers the API.
 */
class PricingEducator
{
    public function __construct(private readonly ChatModel $model)
    {
    }

    /**
     * @param  array{name:string,data_mb:?int,validity_days:?int,countries:int,price:string}  $plan
     * @return array{summary:string, tips:list<string>, ai:bool}
     */
    public function explain(array $plan): array
    {
        $key = 'pricing.edu.'.md5(json_encode($plan));

        return Cache::remember($key, now()->addDay(), function () use ($plan) {
            if ($this->model->available()) {
                try {
                    return $this->viaModel($plan);
                } catch (\Throwable) {
                    // fall through to the deterministic explainer
                }
            }

            return $this->deterministic($plan);
        });
    }

    /**
     * @param  array{name:string,data_mb:?int,validity_days:?int,countries:int,price:string}  $plan
     * @return array{summary:string, tips:list<string>, ai:bool}
     */
    private function viaModel(array $plan): array
    {
        $data = $plan['data_mb'] ? round($plan['data_mb'] / 1024, 1).' GB' : 'its data allowance';
        $validity = $plan['validity_days'] ? $plan['validity_days'].' days' : 'its validity window';

        $system = 'You are a friendly, honest travel-connectivity advisor for NaaraSim, a Pan-African '
            .'eSIM and virtual-number service. Explain in plain language what an eSIM plan means for a '
            .'traveller day to day. Be concrete about how long the data realistically lasts for typical '
            .'use (maps, messaging, social, video). Never invent prices, never mention wholesale or cost, '
            .'never promise speeds. Keep it warm and practical.';

        $user = "Explain this plan for a first-time traveller:\n"
            ."- Name: {$plan['name']}\n- Data: {$data}\n- Valid for: {$validity}\n"
            ."- Countries covered: {$plan['countries']}\n- Price shown to the user: {$plan['price']}\n\n"
            .'Reply as JSON only: {"summary": "2-3 sentence plain explanation", "tips": ["short tip", "short tip", "short tip"]}';

        $res = $this->model->reply($system, [
            ['role' => 'user', 'content' => $user],
        ], []);

        $text = collect($res['content'] ?? [])
            ->firstWhere('type', 'text')['text'] ?? '';
        $json = json_decode($this->extractJson($text), true);

        if (! is_array($json) || ! isset($json['summary'])) {
            return $this->deterministic($plan);
        }

        return [
            'summary' => (string) $json['summary'],
            'tips' => array_values(array_filter(array_map('strval', $json['tips'] ?? []))) ?: $this->deterministic($plan)['tips'],
            'ai' => true,
        ];
    }

    /**
     * Deterministic explainer from the plan facts — no API needed. Uses the same
     * usage profiles as the data estimator so the numbers stay consistent.
     *
     * @param  array{name:string,data_mb:?int,validity_days:?int,countries:int,price:string}  $plan
     * @return array{summary:string, tips:list<string>, ai:bool}
     */
    private function deterministic(array $plan): array
    {
        $gb = $plan['data_mb'] ? round($plan['data_mb'] / 1024, 1) : null;
        $days = $plan['validity_days'] ?: null;

        $summary = $gb
            ? "{$gb} GB is a solid allowance for everyday travel — maps, messaging, ride-hailing and social."
            : 'This plan covers your everyday travel data.';

        if ($gb && $days) {
            $lightDays = max(1, DataEstimator::daysFor($gb, 'light'));
            $mediumDays = max(1, DataEstimator::daysFor($gb, 'medium'));
            $light = $lightDays.' '.\Illuminate\Support\Str::plural('day', $lightDays);
            $medium = $mediumDays.' '.\Illuminate\Support\Str::plural('day', $mediumDays);
            $valid = $days.' '.\Illuminate\Support\Str::plural('day', $days);
            $summary .= " For light use it can stretch to about {$light}, or roughly {$medium} "
                ."at a more typical pace — and it stays valid for {$valid}, so there's no rush to use it up.";
        }

        $tips = [];
        if ($gb) {
            $tips[] = 'Streaming video is the biggest drain — download shows and maps on Wi-Fi before you head out.';
        }
        if ($days) {
            $tips[] = "The clock only starts when your eSIM first connects abroad, not when you buy — install it before you fly.";
        }
        $tips[] = $plan['countries'] > 1
            ? 'Covers multiple countries, so you stay on one plan across the whole trip — no swapping.'
            : 'Running low is fine — top up from your dashboard and your eSIM stays installed.';

        return ['summary' => $summary, 'tips' => $tips, 'ai' => false];
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return $m[0];
        }

        return $text;
    }
}
