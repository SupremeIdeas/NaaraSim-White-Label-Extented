<?php

namespace Tests\Feature;

use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\Providers\PagaTopupProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Paga Business API as a Nigeria airtime/data supplier: signed requests, product mapping, delivered/pending/failed mapping, idempotent reference. */
class PagaTopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('paga.operators');
        config(['services.paga.public_key' => 'pub-id', 'services.paga.secret_key' => 'sec', 'services.paga.hash_key' => 'hk', 'services.paga.base_url' => 'https://beta-checkout.paga.com', 'services.paga.business_url' => null, 'services.paga.airtime_discount_pct' => 2]);
    }

    private function req(string $sku = 'air|OP-MTN', string $type = 'airtime', float $amount = 500): PurchaseRequest
    {
        return new PurchaseRequest('ref123', '+2348031234567', 'NG', $type, $sku, $amount, 'NGN', $type === 'data');
    }

    public function test_requests_are_signed_with_principal_credentials_and_the_documented_hash_order_on_the_beta_host(): void
    {
        $seen = null;
        Http::fake(['*' => function ($r) use (&$seen) { $seen = $r; return Http::response(['responseCode' => 0, 'transactionStatus' => 'SUCCESSFUL', 'transactionId' => 'T1']); }]);
        (new PagaTopupProvider)->purchase($this->req());
        $first = collect(Http::recorded())->first(fn ($p) => str_contains($p[0]->url(), 'airtimePurchase'))[0];
        $this->assertStringStartsWith('https://beta.mypaga.com/paga-webservices/business-rest/secured/airtimePurchase', $first->url());
        $this->assertSame('pub-id', $first->header('principal')[0]);
        $this->assertSame('sec', $first->header('credentials')[0]);
        $this->assertSame(hash('sha512', 'ref123'.'500'.'08031234567'.'hk'), $first->header('hash')[0]);
        $this->assertSame('08031234567', $first['destinationPhoneNumber']);
        $this->assertFalse($first['isDataBundle']);
    }

    public function test_live_mode_uses_the_live_host(): void
    {
        config(['services.paga.base_url' => 'https://checkout.paga.com']);
        Http::fake(['*' => Http::response(['responseCode' => 0, 'transactionStatus' => 'SUCCESSFUL'])]);
        (new PagaTopupProvider)->requery('r1');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://www.mypaga.com/'));
    }

    public function test_products_map_airtime_ranges_and_data_bundles_with_private_cost(): void
    {
        Http::fake([
            '*getMobileOperators' => Http::response(['responseCode' => 0, 'mobileOperator' => [['name' => 'MTN', 'mobileOperatorCode' => 'U-MTN'], ['name' => 'Airtel', 'mobileOperatorCode' => 'U-AIR']]]),
            '*getDataBundleByOperator' => Http::response(['responseCode' => 0, 'mobileOperatorServices' => [['serviceId' => 70, 'servicePrice' => 1000.0, 'serviceName' => 'MTN 1.5GB 30 days (1000)']]]),
        ]);
        $p = collect((new PagaTopupProvider)->listProducts('NG'));
        $air = $p->firstWhere('providerSku', 'air|U-MTN');
        $this->assertFalse($air->isFixed);
        $this->assertSame(0.98, $air->costRatio);
        $d = $p->firstWhere('providerSku', 'data|U-MTN|70');
        $this->assertSame(1000.0, $d->fixedAmount);
        $this->assertSame(980.0, $d->costNative);
        $this->assertSame(1536, $d->dataMb);
        $this->assertSame('30 days', $d->validityText);
        $this->assertCount(0, (new PagaTopupProvider)->listProducts('GH'));
    }

    public function test_a_data_purchase_sends_the_service_id_and_flag(): void
    {
        Http::fake(['*' => Http::response(['responseCode' => 0, 'transactionStatus' => 'SUCCESSFUL', 'transactionId' => 'D1'])]);
        $r = (new PagaTopupProvider)->purchase($this->req('data|U-MTN|70', 'data', 1000));
        $this->assertSame(PurchaseResult::DELIVERED, $r->status);
        Http::assertSent(fn ($q) => str_contains($q->url(), 'airtimePurchase') && $q['mobileOperatorServiceId'] === '70' && $q['isDataBundle'] === true);
    }

    public function test_accepted_but_unconfirmed_stays_pending_and_never_assumes_delivery(): void
    {
        Http::fake(['*airtimePurchase' => Http::response(['responseCode' => 0, 'transactionId' => 'X']), '*transactionStatus' => Http::response([], 500)]);
        $this->assertSame(PurchaseResult::PENDING, (new PagaTopupProvider)->purchase($this->req())->status);
    }

    public function test_definitive_failures_pending_codes_and_network_errors(): void
    {
        $answer = null;
        Http::fake(['*' => function () use (&$answer) {
            if ($answer instanceof \Throwable) {
                throw $answer;
            }

            return is_array($answer) ? Http::response($answer) : Http::response('oops', 502);
        }]);
        $p = new PagaTopupProvider;

        $answer = ['responseCode' => 139, 'message' => 'Insufficient Balance'];
        $r = $p->purchase($this->req());
        $this->assertSame(PurchaseResult::FAILED, $r->status);
        $this->assertTrue($r->definitive);

        $answer = ['responseCode' => 3, 'message' => 'Pending'];
        $this->assertSame(PurchaseResult::PENDING, $p->purchase($this->req())->status);

        $answer = ['responseCode' => 105, 'message' => 'Duplicate reference'];
        $d = $p->purchase($this->req());
        $this->assertSame(PurchaseResult::PENDING, $d->status);
        $this->assertFalse($d->definitive);

        $answer = null;
        $this->assertSame(PurchaseResult::PENDING, $p->purchase($this->req())->status);

        $answer = new \Illuminate\Http\Client\ConnectionException('timeout');
        $this->assertSame(PurchaseResult::PENDING, $p->purchase($this->req())->status);
    }

    public function test_status_mapping(): void
    {
        $p = new PagaTopupProvider;
        $this->assertSame(PurchaseResult::DELIVERED, $p->map(['responseCode' => 0, 'transactionStatus' => 'SUCCESSFUL'], 'r')->status);
        $this->assertSame(PurchaseResult::FAILED, $p->map(['responseCode' => 0, 'transactionStatus' => 'FAILED'], 'r')->status);
        $this->assertSame(PurchaseResult::REVERSED, $p->map(['responseCode' => 0, 'transactionStatus' => 'REVERSED'], 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $p->map(['responseCode' => 135], 'r')->status);
    }

    public function test_unconfigured_without_all_three_keys_and_registered_for_nigeria(): void
    {
        config(['services.paga.hash_key' => null]);
        $this->assertFalse((new PagaTopupProvider)->configured());
        $this->assertArrayHasKey('paga', \App\Services\Topup\TopupProviders::MAP);
    }
}
