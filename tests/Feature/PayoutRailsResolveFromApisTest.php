<?php

namespace Tests\Feature;

use App\Models\PayoutCountryRail;
use App\Services\Payouts\Rail\RailRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutRailsResolveFromApisTest extends TestCase
{
    use RefreshDatabase;

    public function test_paystack_countries_come_from_its_api_including_ones_the_built_in_list_never_had(): void
    {
        PayoutCountryRail::query()->delete();
        config(['services.paystack.secret_key' => 'sk_live_x', 'services.paystack.base_url' => 'https://api.paystack.co', 'services.flutterwave.secret_key' => '', 'services.stripe.secret_key' => '']);
        Http::fake(['api.paystack.co/country' => Http::response(['status' => true, 'data' => [
            ['iso_code' => 'NG'], ['iso_code' => 'GH'], ['iso_code' => 'ZA'], ['iso_code' => 'KE'], ['iso_code' => 'CI'], ['iso_code' => 'EG'], ['iso_code' => 'RW'],
        ]])]);

        app(RailRegistrySeeder::class)->run();

        $row = PayoutCountryRail::where(['country' => 'RW', 'rail' => 'paystack'])->firstOrFail();
        $this->assertTrue($row->provider_supports);
        $this->assertSame('api', $row->verified_by);
    }

    public function test_flutterwave_countries_are_found_by_asking_its_bank_list_per_african_country(): void
    {
        PayoutCountryRail::query()->delete();
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-x', 'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3', 'services.paystack.secret_key' => '', 'services.stripe.secret_key' => '']);
        Http::fake([
            'api.flutterwave.com/v3/banks/NG' => Http::response(['status' => 'success', 'data' => [['code' => '044', 'name' => 'Access']]]),
            'api.flutterwave.com/v3/banks/GH' => Http::response(['status' => 'success', 'data' => [['code' => 'MTN', 'name' => 'MTN']]]),
            'api.flutterwave.com/v3/banks/SL' => Http::response(['status' => 'success', 'data' => [['code' => '1', 'name' => 'SL Bank']]]),   // not in the built-in list
            'api.flutterwave.com/v3/banks/*' => Http::response(['status' => 'error', 'message' => 'Country not supported'], 400),
        ]);

        $out = app(RailRegistrySeeder::class)->run();

        $this->assertTrue((bool) PayoutCountryRail::where(['country' => 'SL', 'rail' => 'flutterwave'])->value('provider_supports'), 'a country only the API knows is added');
        $this->assertSame('api', PayoutCountryRail::where(['country' => 'GH', 'rail' => 'flutterwave'])->value('verified_by'));
        $this->assertNotEmpty(array_filter($out['drift'], fn ($d) => str_contains($d, 'flutterwave: UG')), 'a built-in country the API no longer lists is reported, never silently kept as verified');
    }

    public function test_without_a_key_the_built_in_lists_keep_everything_working(): void
    {
        PayoutCountryRail::query()->delete();
        config(['services.flutterwave.secret_key' => '', 'services.paystack.secret_key' => '', 'services.stripe.secret_key' => '']);

        $out = app(RailRegistrySeeder::class)->run();

        $this->assertTrue((bool) PayoutCountryRail::where(['country' => 'NG', 'rail' => 'paystack'])->value('provider_supports'));
        $this->assertTrue((bool) PayoutCountryRail::where(['country' => 'KE', 'rail' => 'flutterwave'])->value('provider_supports'));
        $this->assertStringContainsString('built-in list used', implode(' ', $out['skipped']));
    }

    public function test_an_unreachable_flutterwave_is_not_mistaken_for_serving_nowhere(): void
    {
        PayoutCountryRail::query()->delete();
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-x', 'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3', 'services.paystack.secret_key' => '', 'services.stripe.secret_key' => '']);
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'error', 'message' => 'Invalid authorization key'], 401)]);

        app(RailRegistrySeeder::class)->run();

        $this->assertTrue((bool) PayoutCountryRail::where(['country' => 'GH', 'rail' => 'flutterwave'])->value('provider_supports'), 'a bad key must not wipe the coverage');
    }
}
