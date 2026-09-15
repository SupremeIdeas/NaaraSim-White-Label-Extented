<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use App\Services\SMS\OtpStatus;
use App\Services\SMS\SmsPoolService;
use App\Support\NumberCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SMSPool (owner audit, 2026-09-15) — verified live against api.smspool.net.
 * `orderid`/status-code semantics were already right; the two real bugs were
 * (1) check()'s "3 = completed"/"6 = cancelled" strings never matched
 * OtpStatus::RECEIVED (the only value PollSmsOtpJob actually looks for), so a
 * delivered code was never recognised and the order silently timed out and
 * auto-refunded anyway, and (2) country/service were passed as raw slugs with
 * no id-resolution even though the canonical form is SMSPool's own numeric id.
 */
class SmsPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.smspool.base_url' => 'https://api.smspool.net',
            'services.smspool.api_key' => 'sp-key',
        ]);
    }

    public function test_it_reports_unavailable_until_configured(): void
    {
        config(['services.smspool.api_key' => null]);
        Http::fake();

        try {
            app(SmsPoolService::class)->priceFor('nigeria', 'whatsapp');
            $this->fail('Expected SmsException.');
        } catch (SmsException) {
            // expected
        }
        Http::assertNothingSent();
    }

    public function test_price_for_uses_the_raw_slug_when_no_id_mapping_exists(): void
    {
        Http::fake(['api.smspool.net/request/price' => Http::response(['price' => 0.25])]);

        $price = app(SmsPoolService::class)->priceFor('nigeria', 'whatsapp');

        $this->assertSame(0.25, $price);
        Http::assertSent(fn ($r) => $r['country'] === 'nigeria' && $r['service'] === 'whatsapp' && $r['key'] === 'sp-key');
    }

    public function test_price_for_prefers_the_live_discovered_id_map(): void
    {
        NumberCatalogue::storeProviderCountryMap('smspool', ['nigeria' => '161']);
        NumberCatalogue::storeProviderServiceMap('smspool', ['whatsapp' => '395']);
        Http::fake(['api.smspool.net/request/price' => Http::response(['price' => 0.25])]);

        app(SmsPoolService::class)->priceFor('nigeria', 'whatsapp');

        Http::assertSent(fn ($r) => $r['country'] === '161' && $r['service'] === '395');
    }

    public function test_out_of_stock_when_price_is_zero(): void
    {
        Http::fake(['api.smspool.net/request/price' => Http::response(['price' => 0])]);

        try {
            app(SmsPoolService::class)->priceFor('nigeria', 'whatsapp');
            $this->fail('Expected OutOfStockException.');
        } catch (OutOfStockException) {
            // expected
        }
        Http::assertSent(fn ($r) => str_contains($r->url(), '/request/price'));
    }

    public function test_check_maps_a_completed_order_onto_the_shared_received_status(): void
    {
        // The critical regression test: before the audit this returned the raw
        // string 'completed', which PollSmsOtpJob never matches (it only checks
        // OtpStatus::RECEIVED) — so a delivered SMSPool code would silently run
        // out the poll window and get auto-refunded despite having arrived.
        Http::fake(['api.smspool.net/sms/check' => Http::response(['status' => 3, 'sms' => '123456'])]);

        $out = app(SmsPoolService::class)->check('ORD-1');

        $this->assertSame(OtpStatus::RECEIVED, $out['status']);
        $this->assertSame('123456', $out['code']);
    }

    public function test_check_maps_a_refunded_order_onto_canceled_not_completed(): void
    {
        Http::fake(['api.smspool.net/sms/check' => Http::response(['status' => 6])]);

        $out = app(SmsPoolService::class)->check('ORD-1');

        $this->assertSame(OtpStatus::CANCELED, $out['status']);
    }

    public function test_check_defaults_to_pending_for_an_unknown_status(): void
    {
        Http::fake(['api.smspool.net/sms/check' => Http::response(['status' => 1])]);

        $out = app(SmsPoolService::class)->check('ORD-1');

        $this->assertSame(OtpStatus::PENDING, $out['status']);
    }

    public function test_sync_catalogue_matches_known_countries_and_services_by_name(): void
    {
        Http::fake([
            'api.smspool.net/country/retrieve_all' => Http::response([
                ['ID' => 161, 'name' => 'Nigeria', 'short_name' => 'NG'],
                ['ID' => 999, 'name' => 'Atlantis'], // not in our base catalogue yet
            ]),
            'api.smspool.net/service/retrieve_all' => Http::response([
                ['ID' => 395, 'name' => 'WhatsApp'],
                ['ID' => 1, 'name' => 'Some Unrelated Thing'],
            ]),
        ]);

        $result = app(SmsPoolService::class)->syncCatalogue();

        $this->assertSame('161', $result['country_map']['nigeria']);
        $this->assertSame('999', $result['country_map']['atlantis']);
        $this->assertSame('Atlantis', $result['countries']['atlantis']);
        $this->assertSame('395', $result['service_map']['whatsapp']);
        $this->assertArrayNotHasKey('some_unrelated_thing', $result['service_map']);
    }

    public function test_sync_catalogue_needs_no_api_key(): void
    {
        config(['services.smspool.api_key' => null]);
        Http::fake([
            'api.smspool.net/country/retrieve_all' => Http::response([]),
            'api.smspool.net/service/retrieve_all' => Http::response([]),
        ]);

        $result = app(SmsPoolService::class)->syncCatalogue();

        $this->assertSame(['countries' => [], 'country_map' => [], 'service_map' => []], $result);
        Http::assertSentCount(2); // retrieve_all called even though no key is configured
    }

    public function test_sync_catalogue_fails_soft_on_error(): void
    {
        Http::fake(['api.smspool.net/*' => Http::response('error', 500)]);

        $result = app(SmsPoolService::class)->syncCatalogue();

        $this->assertSame(['countries' => [], 'country_map' => [], 'service_map' => []], $result);
    }
}
