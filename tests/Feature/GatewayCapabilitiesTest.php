<?php

namespace Tests\Feature;

use App\Support\GatewayCapabilities;
use App\Support\GatewayCurrencyMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_any_check_the_documented_list_applies(): void
    {
        $this->assertContains('GHS', GatewayCurrencyMatrix::currenciesFor('paystack'));
    }

    public function test_the_account_answer_replaces_the_documented_list(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x', 'services.paystack.base_url' => 'https://api.paystack.co']);
        // the exact answer a Nigeria-only Paystack business gave the live API
        Http::fake(['api.paystack.co/balance' => Http::response(['status' => true, 'message' => 'Balances retrieved', 'data' => [['currency' => 'NGN', 'balance' => 0]]])]);

        $snap = GatewayCapabilities::refresh('paystack');

        $this->assertSame(['NGN'], $snap['currencies']);
        $this->assertSame(['NGN'], GatewayCurrencyMatrix::currenciesFor('paystack'));
        $this->assertFalse(GatewayCurrencyMatrix::supports('paystack', 'GHS'));
        $this->assertTrue(GatewayCurrencyMatrix::supports('paystack', 'NGN'));
        $this->assertSame(['NGN'], GatewayCurrencyMatrix::allCurrencies(['paystack']));
    }

    public function test_a_failed_check_keeps_the_last_good_answer_and_records_why(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['api.paystack.co/balance' => Http::sequence()
            ->push(['status' => true, 'data' => [['currency' => 'NGN'], ['currency' => 'GHS']]])
            ->push(['status' => false, 'message' => 'Invalid key'], 401)]);

        GatewayCapabilities::refresh('paystack');
        $snap = GatewayCapabilities::refresh('paystack');

        $this->assertEqualsCanonicalizing(['NGN', 'GHS'], $snap['currencies']);
        $this->assertStringContainsString('Invalid key', (string) $snap['error']);
        $this->assertTrue(GatewayCurrencyMatrix::supports('paystack', 'GHS'));
    }

    public function test_flutterwave_and_stripe_are_read_from_their_own_endpoints(): void
    {
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-x', 'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
            'services.stripe.secret_key' => 'sk_test_x', 'services.stripe.base_url' => 'https://api.stripe.com/v1']);
        Http::fake([
            'api.flutterwave.com/v3/balances' => Http::response(['status' => 'success', 'data' => [['currency' => 'NGN'], ['currency' => 'GHS'], ['currency' => 'KES'], ['currency' => 'ZAR']]]),
            'api.stripe.com/v1/account' => Http::response(['country' => 'US']),
            'api.stripe.com/v1/country_specs/US' => Http::response(['supported_payment_currencies' => ['usd', 'eur', 'gbp', 'cad', 'inr', 'ghs']]),
        ]);

        $this->assertEqualsCanonicalizing(['NGN', 'GHS', 'KES', 'ZAR'], GatewayCapabilities::refresh('flutterwave')['currencies']);
        $this->assertEqualsCanonicalizing(['USD', 'EUR', 'GBP', 'CAD', 'INR', 'GHS'], GatewayCapabilities::refresh('stripe')['currencies']);
        $this->assertTrue(GatewayCurrencyMatrix::supports('stripe', 'GHS'));
    }

    public function test_a_currency_the_provider_refuses_at_checkout_is_learned_and_dropped(): void
    {
        $this->assertTrue(GatewayCapabilities::isCurrencyRefusal('Currency not supported by merchant'));
        $this->assertTrue(GatewayCapabilities::isCurrencyRefusal('No active channel to process transaction. Please contact merchant'));
        $this->assertFalse(GatewayCapabilities::isCurrencyRefusal('Invalid email address'));

        GatewayCapabilities::learnUnsupported('paystack', 'ZAR');

        $this->assertFalse(GatewayCurrencyMatrix::supports('paystack', 'ZAR'));
        $this->assertTrue(GatewayCurrencyMatrix::supports('paystack', 'NGN'));
    }

    public function test_gateways_without_a_capability_endpoint_keep_the_documented_list(): void
    {
        $this->assertNull(GatewayCapabilities::detected('paypal'));
        $this->assertSame(['USD', 'GBP', 'EUR', 'CAD', 'INR'], GatewayCurrencyMatrix::currenciesFor('paypal'));
    }
}
