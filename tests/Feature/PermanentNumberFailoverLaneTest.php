<?php

namespace Tests\Feature;

use App\Http\Controllers\Webhooks\SmsInboundWebhookController;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Support\ProviderHealth;
use App\Support\ProviderModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/**
 * Prompt 12 — the audit's own confirmed finding: PlivoService was fully
 * coded, bound in the container, and had admin key fields, but was wired
 * into ZERO of the four lists that make a provider actually reachable. §1
 * fixed Plivo; §2/§3 add Vonage/Sinch the same way. This asserts all four
 * lists include all three, in the documented priority order: Twilio/Telnyx
 * (confirmed voice+SMS) → Vonage (confirmed Nigeria voice) → Sinch (SMS
 * confirmed, voice unverified — treated conservatively) → Plivo (confirmed
 * SMS/number-only, no African voice).
 */
class PermanentNumberFailoverLaneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lane_is_ordered_strongest_capability_first(): void
    {
        $router = new \App\Services\SMS\PermanentNumberRouter(
            app(\App\Services\Wallet\WalletService::class),
            app(\App\Services\Pricing\PricingEngine::class),
        );
        $lane = (new ReflectionClass($router))->getProperty('lane');
        $lane->setAccessible(true);
        $value = $lane->getValue($router);

        $this->assertSame(['twilio', 'telnyx', 'vonage', 'sinch', 'plivo'], $value);
    }

    public function test_the_naara_line_model_lane_matches_the_router_lane_exactly(): void
    {
        $model = ProviderModels::find('naara_line');
        $this->assertSame(['twilio', 'telnyx', 'vonage', 'sinch', 'plivo'], $model['lane']);
    }

    /** @return array<string, array{0:string, 1:array<string,string>}> */
    public static function providerKeyProvider(): array
    {
        return [
            'plivo' => ['plivo', ['services.plivo.auth_id' => 'MAtest', 'services.plivo.auth_token' => 'tok']],
            'vonage' => ['vonage', ['services.vonage.api_key' => 'key123', 'services.vonage.api_secret' => 'secret456']],
            'sinch' => ['sinch', [
                'services.sinch.client_id' => 'client123', 'services.sinch.client_secret' => 'secret456',
                'services.sinch.project_id' => 'proj-789',
            ]],
        ];
    }

    #[DataProvider('providerKeyProvider')]
    public function test_goes_configured_once_its_keys_are_set(string $provider, array $keys): void
    {
        foreach (array_keys($keys) as $key) {
            config([$key => null]);
        }
        \App\Support\ProviderKeys::flush();
        $this->assertFalse(ProviderModels::providerConfigured($provider), "$provider should not be configured yet");

        config($keys);
        \App\Support\ProviderKeys::flush();
        $this->assertTrue(ProviderModels::providerConfigured($provider), "$provider should be configured now");
    }

    #[DataProvider('providerKeyProvider')]
    public function test_is_in_the_provider_health_check_list(string $provider, array $keys): void
    {
        config($keys);
        $health = app(ProviderHealth::class)->checkAll();

        $this->assertArrayHasKey($provider, $health);
        // No prepaid-wallet concept for a permanent-number provider — just
        // "configured", exactly like Twilio/Telnyx (no fabricated balance probe).
        $this->assertSame('configured', $health[$provider]['status']);
    }

    #[DataProvider('providerKeyProvider')]
    public function test_the_inbound_sms_webhook_accepts_it_as_a_provider(string $provider): void
    {
        config(["services.$provider.webhook_token" => 'secret']);
        $user = User::factory()->create();
        VirtualNumber::create([
            'user_id' => $user->id, 'phone_number' => '+2348000000000',
            'status' => 'active', 'provider' => $provider, 'type' => 'permanent',
        ]);

        $this->postJson("/webhooks/sms-inbound/$provider?token=secret", [
            'to' => '+2348000000000', 'from' => '+2348011111111', 'text' => 'hi',
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_the_inbound_sms_webhook_still_rejects_an_unlisted_provider(): void
    {
        $this->postJson('/webhooks/sms-inbound/some-unlisted-provider', [])
            ->assertStatus(404);
    }

    public function test_all_three_new_providers_are_actually_listed_in_the_webhook_controller(): void
    {
        $providers = (new ReflectionClass(SmsInboundWebhookController::class))->getConstant('PROVIDERS');
        $this->assertContains('plivo', $providers);
        $this->assertContains('vonage', $providers);
        $this->assertContains('sinch', $providers);
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

    /** With Twilio/Telnyx unconfigured but Vonage AND Plivo both configured,
     *  the search must reach Vonage first — proving the priority ORDER is
     *  actually honoured, not just that each provider is individually
     *  reachable in isolation. */
    public function test_vonage_is_tried_before_plivo_when_both_are_configured(): void
    {
        config([
            'services.twilio.account_sid' => null, 'services.twilio.auth_token' => null,
            'services.telnyx.api_key' => null,
            'services.vonage.api_key' => 'key123', 'services.vonage.api_secret' => 'secret456',
            'services.plivo.auth_id' => 'MAtest', 'services.plivo.auth_token' => 'tok',
        ]);
        \App\Support\ProviderKeys::flush();

        $vonageFake = new FakePermanentProvider(cost: 0.75, results: [
            ['number' => '+2348000000001', 'locality' => 'Lagos'],
        ]);
        $plivoFake = new FakePermanentProvider(cost: 0.50, results: [
            ['number' => '+2348000000002', 'locality' => 'Abuja'],
        ]);
        $this->app->instance('number.vonage', $vonageFake);
        $this->app->instance('number.plivo', $plivoFake);

        $result = app(\App\Services\SMS\PermanentNumberRouter::class)->search('nigeria');

        $this->assertSame('vonage', $result['provider']);
    }
}
