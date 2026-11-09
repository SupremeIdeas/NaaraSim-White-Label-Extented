<?php

namespace Tests\Feature;

use App\Models\TopupProviderLog;
use App\Services\Topup\Concerns\LogsProviderCalls;
use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\Providers\DingProvider;
use App\Services\Topup\Providers\ReloadlyTopupProvider;
use App\Services\Topup\Providers\VtpassProvider;
use App\Services\Topup\Providers\ZenditTopupProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The four supplier adapters: status mapping (unknown is NEVER failed), request shapes, reference rules, secrets redaction, webhook auth. */
class TopupProvidersTest extends TestCase
{
    use RefreshDatabase;

    private function req(string $sku = 'sku', string $type = 'airtime', bool $fixed = true): PurchaseRequest
    {
        return new PurchaseRequest('REF123456789ABC', '+2348011111111', 'NG', $type, $sku, 1000, 'NGN', $fixed, meta: ['cost_native' => 4.5, 'price_ratio' => 0.001]);
    }

    // ── VTpass ──────────────────────────────────────────────────────────────
    public function test_vtpass_request_id_starts_with_a_lagos_time_stamp(): void
    {
        $ref = (new VtpassProvider)->newReference();
        $this->assertMatchesRegularExpression('/^\d{12}[A-Z0-9]{10}$/', $ref);
        $this->assertSame(now('Africa/Lagos')->format('YmdHi'), substr($ref, 0, 12));
    }

    public function test_vtpass_status_mapping(): void
    {
        $v = new VtpassProvider;
        $tx = fn ($st, $code = '000') => ['code' => $code, 'response_description' => 'x', 'content' => ['transactions' => ['status' => $st, 'transactionId' => '77']]];
        $this->assertSame(PurchaseResult::DELIVERED, $v->map($tx('delivered'), 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $v->map($tx('pending', '099'), 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $v->map($tx('initiated', '099'), 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $v->map($tx('weird-new-state'), 'r')->status, 'an unrecognised state is pending, never failed');
        $this->assertSame(PurchaseResult::PENDING, $v->map([], 'r')->status);
        $f = $v->map($tx('failed', '016'), 'r');
        $this->assertSame(PurchaseResult::FAILED, $f->status);
        $this->assertTrue($f->definitive);
        $this->assertSame(PurchaseResult::REVERSED, $v->map($tx('reversed', '040'), 'r')->status);
        $this->assertSame(PurchaseResult::REVERSED, $v->map(['code' => '040', 'content' => ['transactions' => ['status' => 'delivered']]], 'r')->status, 'code 040 wins');
        $this->assertSame(PurchaseResult::PENDING, $v->map($tx('', '000'), 'r')->status, 'a bare 000 with no inner status is not trusted as delivered');
    }

    public function test_vtpass_airtime_and_data_requests(): void
    {
        config(['services.vtpass.api_key' => 'AK', 'services.vtpass.secret_key' => 'SK', 'services.vtpass.public_key' => 'PK']);
        Http::fake(['*/pay' => Http::response(['code' => '000', 'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => '9']]])]);
        $v = new VtpassProvider;
        $r = $v->purchase($this->req('mtn'));
        $this->assertSame(PurchaseResult::DELIVERED, $r->status);
        Http::assertSent(fn ($q) => str_ends_with($q->url(), '/pay') && $q['serviceID'] === 'mtn' && $q['amount'] === 1000 && $q['phone'] === '08011111111'
            && $q->hasHeader('secret-key', 'SK') && $q->hasHeader('api-key', 'AK'));
        $v->purchase($this->req('mtn-data|mtn-1gb', 'data'));
        Http::assertSent(fn ($q) => ($q['serviceID'] ?? '') === 'mtn-data' && $q['variation_code'] === 'mtn-1gb' && $q['billersCode'] === '08011111111');
    }

    public function test_vtpass_network_error_and_5xx_are_pending_not_failed(): void
    {
        config(['services.vtpass.api_key' => 'AK', 'services.vtpass.secret_key' => 'SK']);
        Http::fake(['*' => Http::response('boom', 502)]);
        $this->assertSame(PurchaseResult::PENDING, (new VtpassProvider)->purchase($this->req('mtn'))->status);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->assertSame(PurchaseResult::PENDING, (new VtpassProvider)->purchase($this->req('mtn'))->status);
    }

    public function test_vtpass_webhook_needs_the_secret(): void
    {
        config(['services.vtpass.webhook_secret' => 'S3CR3T']);
        $body = ['type' => 'transaction-update', 'data' => ['code' => '000', 'status' => 'delivered', 'requestId' => 'RQ1', 'transactionId' => '5']];
        $bad = Request::create('/webhooks/topup/vtpass', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
        $this->assertNull((new VtpassProvider)->parseWebhook($bad));
        $ok = Request::create('/webhooks/topup/vtpass?secret=S3CR3T', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
        $r = (new VtpassProvider)->parseWebhook($ok);
        $this->assertSame('RQ1', $r->reference);
        $this->assertSame(PurchaseResult::DELIVERED, $r->status);
    }

    // ── Reloadly ────────────────────────────────────────────────────────────
    public function test_reloadly_status_mapping_and_topup_request(): void
    {
        $r = new ReloadlyTopupProvider;
        $this->assertSame(PurchaseResult::DELIVERED, $r->map(['status' => 'SUCCESSFUL', 'transactionId' => 5, 'balanceInfo' => ['cost' => 7.5, 'currencyCode' => 'NGN']], 'x')->status);
        $this->assertSame(PurchaseResult::PENDING, $r->map(['status' => 'PROCESSING'], 'x')->status);
        $this->assertSame(PurchaseResult::PENDING, $r->map(['status' => 'SOMETHING_NEW'], 'x')->status);
        $this->assertSame(PurchaseResult::FAILED, $r->map(['status' => 'REFUNDED'], 'x')->status);

        config(['services.reloadly.client_id' => 'cid', 'services.reloadly.client_secret' => 'sec']);
        Http::fake([
            'auth.reloadly.com/*' => Http::response(['access_token' => 'TOK', 'expires_in' => 86400]),
            '*/topups' => Http::response(['transactionId' => 33469, 'status' => 'SUCCESSFUL', 'balanceInfo' => ['cost' => 790.4, 'currencyCode' => 'NGN']]),
        ]);
        $res = $r->purchase(new PurchaseRequest('nt-abc', '+233244000000', 'GH', 'airtime', '1100|10', 10, 'GHS', true));
        $this->assertSame(PurchaseResult::DELIVERED, $res->status);
        Http::assertSent(fn ($q) => str_ends_with($q->url(), '/topups') && $q['customIdentifier'] === 'nt-abc' && $q['operatorId'] === 1100
            && $q['recipientPhone']['countryCode'] === 'GH' && $q['recipientPhone']['number'] === '244000000' && $q->hasHeader('Authorization', 'Bearer TOK'));
    }

    public function test_reloadly_a_4xx_is_a_definitive_failure_and_a_5xx_is_pending(): void
    {
        config(['services.reloadly.client_id' => 'cid', 'services.reloadly.client_secret' => 'sec']);
        $topup = Http::response(['message' => 'Invalid amount'], 400);
        Http::fake(function ($q) use (&$topup) { return str_contains($q->url(), "auth.reloadly.com") ? Http::response(["access_token" => "T", "expires_in" => 86400]) : $topup; });
        $f = (new ReloadlyTopupProvider)->purchase(new PurchaseRequest('a', '+233244000000', 'GH', 'airtime', '1|1', 1, 'GHS'));
        $this->assertSame(PurchaseResult::FAILED, $f->status);
        $this->assertTrue($f->definitive);
        $topup = Http::response('x', 503);
        $this->assertSame(PurchaseResult::PENDING, (new ReloadlyTopupProvider)->purchase(new PurchaseRequest('a', '+233244000000', 'GH', 'airtime', '1|1', 1, 'GHS'))->status);
    }

    // ── Ding ────────────────────────────────────────────────────────────────
    public function test_ding_status_mapping(): void
    {
        $d = new DingProvider;
        $rec = fn ($s) => ['TransferRecord' => ['ProcessingState' => $s, 'TransferId' => ['TransferRef' => 'T1'], 'Price' => ['SendValue' => 4.5, 'SendCurrencyIso' => 'USD'], 'ReceiptText' => 'PIN 1234'], 'ResultCode' => 1, 'ErrorCodes' => []];
        $this->assertSame(PurchaseResult::DELIVERED, $d->map($rec('Complete'), 'r')->status);
        $this->assertSame('PIN 1234', $d->map($rec('Complete'), 'r')->receiptText);
        $this->assertSame(PurchaseResult::PENDING, $d->map($rec('Submitted'), 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $d->map($rec('Brand-new-state'), 'r')->status);
        $this->assertSame(PurchaseResult::FAILED, $d->map($rec('Failed'), 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $d->map(['ErrorCodes' => [['Code' => 'ProviderTimedOut']]], 'r')->status, 'a timeout is requeried, not failed');
        $nothingSent = $d->map(['ErrorCodes' => [['Code' => 'AccountNumberInvalid']]], 'r');
        $this->assertSame(PurchaseResult::FAILED, $nothingSent->status);
        $this->assertTrue($nothingSent->definitive);
        $this->assertNull($d->parseWebhook(Request::create('/x', 'POST')), 'Ding has no webhook');
    }

    public function test_ding_send_transfer_shape_for_fixed_and_range(): void
    {
        config(['services.ding.api_key' => 'DK']);
        Http::fake(['*/SendTransfer' => Http::response(['TransferRecord' => ['ProcessingState' => 'Complete'], 'ErrorCodes' => []])]);
        $d = new DingProvider;
        $d->purchase(new PurchaseRequest('nt-1', '+233244000000', 'GH', 'airtime', 'GH_XX_TopUp', 10, 'GHS', false));
        Http::assertSent(fn ($q) => $q['SkuCode'] === 'GH_XX_TopUp' && $q['ReceiveValue'] == 10 && $q['SendValue'] == 0 && $q['DistributorRef'] === 'nt-1' && $q['ValidateOnly'] === false && $q->hasHeader('api_key', 'DK'));
        $d->purchase(new PurchaseRequest('nt-2', '+233244000000', 'GH', 'data', 'SKU2', 10, 'GHS', true, meta: ['cost_native' => 4.5]));
        Http::assertSent(fn ($q) => ($q['DistributorRef'] ?? '') === 'nt-2' && $q['SendValue'] == 4.5 && $q['ReceiveValue'] == 0, 'SendValue and ReceiveValue are never both non-zero');
    }

    // ── Zendit ──────────────────────────────────────────────────────────────
    public function test_zendit_maps_a_real_shaped_offer_list_and_a_range_offer(): void
    {
        config(['services.zendit.api_key' => 'k', 'services.zendit.base_url' => 'https://api.zendit.io/v1']);
        $fixed = ['enabled' => true, 'offerId' => '9MOBILE_NG_01-I-DATA', 'country' => 'NG', 'brand' => 'NineMobile', 'brandName' => 'Nine Mobile', 'subTypes' => ['Mobile Data'], 'shortNotes' => '2 GB (30 Days)',
            'priceType' => 'FIXED', 'send' => ['currency' => 'NGN', 'currencyDivisor' => 100, 'fixed' => 100000], 'price' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fixed' => 100],
            'cost' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fixed' => 74, 'fx' => 1351.35], 'durationDays' => 30, 'dataGB' => 2, 'dataUnlimited' => false];
        $range = ['enabled' => true, 'offerId' => 'AIRTELTIGO_GH_OPEN', 'country' => 'GH', 'brand' => 'AirtelTigo', 'brandName' => 'AirtelTigo', 'subTypes' => ['Mobile Top Up'], 'priceType' => 'RANGE',
            'send' => ['currency' => 'GHS', 'currencyDivisor' => 100, 'min' => 100, 'max' => 30000, 'fx' => 1], 'price' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fx' => 9.557184],
            'cost' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fx' => 11.01895735]];
        Http::fake(['*/topups/offers*' => Http::response(['total' => 2, 'list' => [$fixed, $range]])]);
        $out = (new ZenditTopupProvider)->listProducts('NG');
        $this->assertCount(2, $out);
        [$d, $r] = $out;
        $this->assertSame('data', $d->type);
        $this->assertEqualsWithDelta(1000, $d->fixedAmount, 0.001);
        $this->assertEqualsWithDelta(0.74, $d->costNative, 0.001);
        $this->assertSame(2048, $d->dataMb);
        $this->assertSame('30 days', $d->validityText);
        $this->assertSame('airtime', $r->type);
        $this->assertFalse($r->isFixed);
        $this->assertEqualsWithDelta(1.0, $r->minAmount, 0.001);
        $this->assertEqualsWithDelta(300.0, $r->maxAmount, 0.001);
        $this->assertEqualsWithDelta(1 / 11.01895735, $r->costRatio, 0.00001);
    }

    public function test_zendit_status_mapping_and_purchase_body(): void
    {
        config(['services.zendit.api_key' => 'k', 'services.zendit.base_url' => 'https://api.zendit.io/v1']);
        $z = 'DONE';
        Http::fake(function () use (&$z) {
            return Http::response(['status' => $z, 'transactionId' => 'T']);
        });
        foreach ([['DONE', 'delivered'], ['FAILED', 'failed'], ['PENDING', 'pending'], ['ACCEPTED', 'pending'], ['AUTHORIZED', 'pending'], ['IN_PROGRESS', 'pending'], ['NEW_THING', 'pending']] as [$z, $ours]) {
            $this->assertSame($ours, (new ZenditTopupProvider)->purchase($this->req('OFFER'))->status, $z);
        }
        Http::fake(['*/topups/purchases' => Http::response(['status' => 'PENDING', 'transactionId' => 'nt-1'])]);
        (new ZenditTopupProvider)->purchase($this->req('OFFER'));
        Http::assertSent(fn ($q) => $q['offerId'] === 'OFFER' && $q['recipientPhoneNumber'] === '+2348011111111' && $q['transactionId'] === 'REF123456789ABC' && ! isset($q['value']));
        (new ZenditTopupProvider)->purchase($this->req('OFFER', 'airtime', false));
        Http::assertSent(fn ($q) => isset($q['value']) && $q['value']['type'] === 'PRICE');
    }

    public function test_zendit_a_4xx_asks_before_declaring_failure(): void
    {
        config(['services.zendit.api_key' => 'k', 'services.zendit.base_url' => 'https://api.zendit.io/v1']);
        Http::fake(['*/topups/purchases' => Http::response(['message' => 'duplicate'], 400), '*/topups/purchases/*' => Http::response(['status' => 'DONE', 'transactionId' => 'REF123456789ABC'])]);
        $this->assertSame(PurchaseResult::DELIVERED, (new ZenditTopupProvider)->purchase($this->req('OFFER'))->status, 'the earlier order is found, not refunded');
    }

    // ── Logging ─────────────────────────────────────────────────────────────
    public function test_provider_calls_are_logged_with_secrets_redacted(): void
    {
        config(['services.vtpass.api_key' => 'AK', 'services.vtpass.secret_key' => 'TOPSECRETVALUE']);
        Http::fake(['*' => Http::response(['code' => '000', 'access_token' => 'LEAKYTOKEN12345', 'content' => ['transactions' => ['status' => 'delivered']]])]);
        (new VtpassProvider)->purchase($this->req('mtn'));
        $log = TopupProviderLog::first();
        $this->assertSame('vtpass', $log->provider_code);
        $this->assertSame('REF123456789ABC', $log->reference);
        $this->assertStringNotContainsString('LEAKYTOKEN12345', $log->response);
        $this->assertStringNotContainsString('TOPSECRETVALUE', (string) $log->request);
        $this->assertSame(['api-key' => '[redacted]', 'x' => 1], LogsProviderCallsProbe::redact(['api-key' => 'abc', 'x' => 1]));
    }
}

/** Exposes the trait's static redactor. */
class LogsProviderCallsProbe
{
    use LogsProviderCalls;

    public function code(): string
    {
        return 'probe';
    }
}
