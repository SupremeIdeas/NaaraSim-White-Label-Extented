<?php

namespace Tests\Feature;

use App\Services\GiftCards\BitrefillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bitrefill (MASTER-ONLY): a Bitcoin balance is valued in dollars at Bitrefill's own live rate, a USD balance is exact, and only plain gift cards are sold. */
class BitrefillBalanceAndCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bitrefill.api_key' => 'k', 'services.bitrefill.base_url' => 'https://api.bitrefill.com/v2']);
        Cache::flush();
    }

    private function products(): array
    {
        return ['meta' => ['total' => 4], 'data' => [
            ['id' => 'amazon-usa', 'name' => 'Amazon', 'currency' => 'USD', 'recipient_type' => 'none', 'packages' => [['value' => '25', 'price' => 33564]], 'range' => ['min' => 5, 'max' => 500, 'price_rate' => 1342.5], 'categories' => ['shopping']],
            ['id' => 'xl-indonesia', 'name' => 'XL', 'currency' => 'IDR', 'recipient_type' => 'phone_number', 'packages' => [['value' => '10000', 'price' => 100]], 'categories' => ['refill']],
            ['id' => 'bitrefill-esim-europe', 'name' => 'eSIM', 'currency' => 'USD', 'recipient_type' => 'mixed', 'packages' => [['value' => '10', 'price' => 13000]], 'categories' => ['esim']],
            ['id' => 'steam-usa', 'name' => 'Steam', 'currency' => 'USD', 'recipient_type' => 'none', 'packages' => [['value' => '20', 'price' => 26850]], 'categories' => ['games']],
        ]];
    }

    public function test_only_plain_gift_cards_are_sold(): void
    {
        Http::fake(['api.bitrefill.com/v2/products*' => Http::response($this->products())]);
        $ids = array_column(app(BitrefillService::class)->getCatalogue(), 'provider_product_id');
        $this->assertSame(['amazon-usa', 'steam-usa'], $ids);
    }

    public function test_a_bitcoin_balance_is_valued_in_dollars_at_bitrefills_rate(): void
    {
        Http::fake(['api.bitrefill.com/v2/accounts/balance*' => Http::response(['data' => ['balance' => 0.001, 'currency' => 'BTC']]), 'api.bitrefill.com/v2/products*' => Http::response($this->products())]);
        $d = app(BitrefillService::class)->balanceDetail();
        $this->assertSame('BTC', $d['currency']);
        $this->assertEqualsWithDelta(74.49, $d['usd'], 0.05, '0.001 BTC at 1342.5 sats per dollar');
        $this->assertEqualsWithDelta(74.49, app(BitrefillService::class)->getBalance(), 0.05);
    }

    public function test_a_usd_balance_is_exact_and_another_currency_is_not_counted(): void
    {
        Http::fake(['api.bitrefill.com/v2/accounts/balance*' => Http::sequence()->push(['data' => ['balance' => 120.5, 'currency' => 'USD']])->push(['data' => ['balance' => 50, 'currency' => 'EUR']])]);
        $this->assertSame(120.5, app(BitrefillService::class)->getBalance());
        $this->assertSame(0.0, app(BitrefillService::class)->getBalance(), '50 EUR is not read as 50 dollars');
    }

    public function test_a_429_is_retried_so_the_sync_still_finishes(): void
    {
        Http::fake(['api.bitrefill.com/v2/products*' => Http::sequence()->push(['message' => 'rate_limit_reached'], 429, ['ratelimit-reset' => '2'])->push($this->products())]);
        $this->assertCount(2, app(BitrefillService::class)->getCatalogue());
    }

    /** The real v2 reply nests the invoice under `data`; the code is on the order, read with GET /orders/{id} as `redemption_info`. */
    public function test_a_paid_invoice_is_delivered_with_the_code_from_the_order(): void
    {
        Http::fake([
            'api.bitrefill.com/v2/invoices' => Http::response(['meta' => [], 'data' => ['id' => 'inv-1', 'status' => 'complete', 'payment' => ['method' => 'balance', 'status' => 'paid'], 'orders' => [['id' => 'ord-1', 'status' => 'created']]]]),
            'api.bitrefill.com/v2/orders/ord-1' => Http::response(['data' => ['id' => 'ord-1', 'status' => 'delivered', 'redemption_info' => ['code' => 'ABCD-1234', 'pin' => '9876', 'instructions' => 'Redeem at example.com']]]),
        ]);
        $r = app(BitrefillService::class)->order('amazon-usa', 25.0, 'USD', [], 'giftcard:1:2:2500:1');
        $this->assertSame('inv-1', $r['provider_tx_id']);
        $this->assertSame('delivered', $r['status']);
        $this->assertSame('ABCD-1234', $r['receipt']['code']);
        $this->assertSame('9876', $r['receipt']['epin']);
        $this->assertSame('Redeem at example.com', $r['receipt']['instructions']);
    }

    public function test_an_unpaid_or_unfinished_invoice_stays_processing_and_a_failed_one_fails(): void
    {
        $svc = app(BitrefillService::class);
        $this->assertSame('processing', $svc->resolveInvoice(['status' => 'not_delivered', 'orders' => [['id' => 'o']]])['status']);
        $this->assertSame('failed', $svc->resolveInvoice(['status' => 'payment_error'])['status']);
        Http::fake(['api.bitrefill.com/v2/orders/o2' => Http::response(['data' => ['id' => 'o2', 'status' => 'failed']])]);
        $this->assertSame('failed', $svc->resolveInvoice(['status' => 'complete', 'orders' => [['id' => 'o2']]])['status'], 'complete is not the same as every order succeeding');
        Http::fake(['api.bitrefill.com/v2/orders/o3' => Http::response(['data' => ['id' => 'o3', 'status' => 'created']])]);
        $this->assertSame('processing', $svc->resolveInvoice(['status' => 'complete', 'orders' => [['id' => 'o3']]])['status'], 'no code yet: ask again later');
    }

    public function test_the_reconcile_poll_reads_the_nested_invoice(): void
    {
        Http::fake([
            'api.bitrefill.com/v2/invoices/inv-9' => Http::response(['data' => ['id' => 'inv-9', 'status' => 'complete', 'orders' => [['id' => 'ord-9']]]]),
            'api.bitrefill.com/v2/orders/ord-9' => Http::response(['data' => ['status' => 'delivered', 'redemption_info' => ['code' => 'ZZZ']]]),
        ]);
        $r = app(BitrefillService::class)->orderStatus('inv-9');
        $this->assertSame(['delivered', 'ZZZ'], [$r['status'], $r['receipt']['code']]);
    }
}
