<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\User;
use App\Support\MobileMoneyRails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 10 — mobile-money rails audited per gateway/country (verified against
 * Paystack's and Flutterwave's own developer docs, not guessed), then shown
 * as NAMED options at wallet top-up ("checkout" for money-in): a Ghana/Kenya
 * customer sees MTN/AirtelTigo/Vodafone/M-Pesa by name instead of a vague
 * "mobile money" claim, and a Nigeria/South Africa customer is never told
 * mobile money is available when neither gateway offers it there.
 */
class MobileMoneyRailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        config(['services.flutterwave.secret_key' => 'flw_test_secret']);
    }

    public function test_the_rail_table_only_covers_gateway_currency_pairings_that_really_have_one(): void
    {
        $this->assertSame(['MTN Mobile Money', 'AirtelTigo Money', 'Vodafone Cash'], MobileMoneyRails::forGatewayCurrency('paystack', 'GHS'));
        $this->assertSame(['M-Pesa'], MobileMoneyRails::forGatewayCurrency('paystack', 'KES'));
        $this->assertSame([], MobileMoneyRails::forGatewayCurrency('paystack', 'NGN'));
        $this->assertSame([], MobileMoneyRails::forGatewayCurrency('paystack', 'ZAR'));

        $this->assertSame(['MTN Mobile Money', 'Vodafone Cash', 'AirtelTigo Money'], MobileMoneyRails::forGatewayCurrency('flutterwave', 'GHS'));
        $this->assertSame(['M-Pesa'], MobileMoneyRails::forGatewayCurrency('flutterwave', 'KES'));
        $this->assertSame([], MobileMoneyRails::forGatewayCurrency('flutterwave', 'NGN'));
        $this->assertSame([], MobileMoneyRails::forGatewayCurrency('flutterwave', 'ZAR'));

        $this->assertSame([], MobileMoneyRails::forGatewayCurrency('stripe', 'GHS'));
    }

    public function test_ghana_top_up_names_all_three_rails_for_both_gateways(): void
    {
        $component = Livewire::actingAs(User::factory()->create())
            ->test(Wallet::class)->set('currency', 'GHS');

        $gateways = $component->instance()->payGateways();

        $this->assertStringContainsString('MTN Mobile Money', $gateways['paystack'][1]);
        $this->assertStringContainsString('AirtelTigo Money', $gateways['paystack'][1]);
        $this->assertStringContainsString('Vodafone Cash', $gateways['paystack'][1]);

        $this->assertStringContainsString('MTN Mobile Money', $gateways['flutterwave'][1]);
        $this->assertStringContainsString('Vodafone Cash', $gateways['flutterwave'][1]);
        $this->assertStringContainsString('AirtelTigo Money', $gateways['flutterwave'][1]);
    }

    public function test_kenya_top_up_names_mpesa_for_both_gateways(): void
    {
        $component = Livewire::actingAs(User::factory()->create())
            ->test(Wallet::class)->set('currency', 'KES');

        $gateways = $component->instance()->payGateways();

        $this->assertSame('M-Pesa, cards & bank', $gateways['paystack'][1]);
        $this->assertSame('M-Pesa, cards & bank', $gateways['flutterwave'][1]);
    }

    public function test_nigeria_and_south_africa_top_up_never_claim_mobile_money(): void
    {
        foreach (['NGN', 'ZAR'] as $currency) {
            $component = Livewire::actingAs(User::factory()->create())
                ->test(Wallet::class)->set('currency', $currency);

            $gateways = $component->instance()->payGateways();

            foreach ($gateways as $slug => [$label, $hint]) {
                $this->assertStringNotContainsStringIgnoringCase('mobile money', $hint,
                    "{$slug}'s hint for {$currency} must not claim mobile money — neither gateway offers it there.");
                $this->assertStringNotContainsString('M-Pesa', $hint);
            }
        }
    }
}
