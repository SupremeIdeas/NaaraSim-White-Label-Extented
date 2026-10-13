<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\GetatextService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Getatext's price list, in the shape the live API really returns (a `prices` list inside an envelope). */
class GetatextPriceLookupTest extends TestCase
{
    private function fake(array $body): void
    {
        config(['services.getatext.api_key' => 'k', 'services.getatext.base_url' => 'https://getatext.com/api/v1']);
        Http::fake(['getatext.com/api/v1/prices-info' => Http::response($body)]);
    }

    public function test_a_service_is_found_inside_the_live_prices_envelope(): void
    {
        $this->fake(['status' => 'success', 'errors' => null, 'prices' => [
            ['service_name' => 'WhatsApp', 'api_name' => 'whatsapp', 'price' => 1.4, 'stock' => 0],
            ['service_name' => 'Google / Gmail / Youtube', 'api_name' => 'google', 'price' => 0.66, 'stock' => 4954],
        ]]);

        $this->assertSame(0.66, app(GetatextService::class)->priceFor('usa', 'google'));
    }

    public function test_an_out_of_stock_service_and_an_unknown_one_are_told_apart(): void
    {
        $this->fake(['prices' => [['service_name' => 'WhatsApp', 'api_name' => 'whatsapp', 'price' => 1.4, 'stock' => 0]]]);
        try {
            app(GetatextService::class)->priceFor('usa', 'whatsapp');
            $this->fail('An out-of-stock service was priced');
        } catch (OutOfStockException $e) {
            $this->assertStringContainsString('out of stock', $e->getMessage());
        }
        $this->expectException(OutOfStockException::class);
        $this->expectExceptionMessage('no service nope');
        app(GetatextService::class)->priceFor('usa', 'nope');
    }

    public function test_a_bare_list_is_still_read(): void
    {
        $this->fake([['service_name' => 'Telegram', 'api_name' => 'telegram', 'price' => 0.5, 'stock' => 10]]);

        $this->assertSame(0.5, app(GetatextService::class)->priceFor('usa', 'telegram'));
    }
}
