<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Flutterwave Transfers/Payouts (ROADMAP §Layer 0.2). Flutterwave sends directly
 * to a bank+account (no separate recipient object), so createRecipient() just
 * returns a stable marker. The transfer.completed webhook — verified with the
 * same verif-hash secret as the collection side — is the source of truth.
 */
class FlutterwavePayoutGateway implements PayoutGatewayInterface
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function available(): bool
    {
        return filled(config('services.flutterwave.secret_key'));
    }

    private function base(): string
    {
        return rtrim(config('services.flutterwave.base_url'), '/');
    }

    public function createRecipient(PayoutAccount $account): string
    {
        // Flutterwave transfers carry the destination inline — no recipient
        // object. Return a marker so the engine records the account is "prepared".
        return 'fw:'.$account->bank_code.':'.$account->account_number;
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->acceptJson()
            ->post($this->base().'/transfers', [
                'account_bank' => $account->bank_code,
                'account_number' => $account->account_number,
                'amount' => (float) $request->amount,
                'currency' => strtoupper($request->currency),
                'reference' => $request->reference,
                'narration' => 'NaaraSim payout',
            ])->json();

        if (data_get($response, 'status') === 'error') {
            return new PayoutTransferResult(status: 'failed', failureReason: (string) data_get($response, 'message', 'Rejected.'));
        }

        $status = strtoupper((string) data_get($response, 'data.status', 'PENDING'));
        $ref = (string) data_get($response, 'data.id', '');

        return new PayoutTransferResult(
            status: match ($status) {
                'SUCCESSFUL' => 'paid',
                'FAILED' => 'failed',
                default => 'processing', // NEW / PENDING — webhook confirms
            },
            providerRef: $ref !== '' ? $ref : null,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header('verif-hash');
        $secret = (string) config('services.flutterwave.secret_hash');

        return is_string($signature) && $secret !== '' && hash_equals($secret, $signature);
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        $data = $request->input('data', []);
        if (data_get($data, 'reference') === null) {
            return null;
        }

        $status = strtoupper((string) data_get($data, 'status', ''));

        return new PayoutEvent(
            provider: $this->name(),
            reference: (string) data_get($data, 'reference', ''),
            status: match ($status) {
                'SUCCESSFUL' => 'paid',
                default => 'failed', // FAILED / anything terminal-negative
            },
            providerRef: ($id = data_get($data, 'id')) !== null ? (string) $id : null,
        );
    }
}
