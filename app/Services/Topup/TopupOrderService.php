<?php

namespace App\Services\Topup;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Jobs\Topup\SubmitTopupJob;
use App\Models\TopupOrder;
use App\Models\TopupOrderEvent;
use App\Models\TopupProduct;
use App\Models\TopupQuote;
use App\Models\User;
use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Credits\CreditService;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use App\Support\TopupFraud;
use App\Support\TopupSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The top-up money path and state machine. Discipline (blueprint money-safety rules 5-8): the order row and the supplier reference exist BEFORE
 * any supplier call; the wallet debit is atomic + idempotent; the supplier call is a queued job; a definitive failure refunds (once, keyed),
 * an uncertain answer is `pending` and requeried, never refunded and never re-sent to a different supplier on a guess.
 */
class TopupOrderService
{
    public function __construct(private WalletService $wallet, private ProviderRouter $router) {}

    /**
     * Pay for a quote from the wallet and queue delivery. $idempotencyKey makes a double-click return the SAME order.
     *
     * @throws TopupException
     */
    public function place(User $user, TopupQuote $quote, string $idempotencyKey): TopupOrder
    {
        if ($existing = TopupOrder::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }
        abort_unless($quote->user_id === $user->id, 403);
        if ($quote->expired()) {
            throw new TopupException('That price has expired. We have refreshed it: please confirm again.');
        }
        $product = $quote->product;
        if (! $product || ! $product->active) {
            throw new TopupException('That top-up is no longer available. Please pick another.');
        }
        $chain = $this->router->chain($product->country_iso, $product->type === 'data' ? 'data' : 'airtime', (float) $quote->price_usd);
        if (! in_array($product->provider_code, $chain, true)) {
            throw new TopupException('Top-ups to this country are paused right now. Please try again shortly.');
        }

        $price = (float) $quote->price_usd;
        // Profit is checked again at payment time, whatever the quote says: after any Credit discount we must still hold the minimum profit over cost.
        if (! TopupCredit::marginHolds($price, (float) $quote->cost_usd)) {
            throw new TopupException('That price is no longer available. We have refreshed it: please confirm again.');
        }
        TopupFraud::assert($user, $price, $quote->phone_e164);

        $uuid = (string) Str::uuid();
        $walletRef = 'topup:'.$uuid;
        $provider = TopupProviders::get($product->provider_code);

        // 1. Order row + reference FIRST (so a crash mid-flight can always be reconciled), still unpaid.
        $order = TopupOrder::create([
            'uuid' => $uuid, 'user_id' => $user->id, 'quote_id' => $quote->id, 'product_id' => $product->id, 'phone_e164' => $quote->phone_e164,
            'country_iso' => $product->country_iso, 'operator_name' => $product->operator_name, 'product_type' => $product->type === 'data' ? 'data' : 'airtime',
            'product_name' => $product->name, 'send_amount' => $quote->send_amount, 'send_currency' => $quote->send_currency,
            'provider_code' => $product->provider_code, 'provider_sku' => $product->provider_sku, 'reference' => $provider->newReference(),
            'status' => TopupOrder::CREATED, 'price_usd' => $price, 'list_price_usd' => $quote->list_price_usd, 'credit_usd' => $quote->credit_usd, 'credits' => $quote->credits, 'cost_usd' => $quote->cost_usd, 'wallet_ref' => $walletRef, 'idempotency_key' => $idempotencyKey,
        ]);
        $this->event($order, 'created', ['price_usd' => $price]);

        // 2. Atomic, idempotent debit.
        try {
            // Credits and wallet move together or not at all: if the wallet is short, the credit spend rolls back with it.
            DB::transaction(function () use ($user, $quote, $price, $walletRef, $product) {
                if ((float) $quote->credits > 0) {
                    app(CreditService::class)->spend($user, (float) $quote->credits, 'topup_discount', 'topup-credit:'.$walletRef, 'Data top-up discount: '.$product->operator_name);
                }
                $this->wallet->debit($user, $price, 'USD', ['reference' => $walletRef, 'description' => 'Top-up: '.$product->operator_name.' '.$product->name]);
            });
        } catch (InsufficientBalanceException) {
            $this->transition($order, TopupOrder::PAYMENT_FAILED, 'Wallet too low.');
            throw new TopupException('Your wallet is too low: add at least $'.number_format($price, 2).' and try again.');
        } catch (\App\Services\Credits\InsufficientCreditsException) {
            $this->transition($order, TopupOrder::PAYMENT_FAILED, 'Credits changed.');
            throw new TopupException('Your Credit balance changed, so the price was refreshed. Please confirm again.');
        } catch (Throwable $e) {
            $this->transition($order, TopupOrder::PAYMENT_FAILED, 'Debit error.');
            throw $e;
        }
        $this->transition($order, TopupOrder::FUNDS_HELD);
        Auditor::log('topup.placed', TopupOrder::class, $order->id, ['price' => $price]);

        // 3. Supplier call is a queued job.
        SubmitTopupJob::dispatch($order->id)->afterCommit();

        return $order->fresh();
    }

    /** Forward-only transition, row-locked so a webhook and a requery cannot both apply it. */
    public function transition(TopupOrder $order, string $to, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($order, $to, $reason) {
            $fresh = TopupOrder::lockForUpdate()->find($order->id);
            if (! $fresh || ! $fresh->canMoveTo($to)) {
                return false;
            }
            $fresh->status = $to;
            if ($reason !== null) {
                $fresh->failure_reason = substr($reason, 0, 250);
            }
            if ($to === TopupOrder::DELIVERED) {
                $fresh->delivered_at = now();
            }
            $fresh->save();
            $order->setRawAttributes($fresh->getAttributes(), true);
            $this->event($fresh, 'status:'.$to, ['reason' => $reason]);

            return true;
        });
    }

    public function event(TopupOrder $order, string $type, array $payload = []): void
    {
        TopupOrderEvent::create(['order_id' => $order->id, 'type' => $type, 'payload' => $payload ?: null, 'created_at' => now()]);
    }

    /** Build the supplier request for the order's CURRENT reference/sku. */
    public function request(TopupOrder $order): PurchaseRequest
    {
        $p = $order->product;
        $isFixed = $p ? (bool) $p->is_fixed : true;
        $meta = (array) ($p?->meta ?? []);
        if ($isFixed) {
            $meta['cost_native'] = (float) ($p?->cost_native ?? 0);
        }

        return new PurchaseRequest(
            $order->reference, $order->phone_e164, $order->country_iso, $order->product_type, $order->provider_sku, (float) $order->send_amount,
            $order->send_currency, $isFixed, $p?->operator_code, meta: $meta + ['use_local' => (bool) ($meta['use_local'] ?? false)],
        );
    }

    /** Apply a supplier answer. The single door for polling, webhooks and the submit job, so the paths cannot disagree. */
    public function apply(TopupOrder $order, PurchaseResult $r, string $source): void
    {
        $order->refresh();
        if ($order->isTerminal() || $order->status === TopupOrder::REFUNDED) {
            $this->event($order, 'ignored:'.$source, ['status' => $r->status]);

            return;
        }
        $this->event($order, 'supplier:'.$source, ['status' => $r->status, 'code' => $r->providerCode, 'message' => $r->message]);
        if ($r->providerTxnId && ! $order->provider_txn_id) {
            $order->update(['provider_txn_id' => $r->providerTxnId]);
        }
        if ($r->costCharged !== null) {
            $order->update(['cost_native' => $r->costCharged, 'cost_currency' => $r->costCurrency]);
        }

        match ($r->status) {
            PurchaseResult::DELIVERED => $this->markDelivered($order, $r),
            PurchaseResult::REVERSED => $this->failAndRefund($order, 'Reversed by the supplier.'),
            PurchaseResult::FAILED => $this->onFailed($order, $r),
            default => $this->markPending($order),
        };
    }

    public function markPending(TopupOrder $order): void
    {
        if ($order->status === TopupOrder::SUBMITTING) {
            $this->transition($order, TopupOrder::PENDING);
        }
    }

    public function markDelivered(TopupOrder $order, PurchaseResult $r): void
    {
        if ($order->status === TopupOrder::FUNDS_HELD) {
            $this->transition($order, TopupOrder::SUBMITTING);
        }
        if ($this->transition($order, TopupOrder::DELIVERED)) {
            if ($r->receiptText) {
                $order->update(['meta' => array_merge((array) $order->meta, ['receipt' => $r->receiptText])]);
            }
            Auditor::log('topup.delivered', TopupOrder::class, $order->id);
            try {
                if ($order->operator_name) {
                    app(NumberResolver::class)->learn($order->phone_e164, $order->country_iso, $order->operator_name);   // a delivered top-up proves the network
                }
            } catch (Throwable) {
            }
            if (TopupSettings::bool('notify_receipts')) {
                try {
                    Notify::event($order->user, 'topup.delivered', ['what' => trim($order->operator_name.' '.$order->product_name), 'to' => $order->phone_e164], 'topup.delivered:'.$order->id);
                } catch (Throwable) {
                }
            }
        }
    }

    /** A definitive failure: fail over to the next supplier with a NEW reference when allowed, else refund. */
    private function onFailed(TopupOrder $order, PurchaseResult $r): void
    {
        if ($r->definitive && TopupSettings::bool('failover') && $this->failover($order, $r)) {
            return;
        }
        $this->failAndRefund($order, $r->message ?: 'Supplier could not deliver.');
    }

    private function failover(TopupOrder $order, PurchaseResult $r): bool
    {
        $tried = array_values(array_unique(array_merge((array) ($order->meta['tried'] ?? []), [$order->provider_code])));
        $next = $this->router->nextProduct($order, $tried);
        if (! $next) {
            return false;
        }
        $newRef = TopupProviders::get($next->provider_code)->newReference();
        $old = ['provider' => $order->provider_code, 'reference' => $order->reference];
        $order->update([
            'provider_code' => $next->provider_code, 'provider_sku' => $next->provider_sku, 'product_id' => $next->id, 'reference' => $newRef,
            'provider_txn_id' => null, 'meta' => array_merge((array) $order->meta, ['tried' => $tried]),
        ]);
        // The failed attempt is over; the same order re-enters submitting on the new supplier.
        $order->status = TopupOrder::SUBMITTING;
        $order->save();
        $this->event($order, 'failover', $old + ['to' => $next->provider_code, 'new_reference' => $newRef]);
        SubmitTopupJob::dispatch($order->id, true)->afterCommit();

        return true;
    }

    /** Refund the wallet once (the refund is keyed on the wallet reference) and close the order. */
    public function failAndRefund(TopupOrder $order, string $reason): void
    {
        $order->refresh();
        if (in_array($order->status, [TopupOrder::DELIVERED, TopupOrder::REFUNDED, TopupOrder::PAYMENT_FAILED], true)) {
            return;
        }
        if ($order->status !== TopupOrder::FAILED) {
            $this->transition($order, TopupOrder::FAILED, $reason);
        }
        try {
            $this->wallet->refund($order->user, (float) $order->price_usd, 'USD', ['reference' => 'refund:'.$order->wallet_ref, 'description' => 'Top-up could not be delivered']);
            // Credits spent on the discount go back too (keyed on the order, so a retry can never give them twice).
            if ((float) $order->credits > 0) {
                try {
                    app(CreditService::class)->earn($order->user, (float) $order->credits, 'topup_refund', 'topup-credit-refund:'.$order->wallet_ref, 'Credits returned: top-up not delivered');
                } catch (Throwable) {
                    AlertAdminJob::dispatch(code: 'topup_credit_refund_failed', message: "Top-up {$order->uuid}: wallet refunded but the {$order->credits} credits were not returned.", context: ['order' => $order->id]);
                }
            }
            $this->transition($order, TopupOrder::REFUNDED, $reason);
            Auditor::log('topup.refunded', TopupOrder::class, $order->id, ['reason' => $reason]);
            try {
                Notify::event($order->user, 'topup.failed', ['what' => trim($order->operator_name.' '.$order->product_name), 'to' => $order->phone_e164], 'topup.failed:'.$order->id);
            } catch (Throwable) {
            }
        } catch (Throwable $e) {
            AlertAdminJob::dispatch(code: 'topup_refund_failed', message: "Top-up {$order->uuid} failed but the refund did not complete: {$e->getMessage()}", context: ['order' => $order->id]);
        }
    }

    /** Pending past the escalation window goes to the support queue; it is NEVER auto-refunded until the supplier says failed/reversed. */
    public function escalateStale(): int
    {
        $n = 0;
        $cut = now()->subMinutes(TopupSettings::int('escalate_after_minutes'));
        foreach (TopupOrder::whereIn('status', [TopupOrder::PENDING, TopupOrder::SUBMITTING])->where('escalated', false)->where('updated_at', '<', $cut)->limit(200)->get() as $o) {
            $o->update(['escalated' => true]);
            $this->event($o, 'escalated', []);
            AlertAdminJob::dispatch(code: 'topup_pending_stale', message: "Top-up {$o->uuid} has been pending over ".TopupSettings::int('escalate_after_minutes').' minutes: check the supplier record before refunding.', context: ['order' => $o->id, 'provider' => $o->provider_code]);
            $n++;
        }

        return $n;
    }

    /** Admin: refund an order that is not delivered. Requires the supplier to have been asked first for pending ones. */
    public function adminRefund(TopupOrder $order, string $reason): void
    {
        abort_if(in_array($order->status, [TopupOrder::DELIVERED, TopupOrder::REFUNDED, TopupOrder::PAYMENT_FAILED], true), 422);
        // A pending order must be forced through failed -> refunded; allow SUBMITTING/PENDING/FUNDS_HELD -> FAILED.
        $this->failAndRefund($order, 'Admin refund: '.$reason);
        Auditor::log('topup.admin_refund', TopupOrder::class, $order->id, ['reason' => $reason]);
    }
}
