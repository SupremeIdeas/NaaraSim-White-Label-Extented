<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ApiOrder;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Api\ApiClientService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Developer API ordering (ROADMAP §Layer 2) — the money path. Bills the prepaid
 * API wallet at the developer price, fulfils via the shared provider loop, and
 * keeps every money-safety guarantee: charge-first, refund on failure, orphan
 * guard, idempotent, cost never exposed.
 */
class DeveloperApiOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue('developer_api.enabled', true, 'developer_api');
    }

    /** @return array{0:string,1:ApiClient} */
    private function client(float $balance = 20.0, array $scopes = ['order', 'status']): array
    {
        ['client' => $client, 'token' => $token] = app(ApiClientService::class)
            ->create(User::factory()->create(), 'app', $scopes);
        $client->update(['prepaid_balance_usd' => $balance]);

        return [$token, $client->fresh()];
    }

    private function esimgoPlan(float $cost = 10.0): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'esimgo-1', 'name' => 'NG 1GB',
            'type' => 'local', 'data_mb' => 1024, 'countries' => ['NG'], 'validity_days' => 30,
            'cost_price_usd' => $cost, 'is_active' => true,
        ]);
    }

    private function fakeProvider(bool $throws = false): void
    {
        app()->instance('esim.esimgo', new FakeEsimProvider(
            shouldThrow: $throws,
            orderResponse: ['iccid' => '8944DEMO', 'qr_code' => 'https://x/qr.png', 'lpa' => 'LPA:1$demo$ACT'],
        ));
    }

    public function test_order_charges_the_api_wallet_and_returns_masked_delivery(): void
    {
        $plan = $this->esimgoPlan(10.0); // dev price = 10 * 1.10 = 11.00
        $this->fakeProvider();
        [$token, $client] = $this->client(balance: 20.0);

        $res = $this->withToken($token)->postJson('/api/v1/orders', [
            'type' => 'esim', 'plan_id' => $plan->id, 'reference' => 'dev-ref-1',
        ])->assertCreated();

        $this->assertEquals(11.0, $res->json('price_usd'));
        $this->assertSame('8944DEMO', $res->json('result.iccid'));
        $this->assertSame('9.0000', (string) $client->fresh()->prepaid_balance_usd); // 20 - 11

        // Real fulfilment recorded; developer body never leaks cost or supplier.
        $this->assertSame(1, EsimOrder::count());
        $body = strtolower($res->getContent());
        $this->assertStringNotContainsString('esimgo', $body);
        $this->assertStringNotContainsString('cost', $body);
        $this->assertStringNotContainsString('wholesale', $body);
    }

    public function test_insufficient_api_balance_is_402_and_provisions_nothing(): void
    {
        $plan = $this->esimgoPlan();
        $this->fakeProvider();
        [$token] = $this->client(balance: 1.0); // can't cover 11

        $this->withToken($token)->postJson('/api/v1/orders', ['type' => 'esim', 'plan_id' => $plan->id])
            ->assertStatus(402);

        $this->assertSame(0, EsimOrder::count());
        $this->assertSame(0, ApiOrder::count());
    }

    public function test_provider_failure_refunds_the_api_wallet(): void
    {
        Queue::fake();
        $plan = $this->esimgoPlan();
        $this->fakeProvider(throws: true); // only provider fails → lane exhausted
        [$token, $client] = $this->client(balance: 20.0);

        $this->withToken($token)->postJson('/api/v1/orders', ['type' => 'esim', 'plan_id' => $plan->id, 'reference' => 'r'])
            ->assertStatus(502);

        $this->assertSame('20.0000', (string) $client->fresh()->prepaid_balance_usd); // fully refunded
        $this->assertSame(0, EsimOrder::count());
        $this->assertSame(0, ApiOrder::count());
    }

    public function test_a_repeated_reference_is_idempotent_no_double_charge_or_provision(): void
    {
        $plan = $this->esimgoPlan(10.0);
        $this->fakeProvider();
        [$token, $client] = $this->client(balance: 20.0);
        $payload = ['type' => 'esim', 'plan_id' => $plan->id, 'reference' => 'once'];

        $first = $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertCreated();
        $again = $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertOk();

        $this->assertSame($first->json('reference'), $again->json('reference'));
        $this->assertSame('9.0000', (string) $client->fresh()->prepaid_balance_usd); // charged once
        $this->assertSame(1, EsimOrder::count());  // provisioned once
        $this->assertSame(1, ApiOrder::count());
    }

    public function test_a_concurrent_duplicate_reference_never_double_provisions(): void
    {
        $plan = $this->esimgoPlan(10.0); // dev price = 11.00
        $this->fakeProvider();
        [$token, $client] = $this->client(balance: 20.0);

        // Simulate the race window the upfront ApiOrder check can't cover: a
        // sibling request with the same reference has ALREADY charged the wallet
        // (idempotent debit posted) but has not yet persisted its ApiOrder. The
        // debit in this request must be recognised as a replay and NOT fulfil.
        app(\App\Services\Api\ApiWalletService::class)->debit($client, 11.0, [
            'reference' => 'api-order:dup', 'description' => 'sibling',
        ]);

        $this->withToken($token)->postJson('/api/v1/orders', [
            'type' => 'esim', 'plan_id' => $plan->id, 'reference' => 'dup',
        ])->assertStatus(409);

        // No second charge, and the provider was never ordered again.
        $this->assertSame('9.0000', (string) $client->fresh()->prepaid_balance_usd);
        $this->assertSame(0, EsimOrder::count());
        $this->assertSame(0, ApiOrder::count());
    }

    public function test_status_endpoint_returns_the_order_for_the_owning_client_only(): void
    {
        $plan = $this->esimgoPlan();
        $this->fakeProvider();
        [$token] = $this->client(balance: 20.0);

        $this->withToken($token)->postJson('/api/v1/orders', ['type' => 'esim', 'plan_id' => $plan->id, 'reference' => 'track-1'])
            ->assertCreated();

        $this->withToken($token)->getJson('/api/v1/orders/track-1')
            ->assertOk()->assertJsonPath('reference', 'track-1');

        $this->withToken($token)->getJson('/api/v1/orders/nope')->assertNotFound();
    }

    public function test_order_scope_is_required(): void
    {
        $plan = $this->esimgoPlan();
        $this->fakeProvider();
        [$token] = $this->client(balance: 20.0, scopes: ['catalogue']); // no 'order'

        $this->withToken($token)->postJson('/api/v1/orders', ['type' => 'esim', 'plan_id' => $plan->id])
            ->assertForbidden();
    }

    // ---- number ordering ----------------------------------------------------

    private function fakeNumberProvider(): void
    {
        config(['services.fivesim.api_key' => 'k']);
        \App\Support\ProviderKeys::flush();
        app()->instance('number.fivesim', new \Tests\Support\FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-API', 'number' => '+2348010000000', 'cost' => 0.20,
            'status' => \App\Services\SMS\OtpStatus::PENDING,
        ]));
    }

    public function test_number_order_charges_the_dev_price_and_returns_the_number(): void
    {
        \Illuminate\Support\Facades\Queue::fake(); // PollSmsOtpJob dispatched, not run
        $this->fakeNumberProvider();
        [$token, $client] = $this->client(balance: 20.0, scopes: ['order', 'status']);

        // dev number price = cost 0.20 * 1.15 = 0.23
        $res = $this->withToken($token)->postJson('/api/v1/orders', [
            'type' => 'number', 'number_type' => 'otp', 'country' => 'nigeria', 'service' => 'whatsapp',
            'reference' => 'num-1',
        ])->assertCreated();

        $this->assertEquals(0.23, $res->json('price_usd'));
        $this->assertSame('+2348010000000', $res->json('result.number'));
        $this->assertSame('number', $res->json('kind'));
        $this->assertSame('19.7700', (string) $client->fresh()->prepaid_balance_usd); // 20 - 0.23
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PollSmsOtpJob::class);

        // Supplier never leaked.
        $this->assertStringNotContainsString('fivesim', strtolower($res->getContent()));
    }

    public function test_number_status_surfaces_the_code_once_it_arrives(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->fakeNumberProvider();
        [$token] = $this->client(balance: 20.0, scopes: ['order', 'status']);

        $this->withToken($token)->postJson('/api/v1/orders', [
            'type' => 'number', 'number_type' => 'otp', 'country' => 'nigeria', 'service' => 'whatsapp',
            'reference' => 'num-2',
        ])->assertCreated();

        // Simulate the poll job completing the SMS order with a code.
        \App\Models\SmsOrder::where('phone_number', '+2348010000000')
            ->update(['status' => 'completed', 'otp_code' => '445566']);

        $this->withToken($token)->getJson('/api/v1/orders/num-2')
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result.code', '445566');
    }
}
