<?php

namespace Tests\Feature;

use App\Services\SMS\PortabilityChecker;
use App\Support\ProviderKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/**
 * Port-in eligibility (Prompt 11 Batch D). Two honest gates: US/Canada only,
 * then a real carrier probe. We only ever say "eligible" when a provider
 * confirms it; anything unverifiable is an honest "sorry".
 */
class PortabilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function bindTwilio(bool $portable): void
    {
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'tok']);
        ProviderKeys::flush();
        $this->app->instance('number.twilio', new FakePermanentProvider(portable: $portable));
    }

    public function test_a_plus1_number_the_carrier_allows_is_eligible(): void
    {
        $this->bindTwilio(portable: true);

        $result = app(PortabilityChecker::class)->checkPortIn('+15550001234');

        $this->assertTrue($result['eligible']);
        $this->assertNull($result['reason']);
    }

    public function test_a_plus1_number_the_carrier_blocks_is_not_eligible(): void
    {
        $this->bindTwilio(portable: false);

        $result = app(PortabilityChecker::class)->checkPortIn('+15550001234');

        $this->assertFalse($result['eligible']);
        $this->assertNotNull($result['reason']);
    }

    public function test_a_non_us_canada_number_is_refused_at_the_first_gate(): void
    {
        // No provider bound — the +1 gate rejects it before any probe.
        $result = app(PortabilityChecker::class)->checkPortIn('+2348012345678');

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('US and Canada', $result['reason']);
    }

    public function test_an_unverifiable_number_is_never_falsely_promised(): void
    {
        // Twilio not configured → the probe can't run → honest "couldn't confirm".
        config(['services.twilio.account_sid' => null, 'services.twilio.auth_token' => null]);
        ProviderKeys::flush();

        $result = app(PortabilityChecker::class)->checkPortIn('+15550001234');

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('couldn\'t confirm', $result['reason']);
    }
}
