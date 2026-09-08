<?php

namespace App\Jobs;

use App\Models\EsimPlan;
use App\Services\eSIM\EsimTooltipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate AI tooltips for a set of eSIM plans off the request cycle (BUILD-8 §5;
 * money-safety rule 8 — external API calls are always queued). Dispatched after a
 * catalogue sync with only the plan IDs whose data/validity/coverage actually
 * changed, so unchanged plans are never regenerated (cost control, §5.1). The
 * admin "regenerate" action reuses this with $force to override that skip.
 */
class GenerateEsimTooltipsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    /** @param  list<int>  $planIds */
    public function __construct(public array $planIds, public bool $force = false) {}

    public function handle(EsimTooltipService $tooltips): void
    {
        if (! $tooltips->enabled() || $this->planIds === []) {
            return; // no key configured, or nothing to do
        }

        EsimPlan::query()->whereIn('id', $this->planIds)
            ->get()
            ->each(fn (EsimPlan $plan) => $tooltips->generateFor($plan, $this->force));
    }
}
