<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Paystack Transfers (ROADMAP §Layer 0.2). Creates a transfer recipient once
 * (cached on the account), then sends the transfer. The synchronous response is
 * only provisional — the transfer.success / transfer.failed webhook is the
 * source of truth (verified with the same HMAC-SHA512 as the collection side).
 */
class PaystackPayoutGateway implements PayoutGatewayInterface, ReportsBalance, SupportsStatusLookup, DeclaresCapabilities
{
    public function name(): string
    {
        return 'paystack';
    }

    public function available(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    private function base(): string
    {
        return rtrim(config('services.paystack.base_url'), '/');
    }

    public function createRecipient(PayoutAccount $account): string
    {
        $response = Http::connectTimeout(\App\Support\PayoutSettings::httpConnectTimeout())->timeout(\App\Support\PayoutSettings::httpTimeout())->withToken(config('services.paystack.secret_key'))
            ->acceptJson()
            ->post($this->base().'/transferrecipient', [
                'type' => $account->type === 'mobile_money' ? 'mobile_money' : 'nuban',
                'name' => $account->account_name,
                'account_number' => $account->account_number,
                'bank_code' => $account->bank_code,
                'currency' => strtoupper($account->currency),
            ])->throw()->json();

        return (string) data_get($response, 'data.recipient_code', '');
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        $response = Http::connectTimeout(\App\Support\PayoutSettings::httpConnectTimeout())->timeout(\App\Support\PayoutSettings::httpTimeout())->withToken(config('services.paystack.secret_key'))
            ->acceptJson()
            ->post($this->base().'/transfer', [
                'source' => 'balance',
                'amount' => (int) round((float) $request->amount * 100), // kobo
                'recipient' => $account->provider_recipient_ref,
                'reference' => $request->wireReference(),
                'reason' => 'NaaraSim payout',
            ])->json();

        if (data_get($response, 'status') === false) {
            return new PayoutTransferResult(status: 'failed', failureReason: (string) data_get($response, 'message', 'Rejected.'));
        }

        $status = (string) data_get($response, 'data.status', 'pending');
        $ref = (string) data_get($response, 'data.transfer_code', '');

        return new PayoutTransferResult(
            status: match ($status) {
                'success' => 'paid',
                'failed', 'abandoned', 'reversed' => 'failed',
                default => 'processing', // pending / otp — webhook confirms
            },
            providerRef: $ref !== '' ? $ref : null,
        );
    }

    /**
     * Paystack "Verify Transfer": GET /transfer/verify/:reference — keyed by the same
     * reference we sent. NOTE: confirm against the Paystack sandbox before go-live
     * (docs/payouts/GO-LIVE-CHECKLIST.md); until then reconcile-unknown is only as
     * trustworthy as this call, which is why a single "not found" never reverses.
     */
    public function lookupTransfer(PayoutRequest $request): LookupResult
    {
        $response = Http::connectTimeout(\App\Support\PayoutSettings::httpConnectTimeout())->timeout(\App\Support\PayoutSettings::httpTimeout())->withToken(config('services.paystack.secret_key'))
            ->acceptJson()
            ->get($this->base().'/transfer/verify/'.rawurlencode($request->wireReference()));

        if ($response->status() === 404) {
            return LookupResult::notFound();
        }
        $response->throw(); // 5xx / auth errors are NOT an answer — the reconciler retries later

        $json = $response->json();
        if (data_get($json, 'status') !== true) {
            return LookupResult::notFound();
        }

        $ref = (string) data_get($json, 'data.transfer_code', '') ?: null;

        return match ((string) data_get($json, 'data.status', '')) {
            'success' => LookupResult::found('paid', $ref),
            'failed', 'abandoned', 'reversed', 'rejected', 'blocked' => LookupResult::found('failed', $ref, (string) data_get($json, 'data.reason', 'failed')),
            default => LookupResult::found('processing', $ref), // pending / otp / received
        };
    }

    /**
     * The platform's own Paystack balance per currency. Paystack reports minor units
     * (kobo/pesewas), so they are divided by 100. Sandbox-verify before trusting for
     * auto-sync (GO-LIVE-CHECKLIST); a non-answer returns [] and syncs nothing.
     */
    public function balances(): array
    {
        $response = Http::connectTimeout(\App\Support\PayoutSettings::httpConnectTimeout())->timeout(\App\Support\PayoutSettings::httpTimeout())->withToken(config('services.paystack.secret_key'))->acceptJson()->get($this->base().'/balance');
        $response->throw();

        $out = [];
        foreach ((array) data_get($response->json(), 'data', []) as $row) {
            if (isset($row['currency'], $row['balance']) && is_numeric($row['balance'])) {
                $out[strtoupper((string) $row['currency'])] = round(((float) $row['balance']) / 100, 4);
            }
        }

        return $out;
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');
        $secret = (string) config('services.paystack.secret_key');

        // Pentest finding (2026-09-16): an empty secret must never validate —
        // hash_hmac(..., '') is computable by anyone, so without this guard an
        // unconfigured Paystack could have a forged "paid"/"failed" payout
        // webhook accepted, either masking a real transfer failure or
        // triggering a refund on a payout that never actually failed. Mirrors
        // the same guard PaystackGateway (payments side) already has.
        if (! is_string($signature) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        $event = (string) $request->input('event');
        if (! str_starts_with($event, 'transfer.')) {
            return null;
        }

        $data = $request->input('data', []);

        return new PayoutEvent(
            provider: $this->name(),
            reference: (string) data_get($data, 'reference', ''),
            status: match ($event) {
                'transfer.success' => 'paid',
                'transfer.reversed' => 'reversed',
                default => 'failed', // transfer.failed
            },
            providerRef: (string) data_get($data, 'transfer_code', '') ?: null,
        );
    }

    public function capabilities(): array
    {
        return ['confirms_synchronously' => false, 'webhook' => true, 'lookup' => true, 'cancel' => false];
    }
}
