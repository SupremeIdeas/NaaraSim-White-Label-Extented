<?php

namespace Tests\Unit;

use App\Services\Pricing\CurrencyService;
use App\Support\GatewayCurrencyMatrix;
use PHPUnit\Framework\TestCase;

/**
 * GatewayCurrencyMatrix (Unified USD Wallet, Part B §3.6) — the source of
 * truth for which top-up currencies each gateway accepts, so Wallet.php never
 * offers a pairing a gateway doesn't actually support.
 */
class GatewayCurrencyMatrixTest extends TestCase
{
    public function test_supports_is_case_insensitive_and_accurate(): void
    {
        $this->assertTrue(GatewayCurrencyMatrix::supports('paystack', 'ngn'));
        $this->assertTrue(GatewayCurrencyMatrix::supports('paystack', 'NGN'));
        $this->assertFalse(GatewayCurrencyMatrix::supports('paystack', 'GBP'));
        $this->assertTrue(GatewayCurrencyMatrix::supports('flutterwave', 'GBP'));
        $this->assertFalse(GatewayCurrencyMatrix::supports('payssion', 'EUR'));
    }

    public function test_an_unknown_gateway_supports_nothing(): void
    {
        $this->assertSame([], GatewayCurrencyMatrix::currenciesFor('not-a-real-gateway'));
        $this->assertFalse(GatewayCurrencyMatrix::supports('not-a-real-gateway', 'USD'));
    }

    public function test_gateways_for_returns_every_gateway_accepting_a_currency(): void
    {
        $ngn = GatewayCurrencyMatrix::gatewaysFor('ngn');
        $this->assertContains('paystack', $ngn);
        $this->assertContains('flutterwave', $ngn);
        $this->assertNotContains('stripe', $ngn);
        $this->assertNotContains('payssion', $ngn);
    }

    public function test_best_gateway_for_country_picks_a_declared_match(): void
    {
        $this->assertSame('paystack', GatewayCurrencyMatrix::bestGatewayForCountry('NG'));
        $this->assertSame('paystack', GatewayCurrencyMatrix::bestGatewayForCountry('ng'));
        $this->assertNull(GatewayCurrencyMatrix::bestGatewayForCountry('FR'));
        $this->assertNull(GatewayCurrencyMatrix::bestGatewayForCountry(null));
        $this->assertNull(GatewayCurrencyMatrix::bestGatewayForCountry(''));
    }

    public function test_all_currencies_is_deduplicated_and_restricted_to_currency_service(): void
    {
        $all = GatewayCurrencyMatrix::allCurrencies();

        $this->assertSame($all, array_unique($all));
        $this->assertContains('USD', $all);
        $this->assertContains('NGN', $all);
        $this->assertContains('GBP', $all);
        $this->assertContains('USDT', $all);
        // Every entry must be one CurrencyService actually models/displays.
        foreach ($all as $code) {
            $this->assertArrayHasKey($code, CurrencyService::SUPPORTED);
        }
    }
}
