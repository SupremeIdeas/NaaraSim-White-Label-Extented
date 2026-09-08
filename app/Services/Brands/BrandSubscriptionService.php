<?php

namespace App\Services\Brands;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Self-service brand listing lifecycle (BUILD-9 §5): subscribe (plan → first-
 * month charge → brand row + subscription), activate once setup is complete,
 * cancel, and resubscribe onto the SAME brand row. All money moves through
 * WalletService with the same discipline as everywhere else; the first-month
 * charge and the brand row are created in one transaction, so a failed charge
 * never leaves a listing behind (and vice-versa).
 */
class BrandSubscriptionService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /**
     * Start (or restart) a subscription. Charges the first month up-front, then
     * creates the brand (pending_setup) or reactivates the owner's existing brand
     * (skipping onboarding if setup was already complete). Throws
     * InsufficientBalanceException if the wallet can't cover the first month.
     */
    public function subscribe(User $owner, BrandSubscriptionPlan $plan): BrandSubscription
    {
        return DB::transaction(function () use ($owner, $plan) {
            $brand = BrandPartner::where('owner_user_id', $owner->id)->first();
            $setupComplete = $brand !== null && $this->setupComplete($brand);

            // Charge first month BEFORE creating anything — a failed debit rolls
            // the whole transaction back, so no listing exists without payment.
            $this->wallet->debit($owner, (float) $plan->price_usd_per_month, 'USD', [
                'reference' => 'brand-sub-start:'.$owner->id.':'.now()->timestamp,
                'description' => 'Brand listing — '.$plan->name.' (first month)',
            ]);

            if ($brand === null) {
                $brand = BrandPartner::create([
                    'owner_user_id' => $owner->id,
                    'brand_name' => $owner->name ?: 'My brand',
                    'listing_status' => BrandPartner::STATUS_PENDING,
                    'is_featured' => false,
                    'background_color' => '#0A6E6E',
                    'current_plan_id' => $plan->id,
                    'is_active' => true,
                ]);
            } else {
                $brand->forceFill([
                    'current_plan_id' => $plan->id,
                    // Returning brand with completed setup skips straight past onboarding.
                    'listing_status' => $setupComplete ? BrandPartner::STATUS_ACTIVE : BrandPartner::STATUS_PENDING,
                ])->save();
            }

            $sub = BrandSubscription::create([
                'brand_partner_id' => $brand->id,
                'plan_id' => $plan->id,
                'status' => BrandSubscription::ACTIVE,
                'started_at' => now(),
                'next_billing_at' => now()->addMonthNoOverflow(),
                'last_charged_at' => now(),
                'grace_reminders_sent' => 0,
            ]);

            Auditor::log('brand.subscribed', 'BrandPartner', $brand->id, ['plan' => $plan->name, 'owner' => $owner->id]);

            return $sub;
        });
    }

    /** Flip a pending listing live once every required field is complete (§5.1.3). */
    public function activateIfComplete(BrandPartner $brand): bool
    {
        if ($brand->listing_status !== BrandPartner::STATUS_PENDING || ! $this->setupComplete($brand)) {
            return false;
        }
        $sub = $brand->subscription;
        if (! $sub || $sub->status !== BrandSubscription::ACTIVE) {
            return false;
        }
        $brand->forceFill(['listing_status' => BrandPartner::STATUS_ACTIVE])->save();
        Auditor::log('brand.activated', 'BrandPartner', $brand->id);

        return true;
    }

    /** Business-initiated cancellation (§5.3) — distinct from a billing pause. */
    public function cancel(BrandPartner $brand): void
    {
        DB::transaction(function () use ($brand) {
            if ($sub = $brand->subscription) {
                $sub->forceFill(['status' => BrandSubscription::CANCELLED, 'cancelled_at' => now()])->save();
            }
            $brand->forceFill(['listing_status' => BrandPartner::STATUS_DISABLED])->save();
            Auditor::log('brand.cancelled', 'BrandPartner', $brand->id);
        });
    }

    /** Every required onboarding field present (§5.1.2). */
    public function setupComplete(BrandPartner $brand): bool
    {
        return filled($brand->brand_name)
            && filled($brand->category)
            && (filled($brand->hero_image_path) || filled($brand->fallback_image))
            && $brand->handles()->exists();
    }
}
