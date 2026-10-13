<?php

namespace Tests\Feature;

use App\Exceptions\EsimProviderException;
use App\Services\eSIM\EsimGoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsimGoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.esimgo.base_url' => 'https://api.esim-go.com/v2.5',
            'services.esimgo.api_key' => 'test-key',
            'services.esimgo.sandbox' => true,
        ]);
    }

    public function test_order_bundle_posts_a_v2_5_transaction_and_returns_the_new_esim(): void
    {
        Http::fake([
            'api.esim-go.com/v2.5/orders' => Http::response(['orderReference' => 'ORD-1', 'status' => 'completed', 'total' => 4.2]),
            'api.esim-go.com/v2.5/esims/assignments*' => Http::response([['iccid' => '8944000000000000001', 'matchingId' => 'ABCD-1234', 'rspUrl' => 'smdp.io']]),
        ]);

        $out = app(EsimGoService::class)->orderBundle('esim_1GB_US_30D', 1, null);

        $this->assertSame('ORD-1', $out['orderReference']);
        $this->assertSame('8944000000000000001', $out['iccid']);
        $this->assertSame('LPA:1$smdp.io$ABCD-1234', \App\Support\Niche\LpaActivation::fromPayload($out), 'the checkout can build the QR / manual code from the payload');
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.esim-go.com/v2.5/orders'
                && $request->method() === 'POST'
                && $request->hasHeader('X-API-Key', 'test-key')
                && $request->hasHeader('x-sandbox', 'on')
                && $request['type'] === 'transaction'
                && $request['assign'] === true
                && $request['Order'][0]['type'] === 'bundle'
                && $request['Order'][0]['item'] === 'esim_1GB_US_30D'
                && $request['Order'][0]['quantity'] === 1
                && ! isset($request['Order'][0]['iccids']);
        });
    }

    public function test_a_top_up_targets_the_given_iccid_and_needs_no_assignment_lookup(): void
    {
        Http::fake(['api.esim-go.com/v2.5/orders' => Http::response(['orderReference' => 'ORD-2', 'status' => 'completed'])]);

        $out = app(EsimGoService::class)->orderBundle('esim_1GB_US_30D', 1, '8944000000000000009');

        $this->assertSame('8944000000000000009', $out['iccid']);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.esim-go.com/v2.5/orders' && $r['Order'][0]['iccids'] === ['8944000000000000009']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'assignments'));
    }

    public function test_an_order_that_is_not_completed_throws_so_the_router_fails_over(): void
    {
        Http::fake(['api.esim-go.com/v2.5/orders' => Http::response(['orderReference' => 'ORD-3', 'status' => 'failed', 'statusMessage' => 'No stock'])]);
        try {
            app(EsimGoService::class)->orderBundle('x', 1);
            $this->fail('A failed order was accepted');
        } catch (EsimProviderException $e) {
            $this->assertStringContainsString('No stock', $e->getMessage());
        }

    }

    public function test_a_completed_order_whose_esim_cannot_be_read_throws_with_the_order_reference(): void
    {
        Http::fake([
            'api.esim-go.com/v2.5/orders' => Http::response(['orderReference' => 'ORD-4', 'status' => 'completed']),
            'api.esim-go.com/v2.5/esims/assignments*' => Http::response([], 404),
        ]);
        $this->expectException(EsimProviderException::class);
        $this->expectExceptionMessage('ORD-4');
        app(EsimGoService::class)->orderBundle('x', 1);
    }

    public function test_the_assignment_can_also_arrive_as_csv(): void
    {
        Http::fake([
            'api.esim-go.com/v2.5/orders' => Http::response(['orderReference' => 'ORD-5', 'status' => 'completed']),
            'api.esim-go.com/v2.5/esims/assignments*' => Http::response("iccid,matchingId,rspUrl\n8944111,XYZ-9,smdp.example.com\n", 200, ['Content-Type' => 'text/csv']),
        ]);

        $out = app(EsimGoService::class)->orderBundle('x', 1);

        $this->assertSame('8944111', $out['iccid']);
        $this->assertSame('LPA:1$smdp.example.com$XYZ-9', \App\Support\Niche\LpaActivation::fromPayload($out));
    }

    public function test_the_catalogue_walks_every_page_instead_of_stopping_at_the_first(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $page = (int) ($q['page'] ?? 1);

            return Http::response(['bundles' => [['name' => "b{$page}a"], ['name' => "b{$page}b"]], 'pageCount' => 3, 'rows' => 6, 'pageSize' => 2]);
        });

        $cat = app(EsimGoService::class)->getCatalogue();

        $this->assertSame(['b1a', 'b1b', 'b2a', 'b2b', 'b3a', 'b3b'], array_column($cat['bundles'], 'name'));
        Http::assertSentCount(3);
    }

    public function test_get_balance_reads_organisation_balance(): void
    {
        Http::fake([
            'api.esim-go.com/v2.5/organisation' => Http::response(['organisations' => [['balance' => 137.5, 'tier' => 'gold']]]),
        ]);

        $this->assertSame(137.5, app(EsimGoService::class)->getBalance());
    }

    public function test_sandbox_header_absent_when_disabled(): void
    {
        config(['services.esimgo.sandbox' => false]);
        Http::fake(['*' => Http::response([])]);

        app(EsimGoService::class)->getCatalogue();

        Http::assertSent(fn ($request) => ! $request->hasHeader('x-sandbox'));
    }

    public function test_a_failed_http_call_throws_a_typed_provider_exception(): void
    {
        Http::fake(['api.esim-go.com/v2.5/catalogue' => Http::response(['message' => 'unauthorized'], 401)]);

        $this->expectException(EsimProviderException::class);

        app(EsimGoService::class)->getCatalogue();
    }
}
