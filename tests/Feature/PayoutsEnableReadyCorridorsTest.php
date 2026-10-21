<?php

namespace Tests\Feature;

use App\Models\PayoutCorridor;
use App\Models\PayoutCountryRail;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutsEnableReadyCorridorsTest extends TestCase
{
    use RefreshDatabase;

    private function corridor(string $country, string $currency): PayoutCorridor
    {
        return PayoutCorridor::create(['country' => $country, 'currency' => $currency, 'provider' => 'paystack', 'method' => 'bank', 'enabled' => false, 'priority' => 100]);
    }

    public function test_untouched_corridors_with_a_ready_gateway_and_covered_country_are_switched_on(): void
    {
        PayoutCorridor::query()->delete();
        PayoutCountryRail::query()->delete();
        $this->app['env'] = 'production';
        config(['payouts.env' => 'live', 'services.paystack.secret_key' => 'sk_live_abc']);
        foreach (['GH', 'KE'] as $c) {
            PayoutCountryRail::create(['country' => $c, 'rail' => 'paystack', 'provider_supports' => true]);
        }
        $gh = $this->corridor('GH', 'GHS');
        $this->corridor('KE', 'KES');
        $this->corridor('ZA', 'ZAR'); // the provider does not cover ZA in this registry: stays off
        app(PayoutRailRegistry::class)->flush();

        $this->artisan('payouts:enable-ready-corridors')->assertSuccessful();

        $this->assertTrue(PayoutCorridor::where('country', 'GH')->value('enabled'));
        $this->assertTrue(PayoutCorridor::where('country', 'KE')->value('enabled'));
        $this->assertFalse(PayoutCorridor::where('country', 'ZA')->value('enabled'));
    }

    public function test_a_corridor_an_admin_edited_is_never_switched_back_on(): void
    {
        PayoutCorridor::query()->delete();
        PayoutCountryRail::query()->delete();
        $this->app['env'] = 'production';
        config(['payouts.env' => 'live', 'services.paystack.secret_key' => 'sk_live_abc']);
        PayoutCountryRail::create(['country' => 'GH', 'rail' => 'paystack', 'provider_supports' => true]);
        $c = $this->corridor('GH', 'GHS');
        $this->travel(2)->minutes();
        $c->update(['enabled' => false, 'notes' => 'off on purpose']);
        app(PayoutRailRegistry::class)->flush();

        $this->artisan('payouts:enable-ready-corridors')->assertSuccessful();

        $this->assertFalse((bool) PayoutCorridor::where('country', 'GH')->value('enabled'));
    }

    public function test_nothing_is_switched_on_when_the_key_belongs_to_the_other_environment(): void
    {
        PayoutCorridor::query()->delete();
        PayoutCountryRail::query()->delete();
        $this->app['env'] = 'production';
        config(['payouts.env' => 'live', 'services.paystack.secret_key' => 'sk_test_abc']);
        PayoutCountryRail::create(['country' => 'GH', 'rail' => 'paystack', 'provider_supports' => true]);
        $this->corridor('GH', 'GHS');
        app(PayoutRailRegistry::class)->flush();

        $this->artisan('payouts:enable-ready-corridors')->assertSuccessful();

        $this->assertFalse((bool) PayoutCorridor::where('country', 'GH')->value('enabled'));
    }
}
