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

    public function test_order_bundle_hits_v2_5_orders_with_auth_and_sandbox_headers(): void
    {
        Http::fake([
            'api.esim-go.com/v2.5/orders' => Http::response([
                'orderReference' => 'ORD-1', 'status' => 'completed', 'total_price' => 4.2,
            ]),
        ]);

        $out = app(EsimGoService::class)->orderBundle('esim_1GB_US_30D', 1, null);

        $this->assertSame('ORD-1', $out['orderReference']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.esim-go.com/v2.5/orders'
                && $request->method() === 'POST'
                && $request->hasHeader('X-API-Key', 'test-key')
                && $request->hasHeader('x-sandbox', 'on')
                && $request['item'] === 'esim_1GB_US_30D'
                && $request['assign'] === false;
        });
    }

    public function test_get_balance_reads_organisation_balance(): void
    {
        Http::fake([
            'api.esim-go.com/v2.5/organisation' => Http::response(['balance' => 137.5, 'tier' => 'gold']),
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
