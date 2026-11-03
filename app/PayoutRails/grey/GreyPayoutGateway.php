<?php

namespace App\PayoutRails\Grey;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\Payouts\DeclaresCapabilities;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutEvent;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Payouts\ReportsBalance;
use App\Services\Payouts\SupportsStatusLookup;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Grey Business API payout rail (global bank + mobile-money payouts).
 *
 * Money rules honoured here:
 *  - The request's wireReference() is both the `X-Idempotency-Key` header and the body `reference`, so a retry of
 *    the same payout can never send twice (Grey replays the original answer for 24h).
 *  - A clear rejection (4xx other than 409) returns a `failed` result -> the engine refunds. 409 (duplicate in
 *    flight), 5xx and network errors THROW, which the engine records as "unknown" and the reconciler resolves
 *    through {@see lookupTransfer()} — we never guess an outcome.
 *  - Webhooks are verified (HMAC-SHA256 over the RAW body, constant-time compare) before the payload is read.
 *  - Grey keys carry no test/live marker, so available() also requires base URL and PAYOUT_ENV to agree.
 *
 * Wallets: see {@see plan()} — a same-currency wallet pays 1:1; otherwise the USD wallet pays through a quoted conversion.
 */
class GreyPayoutGateway implements PayoutGatewayInterface, DeclaresCapabilities, \App\Services\Payouts\ExplainsAvailability, SupportsStatusLookup, ReportsBalance
{
    public function __construct(
        private readonly GreyClient $client,
        private readonly GreyBeneficiaryMapper $mapper,
    ) {
    }

    public function name(): string
    {
        return 'grey';
    }

    public function available(): bool
    {
        return $this->client->configured() && $this->client->environmentConsistent();
    }

    public function unavailableReason(): ?string
    {
        if (! $this->client->configured()) {
            return 'no_key';
        }

        return $this->client->environmentConsistent() ? null : 'key_present_env_mismatch';
    }

    public function capabilities(): array
    {
        return ['confirms_synchronously' => false, 'webhook' => true, 'lookup' => true, 'cancel' => false];
    }

    /**
     * Grey has no recipient object: the beneficiary rides on the payout. What we CAN do up front is confirm,
     * against Grey's own payout-methods, that this currency/country/rail is supported and that the fields every
     * such account needs are present. (Grey's `required_fields` is a union across all countries, so per-country
     * extras are NOT enforced here — Grey's own validation answers those, and each corridor is proven with a real
     * payout before an admin enables it.)
     */
    public function createRecipient(PayoutAccount $account): string
    {
        $mobile = $account->method === 'mobile_money' || $account->type === 'mobile_money';
        $methods = $this->client->payoutMethods();
        if ($methods === []) {
            throw new PayoutException("We can't confirm this account with our payout partner right now. Please try again shortly.");
        }
        $method = $this->mapper->methodFor($methods, (string) $account->currency, (string) $account->country, $mobile);
        if ($method === null) {
            throw new PayoutException('Payouts to this country and currency are not supported yet.');
        }
        $beneficiary = $this->mapper->map($account, $method);
        $base = $mobile ? ['account_number', 'network_type'] : ['account_number'];
        $missing = array_values(array_filter($base, fn ($f) => ! filled($beneficiary[$f] ?? null)));
        if ($missing !== []) {
            throw new PayoutException('Missing account details: '.implode(', ', $missing).'.');
        }

        return 'grey:'.sha1(json_encode($beneficiary));
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        $mobile = $account->method === 'mobile_money' || $account->type === 'mobile_money';
        $method = $this->mapper->methodFor($this->client->payoutMethods(), (string) $account->currency, (string) $account->country, $mobile);
        $beneficiary = $this->mapper->map($account, $method);
        $reference = $request->wireReference();

        $plan = $this->plan($request);
        if ($plan instanceof PayoutTransferResult) {
            return $plan;
        }

        $body = [
            'source_amount' => $plan['amount'],
            'source_currency' => $plan['currency'],
            'destination_currency' => strtoupper((string) $request->currency),
            'description' => 'Naara payout',
            'reference' => $reference,
            'beneficiary' => $beneficiary,
        ];

        // Transport failures (timeouts, DNS) bubble up as ConnectionException -> engine marks the payout unknown.
        $response = $this->client->postPayout($body, $reference);

        if ($response->successful()) {
            return $this->fromTransaction((array) data_get($response->json(), 'data', []));
        }

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            AlertAdminJob::dispatch('grey_auth_failed', 'Grey rejected our API key (HTTP '.$status.'). Grey payouts will fail until it is fixed.', ['status' => $status]);
        }
        if ($status === 409 || $status === 429 || $status >= 500) {
            // Duplicate-in-flight / throttled / provider error: outcome unknown, never a refund.
            $response->throw();
        }

        return new PayoutTransferResult(status: 'failed', failureReason: $this->reason($response));
    }


    /**
     * Which wallet pays, and how much leaves it. Grey pays out of a wallet and converts to the destination
     * currency; wallets exist only for some currencies (balances() lists them, zero balances included).
     *  - A wallet in the payout currency pays 1:1 (source == destination currency, source_amount == amount).
     *  - Otherwise the configured `convert_from` wallet (default USD) pays. A fresh quote (`/v1/currency/rate`,
     *    transaction_type=withdraw; its destination_amount is NET of Grey's fees) sizes the source so the member
     *    receives the locked local amount, and the source is capped at the net USD we reserved plus `max_slippage_pct`.
     *    If the rate has moved past the cap, or the wallet cannot cover it, nothing is sent: a clear `failed`.
     *
     * @return array{currency: string, amount: float}|PayoutTransferResult
     */
    private function plan(PayoutRequest $request): array|PayoutTransferResult
    {
        $dest = strtoupper((string) $request->currency);
        $wallets = $this->wallets();
        if ($wallets === null) {
            $this->client->balances()->throw(); // unreadable: unknown, never guess

            return new PayoutTransferResult(status: 'failed', failureReason: 'Payout wallet could not be read.');
        }
        if (array_key_exists($dest, $wallets)) {
            return ['currency' => $dest, 'amount' => (float) $request->amount];
        }

        $from = strtoupper((string) config('services.grey.convert_from', 'USD'));
        if (! array_key_exists($from, $wallets)) {
            return new PayoutTransferResult(status: 'failed', failureReason: "No Grey wallet to pay {$dest} from.");
        }
        $net = (float) $request->usd_amount - (float) $request->platform_fee_usd;
        if ($net <= 0) {
            return new PayoutTransferResult(status: 'failed', failureReason: 'No USD basis recorded for this payout.');
        }
        $cap = round($net * (1 + max(0.0, (float) config('services.grey.max_slippage_pct', 3)) / 100), 2);

        $probe = $this->client->quoteWithdrawal($from, $dest, round($net, 2));
        $probe->throw();
        $got = (float) data_get($probe->json(), 'data.destination_amount', 0);
        if ($got <= 0) {
            return new PayoutTransferResult(status: 'failed', failureReason: 'Grey could not quote this conversion.');
        }
        // Size the source so the NET destination covers the locked amount; +0.1% for the fixed-fee rounding.
        $source = round(ceil((float) $request->amount / ($got / round($net, 2)) * 1.001 * 100) / 100, 2);
        if ($source > $cap) {
            return new PayoutTransferResult(status: 'failed', failureReason: 'The exchange rate moved too far since the quote.');
        }
        if (($wallets[$from] ?? 0.0) < $source) {
            AlertAdminJob::dispatch('grey_wallet_short', "Grey {$from} wallet cannot cover a {$source} {$from} payout (payout #{$request->id}). Top up the wallet.", ['currency' => $from]);

            return new PayoutTransferResult(status: 'failed', failureReason: 'Payout funds are being topped up. Please try again shortly.');
        }

        return ['currency' => $from, 'amount' => $source];
    }

    /** @return array<string, float>|null spendable balance per provisioned wallet currency, or null if unreadable */
    private function wallets(): ?array
    {
        $r = $this->client->balances();
        if (! $r->successful()) {
            return null;
        }
        $out = [];
        foreach ((array) data_get($r->json(), 'data.balances', []) as $row) {
            if (($row['currency_type'] ?? 'fiat') === 'fiat' && isset($row['currency'])) {
                $out[strtoupper((string) $row['currency'])] = (float) ($row['available_balance'] ?? 0);
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $tx */
    private function fromTransaction(array $tx): PayoutTransferResult
    {
        return new PayoutTransferResult(
            status: $this->mapStatus((string) ($tx['status'] ?? '')),
            providerRef: (string) ($tx['reference'] ?? $tx['transaction_reference'] ?? '') ?: null,
            failureReason: $this->mapStatus((string) ($tx['status'] ?? '')) === 'failed' ? (string) ($tx['failure_reason'] ?? $tx['message'] ?? 'Rejected by the payout provider.') : null,
        );
    }

    /** Grey documents `processing` and `completed`; anything unrecognised is treated as still processing. */
    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'success', 'successful' => 'paid',
            'failed', 'rejected', 'cancelled', 'canceled', 'reversed', 'declined' => 'failed',
            default => 'processing',
        };
    }

    private function reason(Response $response): string
    {
        return (string) (data_get($response->json(), 'message') ?: data_get($response->json(), 'error') ?: 'The payout provider rejected this payout (HTTP '.$response->status().').');
    }

    public function lookupTransfer(PayoutRequest $request): LookupResult
    {
        $response = $this->client->transactionsByClientReference($request->wireReference());
        if ($response->status() === 404) {
            return LookupResult::notFound();
        }
        $response->throw(); // auth/5xx is not an answer; the reconciler retries later

        foreach ((array) data_get($response->json(), 'data', []) as $tx) {
            if (($tx['client_reference'] ?? null) === $request->wireReference()) {
                $status = $this->mapStatus((string) ($tx['status'] ?? ''));

                return LookupResult::found($status, (string) ($tx['reference'] ?? '') ?: null, $status === 'failed' ? (string) ($tx['failure_reason'] ?? 'failed') : null);
            }
        }

        return LookupResult::notFound();
    }

    /** @return array<string, float> currency => spendable (available) balance in major units */
    public function balances(): array
    {
        $response = $this->client->balances();
        $response->throw();

        $out = [];
        foreach ((array) data_get($response->json(), 'data.balances', []) as $row) {
            if (($row['currency_type'] ?? 'fiat') === 'fiat' && isset($row['currency'], $row['available_balance']) && is_numeric($row['available_balance'])) {
                $out[strtoupper((string) $row['currency'])] = round((float) $row['available_balance'], 4);
            }
        }

        return $out;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('services.grey.webhook_secret');
        $header = (string) $request->header('X-Webhook-Signature', '');
        if ($secret === '' || $header === '') {
            return false;
        }
        $given = str_starts_with($header, 'sha256=') ? substr($header, 7) : $header;

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($given));
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        $event = (string) ($request->header('X-Webhook-Event') ?: $request->input('event_type', ''));
        $reference = (string) $request->input('client_reference', '');

        // Inflows (transaction.received) and created events carry nothing we act on; a payout we did not create
        // has no client_reference of ours.
        if ($reference === '' || ! in_array($event, ['transaction.success', 'transaction.failed'], true)) {
            return null;
        }

        return new PayoutEvent(
            provider: $this->name(),
            reference: $reference,
            status: $event === 'transaction.success' ? 'paid' : 'failed',
            providerRef: (string) $request->input('transaction_reference', '') ?: null,
        );
    }
}
