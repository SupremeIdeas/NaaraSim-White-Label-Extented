<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Api\ApiClientService;
use App\Services\SMS\OtpStatus;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * Developer API read surface (ROADMAP §Layer 2): the feature flag, client auth,
 * scope enforcement, and developer-lane pricing with cost never exposed.
 */
class DeveloperApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
    }

    private function enable(): void
    {
        Setting::setValue('developer_api.enabled', true, 'developer_api');
    }

    /** @return array{0:string,1:\App\Models\ApiClient} */
    private function client(array $scopes = ['catalogue', 'quote']): array
    {
        ['client' => $client, 'token' => $token] = app(ApiClientService::class)
            ->create(User::factory()->create(), 'app', $scopes);

        return [$token, $client];
    }

    private function plan(array $attrs = []): EsimPlan
    {
        static $n = 0;
        $n++;

        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'P'.$n, 'name' => 'Plan '.$n,
            'type' => 'local', 'data_mb' => 1024, 'countries' => ['NG'], 'validity_days' => 30,
            'cost_price_usd' => 10.0, 'is_active' => true,
        ], $attrs));
    }

    public function test_the_api_is_hidden_with_a_404_until_the_admin_enables_it(): void
    {
        [$token] = $this->client();
        $this->withToken($token)->getJson('/api/v1/catalogue')->assertNotFound();
    }

    public function test_catalogue_returns_developer_prices_and_never_the_cost_or_supplier(): void
    {
        $this->enable();
        $this->plan(['name' => 'Nigeria 1GB', 'cost_price_usd' => 10.0]);
        [$token] = $this->client(['catalogue']);

        $res = $this->withToken($token)->getJson('/api/v1/catalogue')->assertOk();

        // Developer price = cost 10 * 1.10 = 11.00 (dev lane), never retail/cost.
        $this->assertEquals(11.0, $res->json('data.0.price_usd'));
        $res->assertJsonPath('data.0.currency', 'USD');
        $body = $res->getContent();
        $this->assertStringNotContainsString('cost', strtolower($body));      // no cost fields
        $this->assertStringNotContainsString('esimgo', strtolower($body));    // no supplier
        $this->assertStringNotContainsString('markup', strtolower($body));
    }

    public function test_catalogue_exposes_has_voice_and_filters_naara_connect_plans(): void
    {
        $this->enable();
        $this->plan(['name' => 'NG Data 1GB', 'has_voice' => false]);
        $this->plan(['name' => 'NG Connect 5GB', 'provider' => 'zendit', 'has_voice' => true]);
        [$token] = $this->client(['catalogue']);

        // Every plan carries has_voice.
        $all = $this->withToken($token)->getJson('/api/v1/catalogue')->assertOk();
        $this->assertCount(2, $all->json('data'));

        // ?has_voice=true → only the Naara Connect (Full eSIM) plan.
        $voice = $this->withToken($token)->getJson('/api/v1/catalogue?has_voice=true')->assertOk();
        $this->assertCount(1, $voice->json('data'));
        $this->assertSame('NG Connect 5GB', $voice->json('data.0.name'));
        $this->assertTrue($voice->json('data.0.has_voice'));

        // ?has_voice=false → only the data-only plan.
        $data = $this->withToken($token)->getJson('/api/v1/catalogue?has_voice=false')->assertOk();
        $this->assertCount(1, $data->json('data'));
        $this->assertFalse($data->json('data.0.has_voice'));
    }

    public function test_a_key_without_the_scope_is_forbidden(): void
    {
        $this->enable();
        $this->plan();
        [$token] = $this->client(['quote']); // no 'catalogue' scope

        $this->withToken($token)->getJson('/api/v1/catalogue')->assertForbidden();
    }

    public function test_no_token_is_unauthorized(): void
    {
        $this->enable();
        $this->getJson('/api/v1/catalogue')->assertUnauthorized();
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        // A client that forgets Accept: application/json must still get a JSON
        // 401 — never an HTML 302 redirect to the login page.
        $this->enable();
        $res = $this->get('/api/v1/catalogue'); // no Accept header

        $res->assertUnauthorized();
        $res->assertHeader('content-type', 'application/json');
        $res->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_revoked_client_is_forbidden(): void
    {
        $this->enable();
        [$token, $client] = $this->client(['catalogue']);
        app(ApiClientService::class)->revoke($client); // also deletes the token

        // Token is dead → unauthorized (Sanctum can't resolve it anymore).
        $this->withToken($token)->getJson('/api/v1/catalogue')->assertUnauthorized();
    }

    public function test_esim_quote_returns_the_developer_price(): void
    {
        $this->enable();
        $plan = $this->plan(['cost_price_usd' => 10.0]);
        [$token] = $this->client(['quote']);

        $res = $this->withToken($token)->postJson('/api/v1/quote', ['type' => 'esim', 'plan_id' => $plan->id])
            ->assertOk();
        $this->assertEquals(11.0, $res->json('price_usd'));
    }

    public function test_number_quote_prices_off_the_wholesale_cost(): void
    {
        $this->enable();
        config(['services.fivesim.api_key' => 'k']);
        \App\Support\ProviderKeys::flush();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20));
        [$token] = $this->client(['quote']);

        // dev SMS price = cost 0.20 * 1.15 = 0.23
        $res = $this->withToken($token)->postJson('/api/v1/quote', [
            'type' => 'number', 'number_type' => 'otp', 'country' => 'nigeria', 'service' => 'whatsapp',
        ])->assertOk();
        $this->assertEquals(0.23, $res->json('price_usd'));
    }
}
