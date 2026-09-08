<?php

namespace App\Services\Payments;

use App\Jobs\AlertAdminJob;
use App\Models\PaymentCharge;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Admin-triggered refunds of wallet top-ups (BUILD-2 §7.1). Money-safety:
 *
 *  - Idempotent: a top-up is refunded at most once (unique gateway+reference).
 *  - Provider first, wallet second: the gateway refund must succeed BEFORE we
 *    touch the ledger, so we never remove wallet funds without the money
 *    actually going back to the payer.
 *  - Ledger discipline: the reversal is a `debit` `wallet_transactions` row via
 *    WalletService (atomic, lock + tx), never a direct balance write.
 *  - Never silently create a loss: if the user has already SPENT the credited
 *    funds (wallet can't cover the reversal), we refuse and tell the admin to
 *    resolve manually rather than push the wallet negative.
 *  - Irreversible rails (crypto) have no API refund — those are recorded as a
 *    `manual` task and alerted, never auto-moved.
 */
class RefundService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Refund a top-up. `$topup` is the original credit `wallet_transactions`
     * row (reference `topup:{gateway}:{ref}`). `$amount` null = full refund.
     *
     * @throws RefundException
     */
    public function refund(WalletTransaction $topup, ?float $amount, string $reason, ?User $admin = null): PaymentRefund
    {
        [$gateway, $ref] = $this->parseTopup($topup);
        $currency = strtoupper((string) $topup->currency);
        $full = round((float) $topup->amount, 4);
        $amount = $amount !== null ? round($amount, 4) : $full;

        if ($amount <= 0 || $amount > $full) {
            throw new RefundException('Refund amount must be between 0 and the original '.number_format($full, 2).'.');
        }
        if (PaymentRefund::where('gateway', $gateway)->where('reference', $ref)->exists()) {
            throw new RefundException('This top-up has already been refunded.');
        }

        $user = $topup->user ?? User::find($topup->user_id);
        if (! $user) {
            throw new RefundException('The paying user no longer exists.');
        }

        $svc = app("pay.{$gateway}");

        // Irreversible rail (crypto): no API refund — record a manual task.
        if (! $svc instanceof RefundableGateway) {
            return $this->recordManual($gateway, $ref, $user, $amount, $currency, $reason, $admin);
        }

        // Don't refund funds the user has already spent (would push the wallet
        // negative / create an unbudgeted loss). Reserved funds are off-limits too.
        $available = $this->available($user, $currency);
        if ($available + 1e-6 < $amount) {
            throw new RefundException('The user has only '.number_format($available, 2).' '.$currency
                .' left of these funds — the rest is spent or frozen. Resolve this refund manually.');
        }

        // Provider first: only reverse the wallet if the money actually goes back.
        // Hand the gateway the provider charge id captured at webhook time
        // (Stripe payment_intent, PayPal capture id, Flutterwave txn id).
        $charge = PaymentCharge::where('gateway', $gateway)->where('reference', $ref)->first();
        $context = [
            'provider_charge_id' => $charge?->provider_charge_id,
            'meta' => $charge?->meta ?? [],
        ];
        $result = $svc->refund($ref, $amount, $currency, $context);
        if (! $result->ok) {
            $this->record($gateway, $ref, $user, $amount, $currency, $reason, $admin, PaymentRefund::STATUS_FAILED, null);
            throw new RefundException(ucfirst($gateway).' declined the refund: '.($result->error ?? 'unknown error').'.');
        }

        return DB::transaction(function () use ($user, $amount, $currency, $gateway, $ref, $reason, $admin, $result) {
            try {
                $this->wallet->debit($user, $amount, $currency, [
                    'reference' => "refund-reversal:{$gateway}:{$ref}",
                    'description' => 'Refund of '.ucfirst($gateway).' top-up',
                    'notify' => false,
                ]);
            } catch (\Throwable $e) {
                // The provider refund already went out; we could not reverse the
                // wallet (race: funds just spent). Freeze nothing, alert loudly.
                AlertAdminJob::dispatch(
                    code: 'refund_reversal_failed',
                    message: "Refunded {$amount} {$currency} on {$gateway} (ref {$ref}) but could NOT reverse the wallet — reconcile manually.",
                    context: ['gateway' => $gateway, 'reference' => $ref, 'user_id' => $user->id, 'amount' => $amount, 'currency' => $currency],
                );
                throw new RefundException('The refund was sent but the wallet could not be reversed — an admin has been alerted.');
            }

            $refund = $this->record($gateway, $ref, $user, $amount, $currency, $reason, $admin, PaymentRefund::STATUS_DONE, $result->providerRef);
            Auditor::log('payment.refunded', PaymentRefund::class, $refund->id, [
                'gateway' => $gateway, 'reference' => $ref, 'amount' => $amount, 'currency' => $currency, 'admin_id' => $admin?->id,
            ]);

            return $refund;
        });
    }

    /** USD counts spendable (reserved funds are not refundable); NGN has no earmark. */
    private function available(User $user, string $currency): float
    {
        if ($currency === 'USD') {
            return $this->wallet->spendableUsd($user);
        }

        return round((float) ($user->wallet?->ngn_balance ?? 0), 4);
    }

    /** @return array{0:string,1:string} [gateway, originalReference] */
    private function parseTopup(WalletTransaction $topup): array
    {
        if ($topup->type !== 'credit' || ! str_starts_with((string) $topup->reference, 'topup:')) {
            throw new RefundException('Only a wallet top-up can be refunded here.');
        }
        // reference = topup:{gateway}:{ref}
        [, $gateway, $ref] = array_pad(explode(':', (string) $topup->reference, 3), 3, '');
        if ($gateway === '' || $ref === '') {
            throw new RefundException('This top-up record is malformed.');
        }

        return [$gateway, $ref];
    }

    private function recordManual(string $gateway, string $ref, User $user, float $amount, string $currency, string $reason, ?User $admin): PaymentRefund
    {
        $refund = $this->record($gateway, $ref, $user, $amount, $currency, $reason, $admin, PaymentRefund::STATUS_MANUAL, null);
        AlertAdminJob::dispatch(
            code: 'refund_manual_required',
            message: ucfirst($gateway)." top-up (ref {$ref}) needs a MANUAL refund — this rail has no refund API. Send {$amount} {$currency} back by hand, then adjust the wallet.",
            context: ['gateway' => $gateway, 'reference' => $ref, 'user_id' => $user->id, 'amount' => $amount, 'currency' => $currency],
            severity: 'warning',
        );
        Auditor::log('payment.refund_manual', PaymentRefund::class, $refund->id, ['gateway' => $gateway, 'reference' => $ref]);

        return $refund;
    }

    private function record(string $gateway, string $ref, User $user, float $amount, string $currency, string $reason, ?User $admin, string $status, ?string $providerRef): PaymentRefund
    {
        return PaymentRefund::updateOrCreate(
            ['gateway' => $gateway, 'reference' => $ref],
            [
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'provider_refund_ref' => $providerRef,
                'reason' => $reason !== '' ? $reason : null,
                'admin_id' => $admin?->id,
            ],
        );
    }
}
