<?php

namespace App\Services\GiftCards;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\GiftCardFraud;
use App\Support\GiftCardPricing;
use Throwable;

/**
 * The Naara Gift purchase money path (Phase 3). Same discipline as eSIM/merchant
 * checkout: retail quoted through PricingEngine, fraud gate, ATOMIC wallet debit
 * (idempotent on the transaction ref), the order row created BEFORE the provider
 * call (no orphan charge), refund-on-provider-failure, and a review-queue hold
 * for high-value orders. Provider identity + cost never leak to the buyer.
 */
class GiftCardOrderService
{
    /** @var array<string, class-string<GiftCardProviderInterface>> */
    private const PROVIDERS = [
        'reloadly' => ReloadlyGiftCardService::class,
        'zendit' => ZenditVoucherService::class,
        'bitrefill' => BitrefillService::class,
        'tillo' => TilloService::class,
    ];

    public function __construct(
        private WalletService $wallet,
        private GiftCardPricing $pricing,
    ) {}

    private function provider(string $key): GiftCardProviderInterface
    {
        if (app()->bound('giftcard.'.$key)) {
            return app('giftcard.'.$key);
        }

        return app(self::PROVIDERS[$key] ?? throw new GiftCardException('Unknown provider.'));
    }

    /**
     * Buy a gift card for $amount (face value) with the required $fields.
     *
     * @param  array<string, string>  $fields
     *
     * @throws GiftCardException
     */
    public function purchase(User $user, GiftCardProduct $product, float $amount, array $fields): GiftCardOrder
    {
        $amount = round($amount, 2);
        $this->assertAmount($product, $amount);
        $this->assertFields($product, $fields);

        // Authoritative retail quote (this one logs).
        $retail = round($this->pricing->retail($product, $amount, log: true), 4);

        // Fraud gate (velocity + cooling-off) BEFORE any money moves.
        GiftCardFraud::assert($user, $retail);

        // Time-bucketed reference (money-safety rule 7): a same-second re-submit
        // of the SAME product+amount shares this reference, so the idempotent
        // debit dedupes it and we return the existing order instead of charging
        // and ordering twice (consistent with the eSIM/merchant checkout).
        $ref = 'giftcard:'.$user->id.':'.$product->id.':'.(int) round($amount * 100).':'.now()->timestamp;

        // Atomic, idempotent debit.
        try {
            $debit = $this->wallet->debit($user, $retail, 'USD', [
                'reference' => $ref,
                'description' => "Gift card: {$product->brand_name} {$product->currency} ".number_format($amount, 2),
            ]);
        } catch (InsufficientBalanceException) {
            throw new GiftCardException('Your wallet is too low — top up at least $'.number_format($retail, 2).'.');
        }
        if (! $debit->wasRecentlyCreated) {
            // This charge was already placed — return the existing order (no
            // second debit, no second provider order). Idempotent success.
            $existing = GiftCardOrder::where('transaction_ref', $ref)->first();
            if ($existing) {
                return $existing;
            }
            // Charged but the order row never landed (rare partial failure): fall
            // through and build it against the SAME already-charged reference.
        }

        // Record the order BEFORE calling the provider (no orphan charge).
        $order = GiftCardOrder::create([
            'user_id' => $user->id,
            'gift_card_product_id' => $product->id,
            'provider' => $product->provider,
            'provider_product_id' => $product->provider_product_id,
            'brand_name' => $product->brand_name,
            'face_value' => $amount,
            'currency' => $product->currency ?: 'USD',
            'price_charged' => $retail,
            'status' => GiftCardOrder::STATUS_PROCESSING,
            'transaction_ref' => $ref,
            'fields' => $fields,
        ]);

        // High-value orders hold in review (funds already committed) — fulfilled
        // on admin approval, refunded on rejection.
        if (GiftCardFraud::needsReview($retail)) {
            $order->update(['status' => GiftCardOrder::STATUS_REVIEW, 'review_reason' => 'Above the review threshold.']);
            Auditor::log('giftcard.review_held', GiftCardOrder::class, $order->id, ['amount' => $retail]);

            return $order;
        }

        return $this->fulfil($order, $product);
    }

    /** Call the provider, store the receipt, refund on failure. */
    private function fulfil(GiftCardOrder $order, GiftCardProduct $product): GiftCardOrder
    {
        try {
            $result = $this->provider($product->provider)->order(
                $product->provider_product_id,
                (float) $order->face_value,
                $order->currency,
                (array) $order->fields,
                $order->transaction_ref,
            );
        } catch (GiftCardProviderException $e) {
            $this->failAndRefund($order);

            throw new GiftCardException('That gift card could not be delivered right now — your wallet was refunded.');
        }

        // A provider may answer 200 with a terminal 'failed' status instead of
        // throwing — treat that exactly like a thrown failure (refund, never
        // leave the buyer charged for an undelivered card).
        if (($result['status'] ?? null) === 'failed') {
            $this->failAndRefund($order);

            throw new GiftCardException('That gift card could not be delivered right now — your wallet was refunded.');
        }

        try {
            $order->update([
                'provider_tx_id' => $result['provider_tx_id'] ?? null,
                'receipt' => $result['receipt'] ?? [],
                'status' => $result['status'] === 'delivered' ? GiftCardOrder::STATUS_DELIVERED : GiftCardOrder::STATUS_PROCESSING,
            ]);
        } catch (Throwable $e) {
            // The card was ordered but we couldn't record the receipt — never
            // refund a delivered card; alert an admin to reconcile by hand.
            AlertAdminJob::dispatch(
                code: 'giftcard_receipt_save_failed',
                message: "Gift card order {$order->id} delivered but the receipt could not be saved: {$e->getMessage()}",
                context: ['order_id' => $order->id, 'ref' => $order->transaction_ref],
            );
        }

        Auditor::log('giftcard.purchased', GiftCardOrder::class, $order->id, ['status' => $order->status]);

        return $order->fresh();
    }

    /**
     * Refund a charged order and mark it failed. Idempotent: the refund is keyed
     * on `refund:{ref}`, so a webhook and the inline path can both call it without
     * double-crediting, and a card that is already terminal is left untouched.
     */
    public function failAndRefund(GiftCardOrder $order): void
    {
        if (in_array($order->status, [GiftCardOrder::STATUS_DELIVERED, GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED], true)) {
            return;
        }
        $this->wallet->refund($order->user, (float) $order->price_charged, 'USD', [
            'reference' => 'refund:'.$order->transaction_ref,
            'description' => 'Gift card could not be delivered',
        ]);
        $order->update(['status' => GiftCardOrder::STATUS_FAILED]);
        Auditor::log('giftcard.failed_refunded', GiftCardOrder::class, $order->id);
    }

    /** Admin approves a held order → fulfil it. */
    public function approveReview(GiftCardOrder $order): GiftCardOrder
    {
        abort_unless($order->status === GiftCardOrder::STATUS_REVIEW, 422);
        $product = $order->product ?? GiftCardProduct::where('provider', $order->provider)
            ->where('provider_product_id', $order->provider_product_id)->firstOrFail();
        $order->update(['status' => GiftCardOrder::STATUS_PROCESSING]);

        return $this->fulfil($order, $product);
    }

    /** Admin rejects a held order → refund. */
    public function rejectReview(GiftCardOrder $order, ?string $reason = null): void
    {
        abort_unless($order->status === GiftCardOrder::STATUS_REVIEW, 422);
        $this->wallet->refund($order->user, (float) $order->price_charged, 'USD', [
            'reference' => 'refund:'.$order->transaction_ref,
            'description' => 'Gift card order declined in review',
        ]);
        $order->update(['status' => GiftCardOrder::STATUS_REFUNDED, 'review_reason' => $reason ?: $order->review_reason]);
        Auditor::log('giftcard.review_rejected', GiftCardOrder::class, $order->id);
    }

    private function assertAmount(GiftCardProduct $product, float $amount): void
    {
        if ($amount <= 0) {
            throw new GiftCardException('Choose an amount.');
        }
        if ($product->isRange()) {
            if ($amount < (float) $product->min_amount || $amount > (float) $product->max_amount) {
                throw new GiftCardException('Enter an amount between '.$product->min_amount.' and '.$product->max_amount.'.');
            }
            // Bitrefill range products carry a real step (increment) constraint
            // the provider enforces on its side — checked here too so the
            // buyer gets a clear message instead of a provider-side order
            // failure after the wallet's already been debited.
            $step = (float) (((array) $product->cost_meta)['range_step'] ?? 0);
            if ($step > 0) {
                $steps = round(($amount - (float) $product->min_amount) / $step);
                if (abs((float) $product->min_amount + $steps * $step - $amount) > 0.01) {
                    throw new GiftCardException('Amount must be in increments of '.$step.' from '.$product->min_amount.'.');
                }
            }
        } elseif (! collect((array) $product->fixed_denominations)->map(fn ($v) => (float) $v)->contains($amount)) {
            throw new GiftCardException('Choose one of the available amounts.');
        }
    }

    private function assertFields(GiftCardProduct $product, array $fields): void
    {
        foreach ((array) $product->required_fields as $f) {
            $key = $f['key'] ?? null;
            if ($key && ($f['required'] ?? true) && trim((string) ($fields[$key] ?? '')) === '') {
                throw new GiftCardException('Please fill in: '.($f['label'] ?? $key).'.');
            }
        }
    }
}
