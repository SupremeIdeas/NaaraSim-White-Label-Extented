<?php

namespace App\Livewire\Admin;

use App\Jobs\GeneratePricingProposalJob;
use App\Models\PricingProposal;
use App\Services\Pricing\PricingArchitect as Architect;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin: "Plan Price with Claude" — the AI Pricing Architect panel.
 *
 * Lets the owner ask Claude to analyse live provider costs + current retail and
 * PROPOSE optimal prices, review the proposal line-by-line (with the guaranteed
 * profit floor shown), then approve to apply. Every price is MarginGuard-clamped
 * on generation AND again on apply, so approving can never set a price below
 * cost + minimum profit. Lights up only when the Anthropic key is active; the
 * always-on margin monitor works regardless.
 */
#[Layout('components.layouts.admin')]
class PricingArchitect extends Component
{
    public bool $analyzing = false;

    public ?string $error = null;

    public ?string $notice = null;

    /**
     * Enforce the admin gate on every request. Livewire method calls hit the
     * shared `/livewire/update` endpoint, which does not re-run the route's
     * `role:` middleware — so approve()/analyze() (which apply live pricing and
     * spend Claude credits) must be re-authorized here, on initial load AND
     * every update. See Pricing::booted() for the full rationale.
     */
    public function booted(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function analyze(Architect $architect): void
    {
        $this->error = $this->notice = null;

        if (! $architect->enabled()) {
            $this->error = 'Add your Anthropic API key under Provider keys to enable Claude pricing.';

            return;
        }

        // Queued (money-safety rule 8). On the sync queue it runs immediately and
        // the pending proposal is on screen at the next render; on Horizon the
        // page polls until it lands.
        GeneratePricingProposalJob::dispatch(Auth::id());
        $this->analyzing = true;
        $this->notice = 'Claude is analysing your providers and prices…';
    }

    public function toggleLine(int $lineId): void
    {
        $proposal = $this->pendingProposal();
        $line = $proposal?->lines()->whereKey($lineId)->first();
        if ($line) {
            $line->update(['accepted' => ! $line->accepted]);
        }
    }

    public function approve(Architect $architect): void
    {
        $this->error = $this->notice = null;
        $proposal = $this->pendingProposal();
        if (! $proposal) {
            $this->error = 'That proposal is no longer available.';

            return;
        }

        $applied = $architect->apply($proposal, Auth::user());
        $this->analyzing = false;
        $this->notice = "Approved — {$applied} price(s) updated. Every price stays above its profit floor.";
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'Prices updated',
            message: "Claude's pricing is live — {$applied} plan(s) repriced, every sale still above its floor.");
    }

    public function reject(Architect $architect): void
    {
        $proposal = $this->pendingProposal();
        if ($proposal) {
            $architect->reject($proposal, Auth::user());
        }
        $this->analyzing = false;
        $this->notice = 'Proposal dismissed. Nothing was changed.';
    }

    private function pendingProposal(): ?PricingProposal
    {
        return PricingProposal::with('lines')->where('status', 'pending')->latest()->first();
    }

    public function render(Architect $architect)
    {
        $proposal = $this->pendingProposal();
        if ($proposal) {
            $this->analyzing = false; // it landed
        }

        return view('livewire.admin.pricing-architect', [
            'enabled' => $architect->enabled(),
            'monitor' => $architect->monitor(),
            'market' => $architect->marketBenchmark(),
            'proposal' => $proposal,
        ]);
    }
}
