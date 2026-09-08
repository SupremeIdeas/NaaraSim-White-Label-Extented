<?php

namespace Tests\Feature;

use App\Services\SMS\SmsNumberRouter;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * Numbers V6 §3 — the Step-3 operator comparison. Networks are merged across
 * the lane and priced at RETAIL; the provider cost is never returned and the
 * best in-stock network is flagged. Also covers Smart Buy's cheapest-country.
 */
class OperatorComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
    }

    private function fakeFivesim(array $operators): FakeSmsProvider
    {
        $fake = new FakeSmsProvider(price: 0.10, operators: $operators);
        app()->instance('number.fivesim', $fake);

        return $fake;
    }

    public function test_it_prices_networks_at_retail_and_flags_the_best(): void
    {
        $this->fakeFivesim([
            'mtn' => ['cost' => 0.20, 'count' => 5, 'rate' => 92.5],
            'airtel' => ['cost' => 0.10, 'count' => 12, 'rate' => 88.0],
            'glo' => ['cost' => 0.15, 'count' => 0, 'rate' => 70.0], // out of stock
        ]);

        $rows = app(SmsNumberRouter::class)->compareOperators('nigeria', 'whatsapp', 'otp');

        // Cheapest in-stock (airtel 0.10) is first and flagged best.
        $this->assertSame('Airtel', $rows[0]['label']);
        $this->assertTrue($rows[0]['best']);
        // Out-of-stock never flagged best, sorted after in-stock.
        $best = array_filter($rows, fn ($r) => $r['best']);
        $this->assertCount(1, $best);

        // Retail is cost + markup (never the raw cost); cost is NEVER present.
        $this->assertGreaterThan(0.10, $rows[0]['retail']);
        foreach ($rows as $r) {
            $this->assertArrayNotHasKey('cost', $r);
            $this->assertArrayHasKey('operator', $r); // raw slug retained for buying
        }
    }

    public function test_smart_buy_resolves_the_cheapest_country(): void
    {
        $fake = $this->fakeFivesim([]);
        $fake->cheapestCountry = 'india';

        $this->assertSame('india', app(SmsNumberRouter::class)->cheapestCountryFor('whatsapp'));
    }
}
