<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use App\Services\SMS\OnlineSimService;
use App\Services\SMS\OtpStatus;
use App\Support\NumberCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OnlineSIM (owner audit, 2026-09-15) — verified against OnlineSIM's own
 * Postman collections (legacy + current v1.1). The confirmed real bug:
 * `country` is a NUMERIC INTERNAL ID, never the plain slug this adapter
 * shipped with — so every call was silently targeting the wrong country (or
 * none) until resolveCountry() started requiring a live-discovered mapping.
 * check()'s status mapping had the same "never matches OtpStatus::RECEIVED"
 * bug as SMSPool, plus a dead `TZ_NUM_CANCEL` branch that doesn't exist in
 * either official spec.
 */
class OnlineSimServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.onlinesim.base_url' => 'https://onlinesim.io/api',
            'services.onlinesim.api_key' => 'os-key',
        ]);
    }

    public function test_it_reports_unavailable_until_configured(): void
    {
        config(['services.onlinesim.api_key' => null]);
        Http::fake();

        try {
            app(OnlineSimService::class)->priceFor('nigeria', 'whatsapp');
            $this->fail('Expected SmsException.');
        } catch (SmsException) {
            // expected
        }
        Http::assertNothingSent();
    }

    public function test_an_unmapped_country_fails_safe_as_out_of_stock_never_a_guessed_id(): void
    {
        // No providerCountryMap('onlinesim') entry exists yet — OnlineSIM
        // requires its own numeric id (unlike SMSPool, name is not accepted),
        // so this must refuse rather than send a slug that could silently hit
        // the wrong country.
        Http::fake();

        try {
            app(OnlineSimService::class)->priceFor('nigeria', 'whatsapp');
            $this->fail('Expected OutOfStockException.');
        } catch (OutOfStockException) {
            // expected
        }
        Http::assertNothingSent();
    }

    public function test_price_for_uses_the_resolved_numeric_country_id(): void
    {
        NumberCatalogue::storeProviderCountryMap('onlinesim', ['nigeria' => '7']);
        Http::fake(['onlinesim.io/api/getTariffs.php*' => Http::response(['price' => 0.3])]);

        $price = app(OnlineSimService::class)->priceFor('nigeria', 'whatsapp');

        $this->assertSame(0.3, $price);
        Http::assertSent(fn ($r) => $r['country'] === '7' && $r['service'] === 'whatsapp' && $r['apikey'] === 'os-key');
    }

    public function test_check_maps_a_received_code_onto_the_shared_received_status(): void
    {
        NumberCatalogue::storeProviderCountryMap('onlinesim', ['nigeria' => '7']);
        // The critical regression test: before the audit this returned the raw
        // string 'completed', which PollSmsOtpJob never matches — a delivered
        // OnlineSIM code would silently run out the poll window and refund.
        Http::fake(['onlinesim.io/api/getState.php*' => Http::response([
            ['response' => 'TZ_NUM_ANSWER', 'msg' => '654321'],
        ])]);

        $out = app(OnlineSimService::class)->check('tzid-1');

        $this->assertSame(OtpStatus::RECEIVED, $out['status']);
        $this->assertSame('654321', $out['code']);
    }

    public function test_check_maps_a_timed_out_order_onto_timeout_not_completed(): void
    {
        Http::fake(['onlinesim.io/api/getState.php*' => Http::response([
            ['response' => 'TZ_OVER_EMPTY'],
        ])]);

        $out = app(OnlineSimService::class)->check('tzid-1');

        $this->assertSame(OtpStatus::TIMEOUT, $out['status']);
    }

    public function test_check_defaults_to_pending_for_an_unrecognised_status(): void
    {
        // TZ_NUM_CANCEL was previously mapped to "cancelled" here but does not
        // exist in either official OnlineSIM spec — must fall through safely.
        Http::fake(['onlinesim.io/api/getState.php*' => Http::response([
            ['response' => 'TZ_NUM_CANCEL'],
        ])]);

        $out = app(OnlineSimService::class)->check('tzid-1');

        $this->assertSame(OtpStatus::PENDING, $out['status']);
    }

    public function test_sync_catalogue_matches_known_countries_by_name(): void
    {
        Http::fake(['onlinesim.io/api/getNumbersStats.php*' => Http::response([
            '7' => ['name' => 'Russia'],
            '86' => ['name' => 'China'],
            '999' => ['name' => 'Atlantis'], // not in our base catalogue yet
        ])]);

        $result = app(OnlineSimService::class)->syncCatalogue();

        $this->assertSame('7', $result['country_map']['russia']);
        $this->assertSame('86', $result['country_map']['china']);
        $this->assertSame('999', $result['country_map']['atlantis']);
        $this->assertSame('Atlantis', $result['countries']['atlantis']);
    }

    public function test_sync_catalogue_is_empty_and_makes_no_call_when_unconfigured(): void
    {
        config(['services.onlinesim.api_key' => null]);
        Http::fake();

        $result = app(OnlineSimService::class)->syncCatalogue();

        $this->assertSame(['countries' => [], 'country_map' => []], $result);
        Http::assertNothingSent();
    }

    public function test_sync_catalogue_fails_soft_on_error(): void
    {
        Http::fake(['onlinesim.io/api/*' => Http::response('error', 500)]);

        $result = app(OnlineSimService::class)->syncCatalogue();

        $this->assertSame(['countries' => [], 'country_map' => []], $result);
    }
}
