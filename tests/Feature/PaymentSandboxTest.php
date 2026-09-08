<?php

namespace Tests\Feature;

use App\Support\PaymentSandbox;
use Tests\TestCase;

/**
 * BUILD-2 §8 — the platform-wide test/sandbox indicator detects a gateway still
 * on test keys from each provider's stable convention, and never falsely claims
 * "test" where it can't tell.
 */
class PaymentSandboxTest extends TestCase
{
    public function test_stripe_and_paystack_test_keys_are_detected(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_abc', 'services.paystack.secret_key' => 'sk_test_xyz']);
        $this->assertTrue(PaymentSandbox::isTest('stripe'));
        $this->assertTrue(PaymentSandbox::isTest('paystack'));

        config(['services.stripe.secret_key' => 'sk_live_abc', 'services.paystack.secret_key' => 'sk_live_xyz']);
        $this->assertFalse(PaymentSandbox::isTest('stripe'));
        $this->assertFalse(PaymentSandbox::isTest('paystack'));
    }

    public function test_flutterwave_test_secret_is_detected(): void
    {
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-0123456789-X']);
        $this->assertTrue(PaymentSandbox::isTest('flutterwave'));

        config(['services.flutterwave.secret_key' => 'FLWSECK-0123456789abcdef-X']);
        $this->assertFalse(PaymentSandbox::isTest('flutterwave'));
    }

    public function test_paypal_and_nowpayments_sandbox_hosts_are_detected(): void
    {
        config(['services.paypal.base_url' => 'https://api-m.sandbox.paypal.com']);
        config(['services.nowpayments.base_url' => 'https://api-sandbox.nowpayments.io']);
        $this->assertTrue(PaymentSandbox::isTest('paypal'));
        $this->assertTrue(PaymentSandbox::isTest('nowpayments'));

        config(['services.paypal.base_url' => 'https://api-m.paypal.com']);
        config(['services.nowpayments.base_url' => 'https://api.nowpayments.io']);
        $this->assertFalse(PaymentSandbox::isTest('paypal'));
        $this->assertFalse(PaymentSandbox::isTest('nowpayments'));
    }

    public function test_gateways_without_a_reliable_convention_are_never_falsely_flagged(): void
    {
        // Binance/Cryptomus/CoinPayments/Payssion have no public test-key prefix —
        // we must NOT claim "test" (a false "you're live" is the dangerous one).
        foreach (['binance', 'cryptomus', 'coinpayments', 'payssion'] as $gw) {
            $this->assertFalse(PaymentSandbox::isTest($gw));
        }
    }

    public function test_only_configured_test_gateways_are_listed(): void
    {
        config([
            'services.stripe.secret_key' => 'sk_test_abc',       // configured + test
            'services.paystack.secret_key' => 'sk_live_xyz',     // configured + live
            'services.flutterwave.secret_key' => '',             // not configured
        ]);

        $test = PaymentSandbox::testGateways();
        $this->assertArrayHasKey('stripe', $test);
        $this->assertArrayNotHasKey('paystack', $test);
        $this->assertArrayNotHasKey('flutterwave', $test);
        $this->assertTrue(PaymentSandbox::anyTest());
    }
}
