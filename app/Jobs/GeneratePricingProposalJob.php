<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Pricing\PricingArchitect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generate an AI pricing proposal off the request cycle (money-safety rule 8 —
 * every external API call is a queued job with retry/backoff). The Anthropic
 * call can take many seconds; the admin page shows "analysing…" and the pending
 * proposal appears when this finishes. Nothing is applied here — the admin still
 * approves.
 */
class GeneratePricingProposalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 20;

    public function __construct(public ?int $adminId = null)
    {
    }

    public function handle(PricingArchitect $architect): void
    {
        if (! $architect->enabled()) {
            return; // key not active — nothing to do
        }

        try {
            $architect->propose($this->adminId ? User::find($this->adminId) : null);
        } catch (\Throwable $e) {
            // Never blind-retry into a paid API forever; log and let $tries cap it.
            Log::warning('Pricing proposal generation failed: '.$e->getMessage());
            throw $e;
        }
    }
}
