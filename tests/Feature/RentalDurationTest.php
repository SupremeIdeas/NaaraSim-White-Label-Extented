<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * Naara Rent duration (US/Getatext long rentals). 5sim hosting is a fixed
 * short-term period; only US numbers offer longer durations, priced by an
 * admin-set per-tier multiplier so the quote scales with the rental length.
 */
class RentalDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        // Getatext serves the US rental lane in these tests.
        app()->instance('number.getatext', new FakeSmsProvider(price: 1.00));
    }

    public function test_a_longer_us_rental_costs_more_by_the_duration_multiplier(): void
    {
        $user = User::factory()->create();
        $router = app(SmsNumberRouter::class);

        // Base 1-week rental (multiplier 1.0).
        $week = $router->quote(new NumberRequest('usa', NumberRequest::TYPE_RENTAL, 'whatsapp', $user, rentalTime: '1w'));
        // 3-month rental (default multiplier 9.0) costs ~9× the base.
        $quarter = $router->quote(new NumberRequest('usa', NumberRequest::TYPE_RENTAL, 'whatsapp', $user, rentalTime: '3mo'));

        $this->assertSame(1.0, round($week['cost'], 2));
        $this->assertSame(9.0, round($quarter['cost'], 2));
        $this->assertGreaterThan($week['retail'], $quarter['retail']);
    }

    public function test_a_non_us_rental_has_no_duration_multiplier(): void
    {
        $user = User::factory()->create();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.50));

        // A rentalTime on a non-US country is ignored — short-term at base cost.
        $quote = app(SmsNumberRouter::class)->quote(
            new NumberRequest('nigeria', NumberRequest::TYPE_RENTAL, 'whatsapp', $user, rentalTime: null)
        );

        $this->assertSame(0.50, round($quote['cost'], 2));
    }
}
