<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\HeroSmsService;
use App\Services\SMS\OtpStatus;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\VirtSmsService;
use App\Support\NumberCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HeroSMS (SMS-Activate successor) + its VirtSMS fallback. Both speak the
 * handler_api.php protocol; both are key-gated so the router stays in-lane until
 * a key is set, and both support "full rent" (service=full → SMS from ANY
 * service) which is what powers Naara Rent's "Any Service" mode.
 */
class HeroSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_unavailable_and_not_full_rent_until_configured(): void
    {
        config(['services.herosms.api_key' => null]);

        $this->assertFalse(app(HeroSmsService::class)->supportsFullRent());
        $this->expectException(OutOfStockException::class);
        app(HeroSmsService::class)->priceFor('nigeria', 'whatsapp');
    }

    public function test_full_rent_buys_a_number_open_to_any_service(): void
    {
        config([
            'services.herosms.api_key' => 'hs-key',
            'services.herosms.base_url' => 'https://hero-sms.com/stubs/handler_api.php',
        ]);
        Http::fake([
            'hero-sms.com/*' => Http::response([
                'status' => 'success',
                'phone' => ['id' => 'HS-99', 'number' => '2348010000000', 'cost' => 0.50],
            ]),
        ]);

        $this->assertTrue(app(HeroSmsService::class)->supportsFullRent());

        $out = app(HeroSmsService::class)->buyRental('nigeria', NumberRequest::SERVICE_ANY);

        $this->assertSame('HS-99', $out['provider_ref']);
        $this->assertSame('2348010000000', $out['number']);
        $this->assertSame(OtpStatus::PENDING, $out['status']);

        // SERVICE_ANY must map to the SMS-Activate "full" sentinel.
        Http::assertSent(fn ($r) => $r['action'] === 'getRentNumber'
            && $r['service'] === 'full'
            && $r['api_key'] === 'hs-key');
    }

    public function test_it_parses_the_status_protocol_replies(): void
    {
        config([
            'services.herosms.api_key' => 'hs-key',
            'services.herosms.base_url' => 'https://hero-sms.com/stubs/handler_api.php',
        ]);
        Http::fake(['hero-sms.com/*' => Http::response('STATUS_OK:123456')]);

        $out = app(HeroSmsService::class)->check('HS-99');
        $this->assertSame(OtpStatus::RECEIVED, $out['status']);
        $this->assertSame('123456', $out['code']);
    }

    public function test_virtsms_uses_its_own_config_and_is_a_full_rent_fallback(): void
    {
        config(['services.herosms.api_key' => null, 'services.virtsms.api_key' => 'vs-key']);

        // VirtSMS is configured independently of HeroSMS.
        $this->assertTrue(app(VirtSmsService::class)->supportsFullRent());
        $this->assertFalse(app(HeroSmsService::class)->supportsFullRent());
    }

    // --- Owner audit (2026-09-15): live country-map discovery ---

    public function test_sync_catalogue_matches_known_countries_by_name_and_extends_unknown_ones(): void
    {
        config(['services.herosms.api_key' => 'hs-key', 'services.herosms.base_url' => 'https://hero-sms.com/stubs/handler_api.php']);
        Http::fake(['hero-sms.com/*' => Http::response([
            '0' => ['id' => 0, 'eng' => 'Nigeria'],
            '1' => ['id' => 1, 'eng' => 'USA'],       // alias → "United States"
            '2' => ['id' => 2, 'eng' => 'England'],   // alias → "United Kingdom"
            '3' => ['id' => 3, 'eng' => 'Iceland'],   // not in our base catalogue yet
        ])]);

        $result = app(HeroSmsService::class)->syncCatalogue();

        $this->assertSame('0', $result['country_map']['nigeria']);
        $this->assertSame('1', $result['country_map']['usa']);
        $this->assertSame('2', $result['country_map']['england']);
        $this->assertSame('3', $result['country_map']['iceland']);
        $this->assertSame('Iceland', $result['countries']['iceland']);
        Http::assertSent(fn ($r) => $r['action'] === 'getCountries');
    }

    public function test_sync_catalogue_is_empty_and_makes_no_call_when_unconfigured(): void
    {
        config(['services.herosms.api_key' => null]);
        Http::fake();

        $result = app(HeroSmsService::class)->syncCatalogue();

        $this->assertSame(['countries' => [], 'country_map' => []], $result);
        Http::assertNothingSent();
    }

    public function test_sync_catalogue_fails_soft_on_a_malformed_response(): void
    {
        config(['services.herosms.api_key' => 'hs-key', 'services.herosms.base_url' => 'https://hero-sms.com/stubs/handler_api.php']);
        Http::fake(['hero-sms.com/*' => Http::response('ERROR_SQL', 500)]);

        $result = app(HeroSmsService::class)->syncCatalogue();

        $this->assertSame(['countries' => [], 'country_map' => []], $result);
    }

    public function test_country_prefers_the_live_discovered_map_over_the_static_config_one(): void
    {
        config([
            'services.herosms.api_key' => 'hs-key',
            'services.herosms.base_url' => 'https://hero-sms.com/stubs/handler_api.php',
            'services.herosms.country_map' => ['nigeria' => 'WRONG-STATIC-ID'],
        ]);
        NumberCatalogue::storeProviderCountryMap('herosms', ['nigeria' => '19']);
        // 'whatsapp' also maps to the seeded service code 'wa' — assert both hold.
        Http::fake(['hero-sms.com/*' => Http::response(['19' => ['wa' => ['cost' => 0.2, 'count' => 5]]])]);

        app(HeroSmsService::class)->priceFor('nigeria', 'whatsapp');

        Http::assertSent(fn ($r) => $r['country'] === '19' && $r['service'] === 'wa');
    }
}
