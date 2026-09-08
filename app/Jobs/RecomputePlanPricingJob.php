<?php

namespace App\Jobs;

use App\Models\EsimPlan;
use App\Services\Pricing\PricingEngine;
use App\Support\EsimCatalogue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recompute computed_retail_usd for every plan (or one provider's plans) via
 * the PricingEngine. Dispatched when the admin changes the global markup or a
 * pricing floor — blueprint rule 1.4: "recompute ALL plans, not just newly
 * synced ones." Runs on Horizon; no external API calls, purely internal math.
 */
class RecomputePlanPricingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $provider = null) {}

    public function handle(PricingEngine $engine): void
    {
        EsimPlan::query()
            ->when($this->provider, fn ($q) => $q->where('provider', $this->provider))
            ->chunkById(500, function ($plans) use ($engine) {
                foreach ($plans as $plan) {
                    $engine->recompute($plan);
                }
            });

        // Readiness-audit fix (2026-09-07): a global markup/floor change must
        // invalidate the storefront's cached "from $X" teaser grid once, after
        // every plan's price has actually changed — not leave it serving the
        // pre-markup-change prices until an unrelated catalogue action flushes it.
        EsimCatalogue::flush();
    }
}
