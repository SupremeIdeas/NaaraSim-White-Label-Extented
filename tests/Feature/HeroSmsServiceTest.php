<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\HeroSmsService;
use App\Services\SMS\OtpStatus;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\VirtSmsService;
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
}
