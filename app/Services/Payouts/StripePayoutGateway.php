<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Connect payouts (ROADMAP §Layer 0.2). Money moves from the
 * platform's Stripe balance to the connected Express account's Stripe
 * balance via a Transfer — Stripe then pays that account out to its own bank
 * on ITS payout schedule, which is between Stripe and the connected account
 * from here, not something this platform tracks further. A successful
 * Transfer call is therefore the settlement event for OUR ledger (the money
 * has left the platform's liability); a later `transfer.reversed` webhook
 * (a dispute/chargeback clawback) is the only way this flips to reversed.
 *
 * The connected account is created and onboarded by StripeConnectService —
 * this gateway only sends money to one that already exists and is enabled.
 */
class StripePayoutGateway implements PayoutGatewayInterface
{
    private const TOLERANCE_SECONDS = 300;

    public function name(): string
    {
        return 'stripe';
    }

    public function available(): bool
    {
        return filled(config('services.stripe.secret_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.stripe.base_url'), '/');
    }

    /** The connected account id IS the recipient — no separate object to create. */
    public function createRecipient(PayoutAccount $account): string
    {
        return (string) $account->account_number;
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        try {
            $response = Http::withToken((string) config('services.stripe.secret_key'))->asForm()
                ->post($this->base().'/transfers', [
                    'amount' => (int) round((float) $request->amount * 100),
                    'currency' => strtolower($account->currency ?: 'usd'),
                    'destination' => $account->account_number,
                    'transfer_group' => $request->reference,
                    'metadata' => ['reference' => $request->reference],
                ])->json();
        } catch (\Throwable $e) {
            throw $e; // infra error — let the engine retry
        }

        if (! isset($response['id'])) {
            return new PayoutTransferResult(
                status: 'failed',
                failureReason: (string) (data_get($response, 'error.message') ?: 'Stripe rejected the transfer.'),
            );
        }

        // The transfer landing in the connected account's Stripe balance is
        // the settlement event for our ledger (see class docblock).
        return new PayoutTransferResult(status: 'paid', providerRef: (string) $response['id']);
    }

    public function verifyWebhook(Request $request): bool
    {
        $header = $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');
        if (! is_string($header) || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', $piece, 2), 2, null);
            $parts[trim((string) $k)] = trim((string) $v);
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if ($timestamp === null || $signature === null) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /** Only transfer.reversed matters to the payout_requests ledger here —
     *  account.updated (onboarding status) is handled separately by
     *  StripeConnectWebhookController, not through this payout-event path. */
    public function parseWebhook(Request $request): ?PayoutEvent
    {
        if ((string) $request->input('type') !== 'transfer.reversed') {
            return null;
        }

        $object = $request->input('data.object', []);
        $reference = (string) data_get($object, 'metadata.reference', '');
        if ($reference === '') {
            return null;
        }

        return new PayoutEvent(
            provider: $this->name(),
            reference: $reference,
            status: 'reversed',
            providerRef: (string) data_get($object, 'id', ''),
        );
    }
}
