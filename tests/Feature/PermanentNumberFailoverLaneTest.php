<?php

namespace Tests\Feature;

use App\Http\Controllers\Webhooks\SmsInboundWebhookController;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Support\ProviderHealth;
use App\Support\ProviderModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/**
 * Prompt 12 §1 — the audit's own confirmed finding: PlivoService was fully
 * coded, bound in the container, and had admin key fields, but was wired
 * into ZERO of the four lists that make a provider actually reachable. This
 * asserts all four now include it, and that it's ordered LAST (weakest
 * capability — SMS/number-only, no voice) behind Twilio and Telnyx.
 */
class PermanentNumberFailoverLaneTest extends TestCase
{
    use RefreshDatabase;

    public function test_plivo_is_the_last_priority_entry_in_permanent_number_router_lane(): void
    {
        $router = new \App\Services\SMS\PermanentNumberRouter(
            app(\App\Services\Wallet\WalletService::class),
            app(\App\Services\Pricing\PricingEngine::class),
        );
        $lane = (new ReflectionClass($router))->getProperty('lane');
        $lane->setAccessible(true);
        $value = $lane->getValue($router);

        $this->assertSame('plivo', end($value), 'plivo must be the last (weakest-capability) entry');
        $this->assertContains('twilio', $value);
        $this->assertContains('telnyx', $value);
    }

    public function test_plivo_is_in_the_naara_line_model_lane(): void
    {
        $model = ProviderModels::find('naara_line');
        $this->assertSame('plivo', end($model['lane']));
    }

    public function test_plivo_goes_configured_once_its_keys_are_set(): void
    {
        config(['services.plivo.auth_id' => null, 'services.plivo.auth_token' => null]);
        \App\Support\ProviderKeys::flush();
        $this->assertFalse(ProviderModels::providerConfigured('plivo'));

        config(['services.plivo.auth_id' => 'MAtest', 'services.plivo.auth_token' => 'tok']);
        \App\Support\ProviderKeys::flush();
        $this->assertTrue(ProviderModels::providerConfigured('plivo'));
    }

    public function test_plivo_is_in_the_provider_health_check_list(): void
    {
        config(['services.plivo.auth_id' => 'MAtest', 'services.plivo.auth_token' => 'tok']);
        $health = app(ProviderHealth::class)->checkAll();

        $this->assertArrayHasKey('plivo', $health);
        // No prepaid-wallet concept for a permanent-number provider — just
        // "configured", exactly like Twilio/Telnyx (no fabricated balance probe).
        $this->assertSame('configured', $health['plivo']['status']);
    }

    public function test_the_inbound_sms_webhook_accepts_plivo_as_a_provider(): void
    {
        config(['services.plivo.webhook_token' => 'secret']);
        $user = User::factory()->create();
        VirtualNumber::create([
            'user_id' => $user->id, 'phone_number' => '+2348000000000',
            'status' => 'active', 'provider' => 'plivo', 'type' => 'permanent',
        ]);

        $this->postJson('/webhooks/sms-inbound/plivo?token=secret', [
            'to' => '+2348000000000', 'from' => '+2348011111111', 'text' => 'hi',
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_the_inbound_sms_webhook_still_rejects_an_unlisted_provider(): void
    {
        $this->postJson('/webhooks/sms-inbound/some-unlisted-provider', [])
            ->assertStatus(404);
    }

    public function test_plivo_is_actually_listed_in_the_webhook_controller(): void
    {
        $providers = (new ReflectionClass(SmsInboundWebhookController::class))->getConstant('PROVIDERS');
        $this->assertContains('plivo', $providers);
    }

    /** The lane failover itself: with plivo last and configured alone (no
     *  Twilio/Telnyx keys), a search reaches it — proving the wiring, not
     *  just the list membership, actually works end to end. */
    public function test_a_search_reaches_plivo_when_it_is_the_only_configured_provider(): void
    {
        config(['services.twilio.account_sid' => null, 'services.twilio.auth_token' => null,
            'services.telnyx.api_key' => null,
            'services.plivo.auth_id' => 'MAtest', 'services.plivo.auth_token' => 'tok']);
        \App\Support\ProviderKeys::flush();

        $fake = new FakePermanentProvider(cost: 0.50, results: [
            ['number' => '+2348000000000', 'locality' => 'Lagos'],
        ]);
        $this->app->instance('number.plivo', $fake);

        $result = app(\App\Services\SMS\PermanentNumberRouter::class)->search('nigeria');

        $this->assertSame('plivo', $result['provider']);
        $this->assertCount(1, $result['numbers']);
    }
}
