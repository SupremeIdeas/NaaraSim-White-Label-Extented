<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\PayoutRails\Grey\GreyPayoutGateway;
use App\Services\Payouts\Extensions\PayoutRailExtensions;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Grey Business API payout rail, against responses shaped exactly like the supplied Grey docs
 * (grey-business-api.md / grey_openapi.json). No real Grey call is made anywhere here.
 */
class GreyPayoutRailTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_grey';

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\Payouts\PayoutService::class);   // resolving the engine is what loads the extension rails, whatever ran before this test
        Cache::flush();
        config([
            'services.grey.api_secret' => 'gbsk_test',
            'services.grey.webhook_secret' => self::SECRET,
            'services.grey.base_url' => 'https://businessapi-sandbox.grey.co',
            'payouts.env' => 'sandbox',
        ]);
    }

    /** Replace (not append to) the faked responses: the first matching stub always wins. */
    /** Real-shaped defaults (wallets USD/EUR/GBP like the live account; payout-methods with Grey's union field lists). */
    private function fake(array $stubs): void
    {
        $methods = ['status' => 'success', 'data' => ['payout_methods' => [
            ['name' => 'bank_account', 'currencies' => ['USD', 'EUR', 'GBP', 'NGN'], 'countries' => ['US', 'GB', 'DE'], 'required_fields' => ['account_number', 'account_type', 'bank_code', 'branch_code', 'iban', 'first_name', 'last_name', 'phone_number'], 'payment_purposes' => ['Family Maintenance', 'Other']],
            ['name' => 'mobile_money', 'currencies' => ['XOF'], 'countries' => ['CI'], 'required_fields' => ['account_number', 'first_name', 'last_name', 'network_type', 'phone_number', 'wallet_id'], 'payment_purposes' => ['Family Maintenance', 'Salary']],
        ]]];
        $wallets = ['status' => 'success', 'data' => ['balances' => [
            ['currency' => 'EUR', 'currency_type' => 'fiat', 'available_balance' => 5000, 'pending_balance' => 0],
            ['currency' => 'GBP', 'currency_type' => 'fiat', 'available_balance' => 5000, 'pending_balance' => 0],
            ['currency' => 'USD', 'currency_type' => 'fiat', 'available_balance' => 5000, 'pending_balance' => 0],
        ]]];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs + ['*/v1/balances' => Http::response($wallets), '*/v1/payout-methods' => Http::response($methods)]);
    }

    private function gw(): GreyPayoutGateway
    {
        return app(GreyPayoutGateway::class);
    }

    private function account(array $over = []): PayoutAccount
    {
        $user = User::factory()->create();

        return PayoutAccount::create(array_merge([
            'user_id' => $user->id, 'type' => 'bank', 'method' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '', 'bank_name' => 'Chase Bank', 'account_number' => '1234567890', 'account_name' => 'John Doe',
            'provider' => 'grey', 'is_verified' => true, 'details' => ['routing_number' => '021000021', 'account_type' => 'checking'],
        ], $over));
    }

    private function request(PayoutAccount $a, array $over = []): PayoutRequest
    {
        return PayoutRequest::create(array_merge([
            'user_id' => $a->user_id, 'payee_type' => 'referral', 'payout_account_id' => $a->id, 'amount' => 500, 'currency' => $a->currency,
            'source_bucket' => 'referral', 'status' => 'processing', 'provider' => 'grey', 'reference' => 'PO-grey-1', 'provider_reference' => 'NS-GREY-0001',
        ], $over));
    }

    private function signed(array $payload, string $event, ?string $secret = null): array
    {
        $body = json_encode($payload);

        return [$body, ['HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, $secret ?? self::SECRET), 'HTTP_X_WEBHOOK_EVENT' => $event, 'CONTENT_TYPE' => 'application/json']];
    }

    private function hook(array $payload, string $event, array $server): Request
    {
        return Request::create('/webhooks/payouts/grey', 'POST', [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
    }

    public function test_the_rail_installs_as_an_extension_with_no_problems(): void
    {
        app(\App\Services\Payouts\PayoutService::class); // extension gateways register with the engine
        $this->assertTrue(PayoutRailExtensions::installed('grey'));
        $this->assertSame([], array_filter(PayoutRailExtensions::problems(), fn ($p) => $p['slug'] === 'grey'));
        $this->assertSame('grey', app('payout.grey')->name());
    }

    public function test_availability_needs_a_key_and_an_environment_that_agrees_with_the_host(): void
    {
        $this->assertTrue($this->gw()->available());

        config(['services.grey.api_secret' => '']);
        $this->assertFalse($this->gw()->available(), 'no key');

        // Production host while the platform is in sandbox mode -> refused.
        config(['services.grey.api_secret' => 'gbsk_x', 'services.grey.base_url' => 'https://businessapi.grey.co']);
        $this->assertFalse($this->gw()->available());

        // Sandbox host while live -> refused (a live deploy must never quietly run on sandbox).
        $this->app['env'] = 'production';
        config(['payouts.env' => 'live', 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co']);
        $this->assertFalse($this->gw()->available());
        config(['services.grey.base_url' => 'https://businessapi.grey.co']);
        $this->assertTrue($this->gw()->available());
    }

    public function test_send_posts_the_documented_payout_with_idempotency_and_maps_processing(): void
    {
        $this->fake(['*/v1/charge/payout' => Http::response(['status' => 'success', 'data' => ['id' => 'uuid-1', 'reference' => 'txn_123', 'client_reference' => 'NS-GREY-0001', 'status' => 'processing']])]);
        $a = $this->account();
        $res = $this->gw()->sendTransfer($this->request($a), $a);

        $this->assertSame('processing', $res->status);
        $this->assertSame('txn_123', $res->providerRef);

        Http::assertSent(function (HttpRequest $r) {
            $b = $r->data();

            return $r->url() === 'https://businessapi-sandbox.grey.co/v1/charge/payout'
                && $r->hasHeader('Authorization', 'Bearer gbsk_test')
                && $r->hasHeader('X-Idempotency-Key', 'NS-GREY-0001')
                && $b['reference'] === 'NS-GREY-0001'
                && $b['source_currency'] === 'USD' && $b['destination_currency'] === 'USD' && (float) $b['source_amount'] === 500.0
                && $b['beneficiary']['first_name'] === 'John' && $b['beneficiary']['last_name'] === 'Doe'
                && $b['beneficiary']['routing_number'] === '021000021'
                && $b['beneficiary']['scheme'] === 'ACH'           // USD bank payout to a US account
                && $b['beneficiary']['bank_country_code'] === 'US';
        });
    }

    public function test_usd_bank_payout_outside_the_us_uses_swift(): void
    {
        $this->fake(['*/v1/charge/payout' => Http::response(['data' => ['status' => 'processing', 'reference' => 'r']])]);
        $a = $this->account(['country' => 'GB', 'details' => ['swift_code' => 'BARCGB22']]);
        $this->gw()->sendTransfer($this->request($a), $a);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/charge/payout') && $r->data()['beneficiary']['scheme'] === 'SWIFT' && $r->data()['beneficiary']['swift_code'] === 'BARCGB22');
    }

    public function test_mobile_money_uses_the_mobile_rail_fields(): void
    {
        // Mobile money has no "Other" on Grey's list, so the owner's configured purpose (an exact listed value) is what is sent.
        config(['services.grey.payment_purpose' => 'Salary']);
        $this->fake([
            '*/v1/currency/rate' => Http::response(['data' => ['destination_amount' => 56942.94]]),
            '*/v1/charge/payout' => Http::response(['data' => ['status' => 'processing', 'reference' => 'r']]),
        ]);
        $a = $this->account(['method' => 'mobile_money', 'type' => 'mobile_money', 'country' => 'CI', 'currency' => 'XOF', 'bank_code' => 'MOOV', 'bank_name' => null, 'details' => ['phone_number' => '0160942872']]);
        $this->gw()->sendTransfer($this->request($a, ['amount' => 57000, 'usd_amount' => 100, 'platform_fee_usd' => 0]), $a);
        Http::assertSent(function (HttpRequest $r) {
            if (! str_ends_with($r->url(), '/v1/charge/payout')) {
                return false;
            }
            $b = $r->data()['beneficiary'];

            return $b['destination'] === 'mobile_money' && $b['network_type'] === 'MOOV' && $b['phone_number'] === '0160942872'
                && $b['source_of_funds'] === 'BUSINESS_INCOME' && ($b['payment_purpose'] ?? null) === 'Salary' && ! isset($b['bank_code']);
        });
    }

    public function test_completed_is_paid_and_unknown_statuses_stay_processing(): void
    {
        $a = $this->account();
        $this->fake(['*/v1/charge/payout' => Http::response(['data' => ['status' => 'completed', 'reference' => 'x']])]);
        $this->assertSame('paid', $this->gw()->sendTransfer($this->request($a), $a)->status);

        $this->fake(['*/v1/charge/payout' => Http::response(['data' => ['status' => 'some_new_state', 'reference' => 'x']])]);
        $this->assertSame('processing', $this->gw()->sendTransfer($this->request($a, ['reference' => 'PO-2', 'provider_reference' => 'NS-GREY-0002']), $a)->status);
    }

    public function test_a_clear_rejection_is_a_failed_result_with_the_providers_reason(): void
    {
        $this->fake(['*/v1/charge/payout' => Http::response(['status' => 'error', 'message' => 'Insufficient balance'], 400)]);
        $a = $this->account();
        $res = $this->gw()->sendTransfer($this->request($a), $a);
        $this->assertSame('failed', $res->status);
        $this->assertSame('Insufficient balance', $res->failureReason);
    }

    public function test_duplicate_in_flight_and_server_errors_throw_so_the_outcome_is_never_guessed(): void
    {
        $a = $this->account();
        $this->fake(['*/v1/charge/payout' => Http::response(['message' => 'duplicate'], 409)]);
        try {
            $this->gw()->sendTransfer($this->request($a), $a);
            $this->fail('409 must throw');
        } catch (RequestException $e) {
            $this->assertSame(409, $e->response->status());
        }

        $this->fake(['*/v1/charge/payout' => Http::response('boom', 502)]);
        $this->expectException(RequestException::class);
        $this->gw()->sendTransfer($this->request($a, ['reference' => 'PO-3', 'provider_reference' => 'NS-GREY-0003']), $a);
    }

    public function test_a_timeout_propagates_as_a_connection_exception(): void
    {
        $this->fake(['*' => fn () => throw new ConnectionException('timed out')]);
        $a = $this->account();
        $this->expectException(ConnectionException::class);
        $this->gw()->sendTransfer($this->request($a), $a);
    }

    public function test_lookup_finds_by_client_reference_and_reports_not_found(): void
    {
        $a = $this->account();
        $req = $this->request($a);
        $this->fake(['*/api/v1/transactions*' => Http::response(['status' => 'success', 'data' => [['id' => 'u', 'reference' => 'txn_9', 'client_reference' => 'NS-GREY-0001', 'status' => 'completed']]])]);
        $r = $this->gw()->lookupTransfer($req);
        $this->assertSame(LookupResult::FOUND, $r->state);
        $this->assertSame('paid', $r->status);
        $this->assertSame('txn_9', $r->providerRef);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'client_reference=NS-GREY-0001'));

        $this->fake(['*/api/v1/transactions*' => Http::response(['status' => 'success', 'data' => []])]);
        $this->assertSame(LookupResult::NOT_FOUND, $this->gw()->lookupTransfer($req)->state);

        // A server error is not an answer — the reconciler must retry, never refund on it.
        $this->fake(['*/api/v1/transactions*' => Http::response('x', 500)]);
        $this->expectException(RequestException::class);
        $this->gw()->lookupTransfer($req);
    }

    public function test_balances_report_spendable_fiat_per_currency(): void
    {
        $this->fake(['*/v1/balances' => Http::response(['status' => 'success', 'data' => ['balances' => [
            ['currency' => 'USD', 'currency_type' => 'fiat', 'available_balance' => 13345.67, 'pending_balance' => 100.5],
            ['currency' => 'NGN', 'currency_type' => 'fiat', 'available_balance' => 0],
            ['currency' => 'BTC', 'currency_type' => 'crypto', 'available_balance' => 1.2],
        ]]])]);
        $this->assertSame(['USD' => 13345.67, 'NGN' => 0.0], $this->gw()->balances());
    }

    public function test_webhook_signature_is_verified_over_the_raw_body(): void
    {
        $payload = ['event_type' => 'transaction.success', 'client_reference' => 'NS-GREY-0001', 'transaction_reference' => 'GREY7F3K2MQ9', 'status' => 'completed'];
        [, $server] = $this->signed($payload, 'transaction.success');
        $this->assertTrue($this->gw()->verifyWebhook($this->hook($payload, 'transaction.success', $server)));

        [, $bad] = $this->signed($payload, 'transaction.success', 'wrong-secret');
        $this->assertFalse($this->gw()->verifyWebhook($this->hook($payload, 'transaction.success', $bad)));

        // Tampered body with an otherwise-valid signature.
        $tampered = $this->hook($payload + ['x' => 1], 'transaction.success', $server);
        $this->assertFalse($this->gw()->verifyWebhook($tampered));

        // No signature, and an unconfigured secret, are both refused (never "valid because empty").
        $this->assertFalse($this->gw()->verifyWebhook($this->hook($payload, 'transaction.success', [])));
        config(['services.grey.webhook_secret' => '']);
        $this->assertFalse($this->gw()->verifyWebhook($this->hook($payload, 'transaction.success', $server)));
    }

    public function test_webhook_events_map_to_payout_events(): void
    {
        $ok = $this->hook(['client_reference' => 'NS-GREY-0001', 'transaction_reference' => 'G1'], 'transaction.success', ['HTTP_X_WEBHOOK_EVENT' => 'transaction.success']);
        $e = $this->gw()->parseWebhook($ok);
        $this->assertSame(['grey', 'NS-GREY-0001', 'paid', 'G1'], [$e->provider, $e->reference, $e->status, $e->providerRef]);

        $bad = $this->hook(['client_reference' => 'NS-GREY-0001'], 'transaction.failed', ['HTTP_X_WEBHOOK_EVENT' => 'transaction.failed']);
        $this->assertSame('failed', $this->gw()->parseWebhook($bad)->status);

        // created / inflow / a payout we never made: nothing to act on.
        $this->assertNull($this->gw()->parseWebhook($this->hook(['client_reference' => 'NS-GREY-0001'], 'transaction.created', ['HTTP_X_WEBHOOK_EVENT' => 'transaction.created'])));
        $this->assertNull($this->gw()->parseWebhook($this->hook(['transaction_reference' => 'IN1'], 'transaction.received', ['HTTP_X_WEBHOOK_EVENT' => 'transaction.received'])));
        $this->assertNull($this->gw()->parseWebhook($this->hook(['client_reference' => ''], 'transaction.success', ['HTTP_X_WEBHOOK_EVENT' => 'transaction.success'])));
    }

    public function test_the_webhook_route_applies_a_signed_success_once_and_ignores_a_forged_one(): void
    {
        $a = $this->account();
        $req = $this->request($a);
        $payload = ['event_type' => 'transaction.success', 'client_reference' => 'NS-GREY-0001', 'transaction_reference' => 'G1', 'status' => 'completed'];
        $body = json_encode($payload);
        $sig = 'sha256='.hash_hmac('sha256', $body, self::SECRET);
        $headers = ['X-Webhook-Signature' => $sig, 'X-Webhook-Event' => 'transaction.success', 'X-Webhook-Delivery-ID' => 'd1', 'Content-Type' => 'application/json'];

        $this->call('POST', '/webhooks/payouts/grey', [], [], [], $this->serverFrom($headers), $body)->assertOk();
        $this->assertSame(PayoutRequest::PAID, $req->fresh()->status);

        // Redelivery of the same event is a no-op, not a second settlement.
        $this->call('POST', '/webhooks/payouts/grey', [], [], [], $this->serverFrom($headers), $body)->assertOk();
        $this->assertSame(PayoutRequest::PAID, $req->fresh()->status);

        // Forged signature on another payout does nothing.
        $b = $this->request($a, ['reference' => 'PO-9', 'provider_reference' => 'NS-GREY-0009']);
        $forgedBody = json_encode(['event_type' => 'transaction.success', 'client_reference' => 'NS-GREY-0009']);
        $forged = ['X-Webhook-Signature' => 'sha256='.str_repeat('0', 64), 'X-Webhook-Event' => 'transaction.success', 'Content-Type' => 'application/json'];
        $this->call('POST', '/webhooks/payouts/grey', [], [], [], $this->serverFrom($forged), $forgedBody);
        $this->assertNotSame(PayoutRequest::PAID, $b->fresh()->status);
    }

    /** @param  array<string, string>  $headers */
    private function serverFrom(array $headers): array
    {
        $s = [];
        foreach ($headers as $k => $v) {
            $s[strtoupper(str_starts_with(strtoupper($k), 'CONTENT') ? $k : 'HTTP_'.$k)] = $v;
        }
        $s = array_combine(array_map(fn ($k) => str_replace('-', '_', $k), array_keys($s)), array_values($s));

        return $s;
    }

    public function test_save_time_check_uses_greys_payout_methods_and_refuses_what_grey_cannot_pay(): void
    {
        $this->fake([]);
        $this->assertStringStartsWith('grey:', $this->gw()->createRecipient($this->account()));

        // A rail/currency/country Grey does not list.
        try {
            $this->gw()->createRecipient($this->account(['country' => 'NG', 'currency' => 'NGN']));
            $this->fail('unsupported corridor must be refused');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('not supported', $e->getMessage());
        }

        // The union of required_fields is NOT enforced per country: only what every account needs.
        $this->assertStringStartsWith('grey:', $this->gw()->createRecipient($this->account(['details' => []])));
        try {
            $this->gw()->createRecipient($this->account(['account_number' => '']));
            $this->fail('no account number');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('account_number', $e->getMessage());
        }

        // Grey unreachable: never "assume supported".
        Cache::flush();
        $this->fake(['*/v1/payout-methods' => Http::response('x', 500)]);
        $this->expectException(PayoutException::class);
        $this->gw()->createRecipient($this->account());
    }

    public function test_the_payment_purpose_is_one_greys_list_accepts(): void
    {
        $this->fake(['*/v1/charge/payout' => Http::response(['data' => ['status' => 'processing', 'reference' => 'r']])]);
        $a = $this->account();
        $this->gw()->sendTransfer($this->request($a), $a);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/charge/payout') && $r->data()['beneficiary']['payment_purpose'] === 'Other');

        // A configured value that is on the list wins; one that is not falls back to "Other".
        config(['services.grey.payment_purpose' => 'Family Maintenance']);
        $this->fake(['*/v1/charge/payout' => Http::response(['data' => ['status' => 'processing', 'reference' => 'r']])]);
        $this->gw()->sendTransfer($this->request($a, ['reference' => 'PO-pp', 'provider_reference' => 'NS-PP']), $a);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/charge/payout') && $r->data()['beneficiary']['payment_purpose'] === 'Family Maintenance');
    }

    // ---- paying a currency Grey has no wallet for (the live account has USD/EUR/GBP wallets only)

    private function ngnAccount(): PayoutAccount
    {
        return $this->account(['country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058', 'bank_name' => 'GTBank', 'details' => []]);
    }

    private function conversionRequest(PayoutAccount $a, array $over = []): PayoutRequest
    {
        // $100 reserved, $0 fee, locked FX 1337 -> 133,700 NGN promised.
        return $this->request($a, array_merge(['amount' => 133700, 'currency' => 'NGN', 'usd_amount' => 100, 'platform_fee_usd' => 0, 'fx_rate' => 1337], $over));
    }

    private function quote(float $destination): \Illuminate\Http\Client\ResponseSequence|\GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['status' => 'success', 'data' => ['source_amount' => 100, 'source_currency' => 'USD', 'destination_currency' => 'NGN', 'destination_amount' => $destination, 'swap_fee' => 0.5, 'withdrawal_fee' => 0.0358]]);
    }

    public function test_a_currency_without_a_wallet_is_paid_from_usd_through_a_quote_sized_to_the_locked_amount(): void
    {
        // Grey nets 132,987.61 NGN per 100 USD (real quote shape). To deliver 133,700 NGN we need a little over $100.54.
        $this->fake(['*/v1/currency/rate' => $this->quote(132987.61), '*/v1/charge/payout' => Http::response(['data' => ['status' => 'processing', 'reference' => 'g1']])]);
        $a = $this->ngnAccount();
        $this->assertSame('processing', $this->gw()->sendTransfer($this->conversionRequest($a), $a)->status);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/currency/rate')
            && $r->data()['transaction_type'] === 'withdraw' && $r->data()['source_currency'] === 'USD' && $r->data()['destination_currency'] === 'NGN');
        Http::assertSent(function (HttpRequest $r) {
            if (! str_ends_with($r->url(), '/v1/charge/payout')) {
                return false;
            }
            $b = $r->data();

            return $b['source_currency'] === 'USD' && $b['destination_currency'] === 'NGN'
                && $b['source_amount'] > 100.5 && $b['source_amount'] < 101.5   // sized to the promise, inside the 3% cap
                && $b['reference'] === 'NS-GREY-0001';
        });
    }

    public function test_a_rate_that_moved_past_the_cap_sends_nothing(): void
    {
        // Grey now gives only 120,000 NGN per 100 USD -> we would need ~$111.4, above the $103 cap.
        $this->fake(['*/v1/currency/rate' => $this->quote(120000), '*/v1/charge/payout' => Http::response(['data' => ['status' => 'processing']])]);
        $a = $this->ngnAccount();
        $res = $this->gw()->sendTransfer($this->conversionRequest($a), $a);
        $this->assertSame('failed', $res->status);
        $this->assertStringContainsString('rate moved', $res->failureReason);
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/charge/payout'));
    }

    public function test_an_empty_usd_wallet_sends_nothing_and_alerts_the_admin(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\AlertAdminJob::class]);
        $this->fake([
            '*/v1/balances' => Http::response(['data' => ['balances' => [['currency' => 'USD', 'currency_type' => 'fiat', 'available_balance' => 0, 'pending_balance' => 0]]]]),
            '*/v1/currency/rate' => $this->quote(132987.61),
        ]);
        $a = $this->ngnAccount();
        $this->assertSame('failed', $this->gw()->sendTransfer($this->conversionRequest($a), $a)->status);
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/charge/payout'));
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'grey_wallet_short');
    }

    public function test_no_usd_basis_or_no_source_wallet_is_a_clean_failure(): void
    {
        $a = $this->ngnAccount();
        $this->fake([]);
        $this->assertSame('failed', $this->gw()->sendTransfer($this->conversionRequest($a, ['usd_amount' => null]), $a)->status);

        $this->fake(['*/v1/balances' => Http::response(['data' => ['balances' => [['currency' => 'EUR', 'currency_type' => 'fiat', 'available_balance' => 9999]]]])]);
        $r = $this->gw()->sendTransfer($this->conversionRequest($a, ['reference' => 'PO-b', 'provider_reference' => 'NS-B']), $a);
        $this->assertSame('failed', $r->status);
        $this->assertStringContainsString('No Grey wallet', $r->failureReason);
        Http::assertNotSent(fn (HttpRequest $q) => str_ends_with($q->url(), '/v1/charge/payout'));
    }
}
