<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\FiveSimService;
use App\Services\SMS\OtpStatus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FiveSimServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.fivesim.base_url' => 'https://5sim.net/v1',
            'services.fivesim.api_key' => 'jwt-token',
        ]);
    }

    public function test_buy_activation_normalises_response_and_sends_bearer(): void
    {
        Http::fake([
            '5sim.net/v1/user/buy/activation/*' => Http::response([
                'id' => 424242, 'phone' => '2348010000000', 'price' => 0.2, 'status' => 'PENDING',
            ]),
        ]);

        $out = app(FiveSimService::class)->buyOtp('nigeria', 'whatsapp');

        $this->assertSame('424242', $out['provider_ref']);
        $this->assertSame('2348010000000', $out['number']);
        $this->assertSame(0.2, $out['cost']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer jwt-token')
            && str_contains($r->url(), '/user/buy/activation/nigeria/any/whatsapp'));
    }

    public function test_check_extracts_code_and_marks_received(): void
    {
        Http::fake([
            '5sim.net/v1/user/check/*' => Http::response([
                'status' => 'RECEIVED',
                'sms' => [['sender' => 'WhatsApp', 'text' => 'code 123-456', 'code' => '123456']],
            ]),
        ]);

        $out = app(FiveSimService::class)->check('424242');

        $this->assertSame(OtpStatus::RECEIVED, $out['status']);
        $this->assertSame('123456', $out['code']);
    }

    public function test_finish_hits_the_finish_endpoint(): void
    {
        Http::fake(['5sim.net/v1/user/finish/*' => Http::response(['id' => 1, 'status' => 'FINISHED'])]);

        app(FiveSimService::class)->finish('424242');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/user/finish/424242'));
    }

    public function test_price_for_picks_cheapest_in_stock_operator(): void
    {
        Http::fake([
            '5sim.net/v1/guest/prices*' => Http::response([
                'nigeria' => ['whatsapp' => [
                    'virtual21' => ['cost' => 0.5, 'count' => 100, 'rate' => 60],
                    'virtual4' => ['cost' => 0.3, 'count' => 20, 'rate' => 55],
                    'virtual9' => ['cost' => 0.1, 'count' => 0, 'rate' => 40], // out of stock
                ]],
            ]),
        ]);

        // Cheapest with stock is 0.3 (0.1 has zero stock).
        $this->assertSame(0.3, app(FiveSimService::class)->priceFor('nigeria', 'whatsapp'));
    }

    public function test_price_for_throws_out_of_stock_when_no_operator_has_stock(): void
    {
        Http::fake([
            '5sim.net/v1/guest/prices*' => Http::response([
                'nigeria' => ['whatsapp' => ['virtual9' => ['cost' => 0.1, 'count' => 0]]],
            ]),
        ]);

        $this->expectException(OutOfStockException::class);
        app(FiveSimService::class)->priceFor('nigeria', 'whatsapp');
    }

    // --- Owner audit (2026-09-15): unconfigured never makes a live call ---

    public function test_an_unconfigured_key_refuses_before_any_http_call(): void
    {
        config(['services.fivesim.api_key' => '']);
        Http::fake();

        try {
            app(FiveSimService::class)->priceFor('nigeria', 'whatsapp');
            $this->fail('Expected an OutOfStockException.');
        } catch (OutOfStockException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_key_refuses_buy_otp_before_any_http_call(): void
    {
        config(['services.fivesim.api_key' => null]);
        Http::fake();

        try {
            app(FiveSimService::class)->buyOtp('nigeria', 'whatsapp');
            $this->fail('Expected an OutOfStockException.');
        } catch (OutOfStockException) {
            // expected
        }

        Http::assertNothingSent();
    }
}
