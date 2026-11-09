<?php

namespace Tests\Feature;

use App\Models\TopupQuote;
use App\Services\Topup\QuoteService;
use App\Support\TopupLogos;
use Tests\TestCase;

class TopupControlsTest extends TestCase
{
    public function test_supplier_operator_names_resolve_to_the_shipped_brand_logos(): void
    {
        foreach ([['MTN Nigeria', 'NG', 'mtn'], ['Airtel Nigeria', 'NG', 'airtel'], ['GLO Nigeria', 'NG', 'glo'], ['9Mobile Nigeria', 'NG', 'ninemobile'], ['Nine Mobile', 'NG', 'ninemobile'],
            ['MTN Ghana Data', 'GH', 'mtn'], ['Safaricom Kenya', 'KE', 'safaricom'], ['Vodacom', 'ZA', 'vodacom'], ['Etisalat', 'NG', 'ninemobile']] as [$name, $cc, $key]) {
            $this->assertSame($key, TopupLogos::resolveKey($name, $cc), $name);
            $this->assertNotNull(TopupLogos::url($name, $cc), $name.' has no logo file');
        }
        $this->assertNull(TopupLogos::url('Some Unknown Telco', 'NG'));   // honest initials tile, never a broken image
    }

    public function test_validity_text_maps_to_days(): void
    {
        $this->assertSame(7, QuoteService::validityDays('7 days'));
        $this->assertSame(14, QuoteService::validityDays('2 weeks'));
        $this->assertSame(30, QuoteService::validityDays('Monthly'));
        $this->assertSame(1, QuoteService::validityDays('1 Day'));
        $this->assertSame(0, QuoteService::validityDays(''));
    }

    public function test_rate_line_shows_local_value_per_dollar_and_never_for_usd(): void
    {
        $svc = app(QuoteService::class);
        $q = new TopupQuote(['send_amount' => 1500, 'send_currency' => 'NGN', 'list_price_usd' => 1.00]);
        $line = $svc->rateLine($q);
        $this->assertSame('NGN', $line['currency']);
        $this->assertEqualsWithDelta(1500.0, $line['per_usd'], 0.01);
        $this->assertNull($svc->rateLine(new TopupQuote(['send_amount' => 5, 'send_currency' => 'USD', 'list_price_usd' => 5])));
    }
}
