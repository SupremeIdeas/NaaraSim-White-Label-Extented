<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Services\Brands\BrandSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Get Listed" landing (BUILD-9 §9). Explains the value honestly, shows a live
 * plan-comparison table, and starts the subscription immediately on plan pick —
 * no "contact us" step. Choosing a plan charges the first month and drops the
 * business straight into guided setup.
 */
#[Layout('components.layouts.customer')]
class GetListed extends Component
{
    public ?string $error = null;

    public function booted(): void
    {
        // Batch 8: the "get listed" surface is part of the Brand Hunt feature —
        // locked with it. Inert on the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT), 404);
    }

    public function choose(int $planId, BrandSubscriptionService $subs)
    {
        $plan = BrandSubscriptionPlan::active()->find($planId);
        if (! $plan) {
            $this->error = 'That plan is not available.';

            return null;
        }

        try {
            $subs->subscribe(Auth::user(), $plan);
        } catch (InsufficientBalanceException) {
            $this->error = 'Your wallet is short for the first month ($'.number_format((float) $plan->price_usd_per_month, 2).'). Top up your wallet, then choose a plan.';

            return null;
        }

        return $this->redirect(route('brand.manage'), navigate: true);
    }

    public function render()
    {
        return view('livewire.get-listed', [
            'plans' => BrandSubscriptionPlan::active()->ordered()->get(),
            'alreadyListed' => Auth::check() && BrandPartner::where('owner_user_id', Auth::id())->exists(),
        ]);
    }
}
