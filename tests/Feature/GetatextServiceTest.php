<?php

namespace Tests\Feature;

use App\Exceptions\GetatextAuthException;
use App\Exceptions\OutOfStockException;
use App\Services\SMS\GetatextService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GetatextServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.getatext.base_url' => 'https://getatext.com/api/v1',
            'services.getatext.api_key' => 'sk_test_123',
        ]);
    }

    public function test_rent_a_number_sends_auth_header_and_normalises(): void
    {
        Http::fake([
            'getatext.com/api/v1/rent-a-number' => Http::response([
                'id' => 12345, 'number' => '15551234567', 'price' => 1.25, 'new_balance' => 48.75,
            ]),
        ]);

        $out = app(GetatextService::class)->buyOtp('US', 'whatsapp', ['max_price' => 2.0]);

        $this->assertSame('12345', $out['provider_ref']);
        $this->assertSame('15551234567', $out['number']);
        $this->assertSame(1.25, $out['cost']);
        Http::assertSent(fn ($r) => $r->hasHeader('Auth', 'sk_test_123')
            && $r['service'] === 'whatsapp' && $r['max_price'] === 2.0);
    }

    public function test_out_of_stock_error_maps_to_out_of_stock_exception(): void
    {
        Http::fake([
            'getatext.com/api/v1/rent-a-number' => Http::response(['error' => 'Service is out of stock']),
        ]);

        $this->expectException(OutOfStockException::class);
        app(GetatextService::class)->buyOtp('US', 'whatsapp');
    }

    public function test_bad_api_key_maps_to_auth_exception(): void
    {
        Http::fake([
            'getatext.com/api/v1/rent-a-number' => Http::response(['error' => 'Wrong Api Key or User is restricted']),
        ]);

        $this->expectException(GetatextAuthException::class);
        app(GetatextService::class)->buyOtp('US', 'whatsapp');
    }

    // --- Owner audit (2026-09-15): unconfigured never makes a live call ---

    public function test_an_unconfigured_key_refuses_before_any_http_call(): void
    {
        config(['services.getatext.api_key' => '']);
        Http::fake();

        try {
            app(GetatextService::class)->priceFor('US', 'whatsapp');
            $this->fail('Expected an OutOfStockException.');
        } catch (OutOfStockException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_key_refuses_buy_otp_before_any_http_call(): void
    {
        config(['services.getatext.api_key' => null]);
        Http::fake();

        try {
            app(GetatextService::class)->buyOtp('US', 'whatsapp');
            $this->fail('Expected an OutOfStockException.');
        } catch (OutOfStockException) {
            // expected
        }

        Http::assertNothingSent();
    }
}
