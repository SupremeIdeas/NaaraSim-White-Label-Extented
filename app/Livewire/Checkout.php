<?php

namespace App\Livewire;

use App\Exceptions\EsimProviderException;
use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Jobs\EvaluateJourneyGoalsJob;
use App\Jobs\ProcessReferralRewardJob;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Notifications\OrderPlacedNotification;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\eSIM\ProviderRouter;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Pricing\CouponEngine;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use App\Services\WhatsApp\WhatsAppAutopilot;
use App\Support\CreditSettings;
use App\Support\Mailer;
use App\Support\MerchantBranding;
use App\Support\Niche\DeviceCompat;
use App\Support\Niche\LpaActivation;
use App\Support\PendingCoupon;
use App\Support\PurchaseReceipt;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * eSIM checkout (blueprint Sections 13, 14). Debits the wallet at the display
 * retail, then fulfils via the profit-aware ProviderRouter. Money-safety:
 *   - The user is only ever shown final_retail_usd (never cost).
 *   - ProviderRouter refunds itself if no provider can fulfil profitably.
 *   - Orphan-charge guard: if the order succeeds but persisting it fails, the
 *     wallet is refunded and admins alerted.
 */
#[Layout('components.layouts.customer')]
class Checkout extends Component
{
    public EsimPlan $plan;

    public bool $done = false;

    public ?string $message = null;

    public ?string $error = null;

    /** Device-compatibility gate (blueprint Section 32) — checked BEFORE buying. */
    public string $device = '';

    public ?bool $deviceResult = null;   // true=supported, false=not, null=unknown

    public bool $deviceConfirmed = false; // user confirmed their device supports eSIM

    /** Coupon (Module 31). Only the discounted RETAIL is ever shown — never cost. */
    public string $coupon = '';

    public ?float $couponPrice = null;   // discounted retail (USD)

    public ?float $couponSaved = null;   // savings vs list retail (USD)

    public ?string $couponError = null;

    /** NaaraCredits redemption (loyalty). Margin-capped server-side. */
    public bool $useCredits = false;

    public function mount(EsimPlan $plan): void
    {
        $this->plan = $plan;
        // Pre-fill a coupon the user claimed from an offer (one-tap path). It is
        // still validated + MarginGuard-clamped when applied/charged.
        $this->coupon = PendingCoupon::peek() ?? '';
    }

    /** The retail the wallet is charged after a coupon (never below the floor). */
    private function effectiveRetail(CouponEngine $coupons): float
    {
        $retail = (float) $this->plan->final_retail_usd;
        if (trim($this->coupon) !== '' && ($model = $coupons->usable($this->coupon, auth()->user(), 'esim'))) {
            $retail = $coupons->price($model, $retail, (float) $this->plan->cost_price_usd, 'esim')['price'];
        }

        return $retail;
    }

    public function applyCoupon(CouponEngine $coupons): void
    {
        $this->couponError = null;
        $this->couponPrice = $this->couponSaved = null;

        $model = $coupons->usable($this->coupon, auth()->user(), 'esim');
        if (! $model) {
            $this->couponError = 'That coupon code is not valid for this purchase.';

            return;
        }

        $quote = $coupons->price($model, (float) $this->plan->final_retail_usd, (float) $this->plan->cost_price_usd, 'esim');
        $this->couponPrice = $quote['price'];
        $this->couponSaved = $quote['saved'];

        // Mutual exclusivity (blueprint §4, client UX): applying a coupon clears
        // any credit selection so the customer is never mid-way into both. The
        // server guard in purchase() is the real enforcement.
        $this->useCredits = false;
    }

    public function removeCoupon(): void
    {
        $this->reset('coupon', 'couponPrice', 'couponSaved', 'couponError');
    }

    /** Turning NaaraCredits on clears any coupon (the two are mutually exclusive). */
    public function updatedUseCredits(bool $value): void
    {
        if ($value) {
            $this->reset('coupon', 'couponPrice', 'couponSaved', 'couponError');
        }
    }

    public function checkDevice(): void
    {
        $this->deviceResult = DeviceCompat::check($this->device);
        // A known-supported device auto-confirms; unknown/unsupported needs the
        // explicit checkbox so the user takes responsibility.
        if ($this->deviceResult === true) {
            $this->deviceConfirmed = true;
        }
        if ($this->deviceResult === false) {
            $this->deviceConfirmed = false;
        }
    }

    public function purchase(WalletService $wallet, ProviderRouter $router, CouponEngine $coupons, CreditService $credits, PricingEngine $pricing, MerchantEarningsService $earnings): void
    {
        $user = auth()->user();

        // Device-compatibility gate: never sell an eSIM to a phone that can't use
        // it (blueprint Section 32 — the check runs BEFORE purchase).
        if (! $this->deviceConfirmed) {
            $this->error = 'Please confirm your device supports eSIM before buying.';

            return;
        }

        // Order rate limit: 10/min (blueprint Section 19.2).
        $key = 'orders:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many orders in a short time. Please wait a minute and try again.';

            return;
        }
        RateLimiter::hit($key, 60);

        // Merchant lane (ROADMAP §Layer 3.2/3.4): a customer who joined through a
        // reseller pays the merchant price (retail + admin-set reseller margin);
        // the M−R upcharge is later accrued to that merchant. Everyone else pays
        // plain retail. plainRetail is kept as the accrual floor.
        $merchant = MerchantBranding::forCustomer($user);
        if ($merchant !== null) {
            // Compute the plain retail through the engine (never the possibly-
            // stale generated column) so the accrual floor is exact.
            $plainRetail = $pricing->calculateRetail($this->plan, log: false);
            // §3.3: the customer's locked margin-at-signup (if any) wins over the
            // merchant's current margin — a merchant-referred user's price never
            // changes when the merchant's margin later does.
            $retail = $pricing->merchantEsimPrice($this->plan, $merchant, true,
                $user->merchant_margin_pct !== null ? (float) $user->merchant_margin_pct : null);
        } else {
            $plainRetail = (float) $this->plan->final_retail_usd;
            $retail = $plainRetail;
        }

        // Margin-safe discount floor (blueprint §2/§3): compute the admin's and
        // (for a merchant sale) the merchant's margin ONCE, from the pre-discount
        // prices, and thread the SAME figures into both the coupon and credit
        // engines — so the combined floor is identical and neither can zero the
        // merchant's earning.
        $cost = (float) $this->plan->cost_price_usd;
        $adminMargin = $plainRetail - $cost;
        $merchantMargin = $merchant !== null ? max(0.0, $retail - $plainRetail) : null;

        // Mutual exclusivity (blueprint §4): a customer picks ONE discount
        // mechanism per order. Enforced server-side before any pricing math.
        if (trim($this->coupon) !== '' && $this->useCredits) {
            $this->error = 'Choose either a coupon or NaaraCredits for this order — not both.';

            return;
        }

        // Coupon is re-resolved server-side at purchase time — the preview shown
        // by applyCoupon() is never trusted. CouponEngine clamps the discount to
        // cost + minimum profit, so no code can ever charge below wholesale.
        $couponModel = null;
        $couponClamped = false;
        if (trim($this->coupon) !== '') {
            $couponModel = $coupons->usable($this->coupon, $user, 'esim');
            if (! $couponModel) {
                $this->error = 'That coupon code is no longer valid. Remove it or try another.';

                return;
            }
            $quote = $coupons->price($couponModel, $retail, $cost, 'esim', $adminMargin, $merchantMargin);
            $listRetail = $retail;
            $retail = $quote['price'];
            $couponClamped = $quote['clamped'];
        }

        $ref = "esim-checkout:{$this->plan->id}:{$user->id}:".now()->timestamp;

        // NaaraCredits redemption (loyalty). Re-quoted server-side and MARGIN-CAPPED
        // by CreditService so credits can never push the money charged below cost +
        // minimum profit. Credits are spent BEFORE the wallet debit and refunded on
        // any downstream failure, so the user is never left short.
        $redeemUsd = 0.0;
        $creditsSpent = 0.0;
        if ($this->useCredits) {
            $quote = $credits->quoteRedemption($user, $retail, $cost, $adminMargin, $merchantMargin);
            $redeemUsd = $quote['usd'];
            $creditsSpent = $quote['credits'];
        }
        $walletCharge = round($retail - $redeemUsd, 4); // always >= cost + min profit > 0

        if ($creditsSpent > 0) {
            try {
                $credits->spend($user, $creditsSpent, 'redeem', "credit-redeem:{$ref}", "Redeemed on eSIM: {$this->plan->name}");
            } catch (InsufficientCreditsException $e) {
                $this->error = 'Your NaaraCredits balance changed — please review and try again.';

                return;
            }
        }

        try {
            $debit = $wallet->debit($user, $walletCharge, 'USD', [
                'reference' => $ref,
                'description' => "eSIM: {$this->plan->name}",
            ]);
        } catch (InsufficientBalanceException $e) {
            $this->refundCredits($credits, $user, $creditsSpent, $ref);
            $this->error = 'Your wallet balance is too low. Please top up and try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Payment failed',
                message: 'Your wallet balance is too low — you were not charged. Top up and try again.',
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

            return;
        }

        // Double-submit guard (money-safety rule 7): the wallet debit is
        // idempotent on $ref, but the provider order is not. A same-second
        // re-submit returns the EXISTING debit (wasRecentlyCreated === false) —
        // the order was already placed for this charge, so ordering again would
        // buy a second eSIM at our cost against a single charge. Stop here.
        if (! $debit->wasRecentlyCreated) {
            $this->done = true;
            $this->message = 'This order is already being processed — it will appear on your dashboard shortly.';

            return;
        }

        try {
            $result = $router->orderPlan((string) $this->plan->id, $user, 'USD', $walletCharge);
        } catch (EsimProviderException $e) {
            // ProviderRouter already refunded the wallet (the exact amount charged);
            // return the redeemed credits too.
            $this->refundCredits($credits, $user, $creditsSpent, $ref);
            $this->error = 'No provider could fulfil this plan right now — your wallet was refunded.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Order could not be completed',
                message: 'No provider could fulfil this plan right now. Your wallet was refunded in full — you were not charged.');

            return;
        }

        try {
            EsimOrder::create([
                'user_id' => $user->id,
                'plan_id' => $this->plan->id,
                'provider' => $result->provider,
                'provider_order_ref' => data_get($result->payload, 'orderReference')
                    ?? data_get($result->payload, 'id'),
                'iccid' => data_get($result->payload, 'iccid')
                    ?? data_get($result->payload, 'esims.0.iccid'),
                'bundle_name' => $result->providerPlanId,
                'qr_code_url' => data_get($result->payload, 'qr_code')
                    ?? data_get($result->payload, 'qrCodeUrl'),
                'lpa_string' => LpaActivation::fromPayload($result->payload),
                'status' => 'processing',
                'price_charged' => $walletCharge,   // real money collected (credits shown separately)
                'wholesale_cost' => $result->cost,
                'currency' => 'USD',
            ]);
            // Itemised receipt (BUILD-7 §1) — best-effort, never blocks the order.
            PurchaseReceipt::send($user, $this->plan->name, (float) $walletCharge, $ref);
        } catch (Throwable $e) {
            // Orphan-charge guard: charged + provider ordered, but we failed to
            // persist. Refund the money AND the redeemed credits, then alert.
            $wallet->refund($user, $walletCharge, 'USD', [
                'reference' => "refund:{$ref}",
                'description' => 'eSIM order could not be saved',
            ]);
            $this->refundCredits($credits, $user, $creditsSpent, $ref);
            AlertAdminJob::dispatch(
                code: 'esim_order_save_failed',
                message: "eSIM order for user {$user->id} succeeded at {$result->provider} but failed to persist; wallet refunded.",
                context: ['user_id' => $user->id, 'plan_id' => $this->plan->id],
            );
            $this->error = 'Something went wrong finalising your order — your wallet was refunded.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Order could not be saved',
                message: 'Something went wrong finalising your order. Your wallet was refunded in full — you were not charged.');

            return;
        }

        // Redemption is recorded only after the order persisted, so an
        // abandoned/refunded purchase never burns the user's coupon use.
        if ($couponModel) {
            $coupons->redeem($couponModel, $user, 'esim', $ref, $listRetail, $retail, $couponClamped);
        }

        // Merchant earnings (ROADMAP §Layer 3.4): accrue the cash collected ABOVE
        // plain retail to the customer's merchant — only what was actually paid,
        // so the admin's own margin is never touched. Idempotent on $ref.
        if ($merchant !== null) {
            $earnings->accrue($merchant, $user, 'esim', $plainRetail, $walletCharge, 'earn:'.$ref);
        }

        // Referral share (BUILD-22 §1): if this buyer was referred, book their
        // referrer a share of Naara's OWN margin on this order — once ever, off
        // the money path. profit is internal-only; only the payable share is
        // persisted. Idempotent via Referral.rewarded + the ledger reference.
        ProcessReferralRewardJob::dispatch($user->id, 'esim', $result->profit);

        // Order-confirmation email (best-effort; never blocks the money path) —
        // shows the real money charged.
        Mailer::notify($user, new OrderPlacedNotification('esim', $this->plan->name, $walletCharge, 'USD'));

        // WhatsApp Autopilot (§7): mirror the confirmation to WhatsApp for opted-in
        // users. Fully gated + best-effort — queues a template or silently no-ops.
        app(WhatsAppAutopilot::class)
            ->notify($user, 'esim_delivered', [$user->name ?: 'there', $this->plan->name]);

        // First-purchase NaaraCredits bonus (loyalty; idempotent, best-effort).
        $credits->grantOnce(
            $user,
            (float) CreditSettings::get('first_purchase_bonus', 0),
            'first_purchase',
            'First purchase bonus',
        );

        // My Journey goals (loyalty expansion) — queued so a purchase-count or
        // countries-reached goal can unlock the instant this order lands.
        EvaluateJourneyGoalsJob::dispatch($user->id);

        $this->done = true;
        $this->message = $creditsSpent > 0
            ? 'Success! You used '.number_format($creditsSpent, 0).' NaaraCredits. Your eSIM is being provisioned and will appear on your dashboard shortly.'
            : 'Success! Your eSIM is being provisioned and will appear on your dashboard shortly.';

        // Hero toast — dispatched only now, after the order committed (server-anchored).
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'Order confirmed',
            message: $creditsSpent > 0
                ? number_format($creditsSpent, 0).' NaaraCredits applied. Your eSIM is being provisioned.'
                : 'Your eSIM is being provisioned and will appear on your dashboard shortly.',
            cta: ['label' => 'View my eSIM', 'href' => route('dashboard')]);
    }

    /** Return redeemed credits to the user after a failed/aborted purchase. */
    private function refundCredits(CreditService $credits, $user, float $amount, string $ref): void
    {
        if ($amount > 0) {
            try {
                $credits->earn($user, $amount, 'redeem', "credit-refund:{$ref}", 'Credits returned — order not completed');
            } catch (Throwable $e) {
                // best-effort; the wallet money refund is the primary guarantee
            }
        }
    }

    public function render(CreditService $credits, CouponEngine $coupons)
    {
        // Live credit-redemption quote for the UI (margin-capped, never cost).
        $creditBalance = $credits->balance(auth()->user());
        $creditQuote = ['usd' => 0.0, 'credits' => 0.0];
        if (CreditSettings::enabled() && $creditBalance > 0) {
            $creditQuote = $credits->quoteRedemption(
                auth()->user(),
                $this->effectiveRetail($coupons),
                (float) $this->plan->cost_price_usd,
            );
        }

        return view('livewire.checkout', [
            'creditsEnabled' => CreditSettings::enabled(),
            'creditBalance' => $creditBalance,
            'creditQuote' => $creditQuote,
        ]);
    }
}
