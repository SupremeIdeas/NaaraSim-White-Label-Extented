<?php

namespace Tests\Feature;

use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\Providers\BitrefillTopupProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bitrefill phone refills as a top-up supplier: product mapping (fixed, data, range), lookup, purchase and the delivered/pending/failed mapping. */
class BitrefillTopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bitrefill.api_key' => 'tok', 'services.bitrefill.base_url' => 'https://api.bitrefill.com/v2']);
    }

    public function test_products_map_to_fixed_airtime_data_bundles_and_a_range_with_private_cost(): void
    {
        Http::fake(['*/products*' => Http::response(['meta' => ['total' => 1], 'data' => [[
            'id' => 'mtn-nigeria', 'name' => 'MTN Nigeria', 'currency' => 'NGN', 'in_stock' => true, 'categories' => ['refill'],
            'packages' => [['id' => 'mtn-nigeria<&>1000', 'value' => 1000, 'price' => 993], ['id' => 'mtn-nigeria<&>2GB', 'value' => '2GB, 30 Days', 'price' => 2.5]],
            'range' => ['min' => 100, 'max' => 50000, 'step' => 50, 'price_rate' => 0.9925],
        ]]])]);

        $p = collect((new BitrefillTopupProvider)->listProducts('NG'));

        $fixed = $p->firstWhere('providerSku', 'mtn-nigeria<&>1000');
        $this->assertSame('airtime', $fixed->type);
        $this->assertSame(1000.0, $fixed->fixedAmount);
        $this->assertSame(993.0, $fixed->costNative);
        $this->assertSame('NGN', $fixed->costCurrency);   // package prices are in the PRODUCT currency (live API: NGN 993 for a NGN 1000 refill), never USD
        $data = $p->firstWhere('providerSku', 'mtn-nigeria<&>2GB');
        $this->assertSame('data', $data->type);
        $this->assertSame(2048, $data->dataMb);
        $range = $p->firstWhere('providerSku', 'mtn-nigeria');
        $this->assertFalse($range->isFixed);
        $this->assertSame(0.9925, $range->costRatio);
        $this->assertSame('NGN', $range->costCurrency);
        $this->assertSame(100.0, $range->minAmount);
    }

    public function test_non_refill_brands_in_the_country_listing_are_never_sold_as_top_ups(): void
    {
        Http::fake(['*/products*' => Http::response(['meta' => ['total' => 3], 'data' => [
            ['id' => 'spar-market-nigeria', 'name' => 'Spar Market Nigeria', 'currency' => 'NGN', 'in_stock' => true, 'categories' => ['food', 'groceries'], 'packages' => [['id' => 's<&>500', 'value' => 500, 'price' => 490]]],
            ['id' => 'gotv-bill-nigeria', 'name' => 'GoTV Bill Nigeria', 'currency' => 'NGN', 'in_stock' => true, 'categories' => ['bill'], 'packages' => [['id' => 'g<&>1900', 'value' => 1900, 'price' => 1963]]],
            ['id' => 'airtel-nigeria', 'name' => 'Airtel Nigeria', 'currency' => 'NGN', 'in_stock' => true, 'categories' => ['refill'], 'packages' => [['id' => 'a<&>500', 'value' => 500, 'price' => 497]]],
        ]])]);

        $p = collect((new BitrefillTopupProvider)->listProducts('NG'));

        $this->assertSame(['Airtel Nigeria'], $p->pluck('operatorName')->unique()->values()->all());
    }

    public function test_lookup_returns_the_operator_only_when_it_is_certain(): void
    {
        $answer = ['operator_found' => true, 'data' => ['id' => 'mtn-nigeria', 'name' => 'MTN Nigeria', 'country_code' => 'NG']];
        Http::fake(['*/check_phone_number*' => function () use (&$answer) { return Http::response($answer); }]);
        $r = (new BitrefillTopupProvider)->lookupRecipient('+2348031234567');
        $this->assertSame('MTN Nigeria', $r->operatorName);
        $this->assertSame('NG', $r->countryIso);

        $answer = ['operator_found' => false, 'data' => [['id' => 'a'], ['id' => 'b']]];
        $this->assertNull((new BitrefillTopupProvider)->lookupRecipient('+2348031234567'));
    }

    public function test_purchase_creates_a_balance_paid_invoice_with_the_phone_number(): void
    {
        Http::fake(['*/invoices' => Http::response(['data' => ['id' => 'inv9', 'status' => 'complete', 'payment' => ['price' => 0.72], 'orders' => [['id' => 'o', 'status' => 'delivered']]]])]);

        $res = (new BitrefillTopupProvider)->purchase(new PurchaseRequest('nt-1', '+2348031234567', 'NG', 'airtime', 'mtn-nigeria<&>1000', 1000, 'NGN', true, 'mtn-nigeria', meta: ['product_id' => 'mtn-nigeria']));

        $this->assertSame(PurchaseResult::DELIVERED, $res->status);
        $this->assertSame('inv9', $res->providerTxnId);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['payment_method'] === 'balance' && $r['auto_pay'] === true
            && $r['products'][0]['phone_number'] === '+2348031234567' && $r['products'][0]['package_id'] === 'mtn-nigeria<&>1000' && $r['products'][0]['product_id'] === 'mtn-nigeria');
    }

    public function test_a_range_purchase_sends_the_value_and_a_refusal_is_definitive_while_a_server_error_is_pending(): void
    {
        $status = 400;
        $body = ['error_code' => 'balance_too_low', 'message' => 'Insufficient balance'];
        Http::fake(['*/invoices' => function () use (&$body, &$status) { return Http::response($body, $status); }]);
        $req = new PurchaseRequest('nt-2', '+2348031234567', 'NG', 'airtime', 'mtn-nigeria', 5000, 'NGN', false, 'mtn-nigeria', meta: ['product_id' => 'mtn-nigeria', 'range' => true]);
        $r = (new BitrefillTopupProvider)->purchase($req);
        $this->assertSame(PurchaseResult::FAILED, $r->status);
        $this->assertTrue($r->definitive);
        Http::assertSent(fn ($x) => $x['products'][0]['value'] === 5000.0 && ! isset($x['products'][0]['package_id']));

        $status = 503;
        $body = [];
        $this->assertSame(PurchaseResult::PENDING, (new BitrefillTopupProvider)->purchase($req)->status);
    }

    public function test_invoice_states_map_safely_and_unknown_is_never_failed(): void
    {
        $p = new BitrefillTopupProvider;
        $this->assertSame(PurchaseResult::DELIVERED, $p->map_invoice(['id' => 'i', 'status' => 'complete', 'orders' => [['status' => 'delivered']]], 'r')->status);
        $this->assertSame(PurchaseResult::FAILED, $p->map_invoice(['id' => 'i', 'status' => 'complete', 'orders' => [['status' => 'failed']]], 'r')->status);
        $this->assertTrue($p->map_invoice(['id' => 'i', 'status' => 'denied', 'orders' => []], 'r')->definitive);
        $this->assertSame(PurchaseResult::PENDING, $p->map_invoice(['id' => 'i', 'status' => 'pending', 'orders' => [['status' => 'created']]], 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $p->map_invoice(['id' => 'i', 'status' => 'something_new'], 'r')->status);
        $this->assertSame(PurchaseResult::PENDING, $p->requery('r', null)->status);
    }
}
