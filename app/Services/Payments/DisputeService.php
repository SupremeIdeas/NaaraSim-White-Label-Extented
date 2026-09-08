<?php

namespace App\Services\Payments;

use App\Jobs\AlertAdminJob;
use App\Models\PaymentCharge;
use App\Models\PaymentDispute;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;

/**
 * Card dispute / chargeback handling (BUILD-2 §7.2). When a gateway logs a
 * dispute we FREEZE the disputed amount from the user's wallet (a reserve
 * earmark, so it can neither be spent nor withdrawn while contested) and alert
 * an admin. On resolution we release the earmark (won) or release then debit it
 * (lost — the processor pulls the money back).
 *
 * Money-safety: idempotent per (gateway, provider_dispute_id); the freeze is a
 * WalletService reserve, the claw-back a WalletService debit — never a direct
 * balance write. The USD reserve is the only earmark the wallet has, so non-USD
 * disputes (e.g. NGN) are recorded and alerted for manual handling rather than
 * silently pretending to freeze.
 */
class DisputeService
{
    public function __construct(private WalletService $wallet) {}

    public function handle(DisputeEvent $event): ?PaymentDispute
    {
        if ($event->providerDisputeId === '') {
            AlertAdminJob::dispatch(
                code: 'dispute_without_id',
                message: "A {$event->gateway} dispute webhook arrived with no dispute id — handle it manually on the dashboard.",
                context: ['gateway' => $event->gateway, 'reference' => $event->reference],
            );

            return null;
        }

        return match ($event->status) {
            DisputeEvent::OPEN => $this->open($event),
            DisputeEvent::WON, DisputeEvent::LOST => $this->resolve($event),
            default => null,
        };
    }

    private function open(DisputeEvent $event): PaymentDispute
    {
        $existing = PaymentDispute::where('gateway', $event->gateway)
            ->where('provider_dispute_id', $event->providerDisputeId)->first();
        if ($existing) {
            return $existing; // idempotent
        }

        $reference = $this->resolveReference($event);
        $user = $this->userFromReference($event->gateway, $reference);

        // Freeze what we can. Only USD has a wallet earmark; freeze up to the
        // user's spendable balance (money already spent can't be frozen — that
        // gap is the exposure the alert flags).
        $frozen = 0.0;
        if ($user && $event->currency === 'USD' && $event->amount > 0) {
            $freezable = min($event->amount, $this->wallet->spendableUsd($user));
            if ($freezable > 0) {
                $this->wallet->reserve($user, $freezable);
                $frozen = $freezable;
            }
        }

        $dispute = PaymentDispute::create([
            'user_id' => $user?->id,
            'gateway' => $event->gateway,
            'provider_dispute_id' => $event->providerDisputeId,
            'reference' => $reference,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'status' => PaymentDispute::STATUS_OPEN,
            'frozen_amount' => $frozen,
        ]);

        $short = $event->amount > 0 && $frozen + 1e-6 < $event->amount;
        AlertAdminJob::dispatch(
            code: 'payment_dispute_opened',
            message: ucfirst($event->gateway)." dispute {$event->providerDisputeId} for "
                .number_format($event->amount, 2).' '.$event->currency
                .($user ? " (user {$user->id})" : ' (user unresolved)')
                .($frozen > 0 ? ' — froze '.number_format($frozen, 2).' from the wallet.' : '')
                .($short ? ' Could NOT fully freeze it (funds spent / non-USD) — respond before the deadline.' : ''),
            context: ['gateway' => $event->gateway, 'dispute_id' => $event->providerDisputeId, 'reference' => $event->reference, 'amount' => $event->amount, 'currency' => $event->currency, 'frozen' => $frozen],
        );
        Auditor::log('payment.dispute_opened', PaymentDispute::class, $dispute->id, ['gateway' => $event->gateway, 'frozen' => $frozen]);

        return $dispute;
    }

    private function resolve(DisputeEvent $event): ?PaymentDispute
    {
        $dispute = PaymentDispute::where('gateway', $event->gateway)
            ->where('provider_dispute_id', $event->providerDisputeId)->first();
        if (! $dispute || ! $dispute->isOpen()) {
            return $dispute; // unknown or already resolved — idempotent
        }

        $user = $dispute->user;
        $frozen = round((float) $dispute->frozen_amount, 4);

        if ($user && $frozen > 0) {
            // Unfreeze first (release the earmark back to spendable)...
            $this->wallet->release($user, $frozen);

            // ...then, if we lost, pull the money back (the processor took it).
            if ($event->status === DisputeEvent::LOST) {
                try {
                    $this->wallet->debit($user, $frozen, $dispute->currency, [
                        'reference' => "chargeback:{$event->gateway}:{$event->providerDisputeId}",
                        'description' => 'Chargeback ('.ucfirst($event->gateway).')',
                        'notify' => false,
                    ]);
                } catch (\Throwable $e) {
                    AlertAdminJob::dispatch(
                        code: 'chargeback_debit_short',
                        message: "Lost {$event->gateway} dispute {$event->providerDisputeId} but could not fully debit the frozen "
                            .number_format($frozen, 2).' '.$dispute->currency.' — the wallet was short. Reconcile manually.',
                        context: ['gateway' => $event->gateway, 'dispute_id' => $event->providerDisputeId, 'user_id' => $user->id, 'amount' => $frozen],
                    );
                }
            }
        }

        $dispute->update(['status' => $event->status, 'resolved_at' => now()]);
        AlertAdminJob::dispatch(
            code: 'payment_dispute_resolved',
            message: ucfirst($event->gateway)." dispute {$event->providerDisputeId} resolved: {$event->status}."
                .($event->status === DisputeEvent::LOST ? ' Funds were charged back.' : ' We kept the funds.'),
            context: ['gateway' => $event->gateway, 'dispute_id' => $event->providerDisputeId, 'status' => $event->status],
            severity: $event->status === DisputeEvent::LOST ? 'critical' : 'info',
        );
        Auditor::log('payment.dispute_resolved', PaymentDispute::class, $dispute->id, ['status' => $event->status]);

        return $dispute;
    }

    /**
     * Our NAARA reference for a dispute: the one the gateway gave us directly
     * (Paystack) or, failing that, mapped from the provider charge id it cited
     * (Stripe payment_intent / PayPal capture id) via the captured charge.
     */
    private function resolveReference(DisputeEvent $event): ?string
    {
        if ($event->reference) {
            return $event->reference;
        }
        if ($event->providerChargeId !== '') {
            return PaymentCharge::referenceForChargeId($event->gateway, $event->providerChargeId);
        }

        return null;
    }

    private function userFromReference(string $gateway, ?string $reference): ?User
    {
        if (! $reference) {
            return null;
        }
        $txn = WalletTransaction::where('reference', "topup:{$gateway}:{$reference}")
            ->where('type', 'credit')->first();

        return $txn ? ($txn->user ?? User::find($txn->user_id)) : null;
    }
}
