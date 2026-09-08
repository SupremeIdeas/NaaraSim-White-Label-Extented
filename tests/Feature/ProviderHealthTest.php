<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Support\ProviderHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-5 §2 — provider health across the whole eSIM + number stack. The probe
 * doubles as a reachability check: a provider whose balance call throws is
 * reported `down` (and alerted), not silent. Unconfigured providers are
 * `coming_soon`; balance-less providers (Twilio/Telnyx) are `configured`.
 */
class ProviderHealthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEsim(?float $balance, bool $throw = false): object
    {
        return new class($balance, $throw)
        {
            public function __construct(private ?float $balance, private bool $throw) {}

            public function getBalance(): float
            {
                if ($this->throw) {
                    throw new \RuntimeException('provider timeout');
                }

                return (float) $this->balance;
            }
        };
    }

    public function test_a_reachable_provider_is_ok_and_a_throwing_one_is_down(): void
    {
        config(['services.esimgo.api_key' => 'k', 'services.quibity.api_key' => 'k']);
        $this->app->instance('esim.esimgo', $this->fakeEsim(120.0));
        $this->app->instance('esim.quibity', $this->fakeEsim(null, throw: true));

        $health = app(ProviderHealth::class)->checkAll();

        $this->assertSame('ok', $health['esimgo']['status']);
        $this->assertSame(120.0, $health['esimgo']['balance']);
        $this->assertSame('down', $health['quibity']['status']);
        $this->assertArrayHasKey('error', $health['quibity']);
    }

    public function test_a_balance_below_threshold_is_low(): void
    {
        config(['services.esimgo.api_key' => 'k']);
        Setting::setValue('pricing.low_balance_alert.esimgo', 50, 'pricing');
        $this->app->instance('esim.esimgo', $this->fakeEsim(10.0));

        $health = app(ProviderHealth::class)->checkAll();

        $this->assertSame('low', $health['esimgo']['status']);
    }

    public function test_an_unconfigured_provider_is_coming_soon(): void
    {
        config(['services.airalo.client_id' => '', 'services.airalo.client_secret' => '']);

        $health = app(ProviderHealth::class)->checkAll();

        $this->assertSame('coming_soon', $health['airalo']['status']);
    }

    public function test_a_balanceless_provider_is_reported_configured(): void
    {
        config(['services.twilio.account_sid' => 'AC', 'services.twilio.auth_token' => 't']);
        // A service with no getBalance()/balance() method.
        $this->app->instance('number.twilio', new class {});

        $health = app(ProviderHealth::class)->checkAll();

        $this->assertSame('configured', $health['twilio']['status']);
        $this->assertNull($health['twilio']['balance']);
    }

    public function test_the_command_alerts_on_down_and_low(): void
    {
        config(['services.esimgo.api_key' => 'k', 'services.quibity.api_key' => 'k']);
        Setting::setValue('pricing.low_balance_alert.esimgo', 50, 'pricing');
        $this->app->instance('esim.esimgo', $this->fakeEsim(10.0));       // low
        $this->app->instance('esim.quibity', $this->fakeEsim(null, throw: true)); // down

        $this->artisan('providers:health-check')->assertSuccessful();

        // AlertAdminJob writes an error_logs row synchronously in tests.
        $this->assertDatabaseHas('error_logs', ['code' => 'ESIMGO_low_balance']);
        $this->assertDatabaseHas('error_logs', ['code' => 'QUIBITY_provider_down']);
    }
}
