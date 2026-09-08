<?php

namespace App\Livewire;

use App\Models\EsimPlan;
use App\Services\Pricing\PricingEducator;
use App\Support\PricingDisplay;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public pricing page (Module 29). Shows real plans in a comparison layout when
 * an eSIM provider is live (admin can force live/estimate/auto), or honest
 * estimate tiers before launch. Each plan can be "explained" in plain language
 * via the PricingEducator (AI when configured, deterministic otherwise). CTAs
 * route a guest into registration and a signed-in user straight to checkout.
 */
#[Layout('components.layouts.marketing')]
class PricingPage extends Component
{
    /** planId (or estimate tier index prefixed 'e') => explanation array. */
    public array $explanations = [];

    public ?string $openExplainer = null;

    public function explain(string $ref, PricingEducator $educator): void
    {
        if (isset($this->explanations[$ref])) {
            $this->openExplainer = $this->openExplainer === $ref ? null : $ref;

            return;
        }

        $plan = $this->planFacts($ref);
        if ($plan) {
            $this->explanations[$ref] = $educator->explain($plan);
            $this->openExplainer = $ref;
        }
    }

    /** Public facts only (never cost) for the educator. */
    private function planFacts(string $ref): ?array
    {
        if (str_starts_with($ref, 'e')) {
            $tier = PricingDisplay::estimateTiers()[(int) substr($ref, 1)] ?? null;

            return $tier ? [
                'name' => $tier['name'],
                'data_mb' => $this->gbToMb($tier['data'] ?? ''),
                'validity_days' => (int) filter_var($tier['validity'] ?? '', FILTER_SANITIZE_NUMBER_INT) ?: null,
                'countries' => 190,
                'price' => 'from $'.number_format((float) ($tier['from_usd'] ?? 0), 2),
            ] : null;
        }

        $plan = EsimPlan::where('is_active', true)->find((int) $ref);

        return $plan ? [
            'name' => $plan->name,
            'data_mb' => $plan->data_mb,
            'validity_days' => $plan->validity_days,
            'countries' => is_array($plan->countries) ? count($plan->countries) : 1,
            'price' => $plan->display_price['usd'],
        ] : null;
    }

    private function gbToMb(string $data): ?int
    {
        if (! preg_match('/([\d.]+)\s*(gb|mb)/i', $data, $m)) {
            return null;
        }

        return strtolower($m[2]) === 'gb' ? (int) round((float) $m[1] * 1024) : (int) $m[1];
    }

    public function render()
    {
        $mode = PricingDisplay::mode();

        return view('livewire.pricing-page', [
            'mode' => $mode,
            'plans' => $mode === 'live' ? PricingDisplay::livePlans() : collect(),
            'tiers' => $mode === 'estimate' ? PricingDisplay::estimateTiers() : [],
        ]);
    }
}
