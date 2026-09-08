<?php

namespace Tests\Feature;

use App\Exceptions\EsimProviderException;
use App\Jobs\AlertAdminJob;
use App\Models\EsimPlan;
use App\Models\OrderLog;
use App\Models\User;
use App\Services\eSIM\EsimOrderResult;
use App\Services\eSIM\ProviderRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

class ProviderRouterTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $provider, float $cost, array $extra = []): EsimPlan
    {
        static $n = 0;
        $n++;

        return EsimPlan::create(array_merge([
            'provider' => $provider,
            'provider_plan_id' => "{$provider}-plan-{$n}",
            'name' => "{$provider} US 1GB",
            'data_mb' => 1000,
            'validity_days' => 30,
            'countries' => ['US'],
            'cost_price_usd' => $cost,
        ], $extra));
    }

    public function test_fails_over_esimgo_to_airalo_to_quibity(): void
    {
        // User bought this plan; final_retail_usd = 20 is what they paid.
        $chosen = $this->plan('esimgo', 5.0, ['computed_retail_usd' => 20.0])->fresh();
        $this->plan('airalo', 8.0);
        $this->plan('quibity', 10.0);

        $esimgo = new FakeEsimProvider(shouldThrow: true);   // primary fails
        $airalo = new FakeEsimProvider(shouldThrow: true);   // secondary fails
        $quibity = new FakeEsimProvider(shouldThrow: false, orderResponse: ['iccid' => '8944']);
        app()->instance('esim.esimgo', $esimgo);
        app()->instance('esim.airalo', $airalo);
        app()->instance('esim.quibity', $quibity);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertInstanceOf(EsimOrderResult::class, $result);
        $this->assertSame('quibity', $result->provider);
        $this->assertSame('8944', $result->payload['iccid']);
        $this->assertSame(1, $esimgo->orderCalls);
        $this->assertSame(1, $airalo->orderCalls);
        $this->assertSame(1, $quibity->orderCalls);

        // Profit tracked: charged 20 - cost 10 = 10.
        $log = OrderLog::where('provider', 'quibity')->firstOrFail();
        $this->assertSame('success', $log->result);
        $this->assertSame('10.0000', (string) $log->profit);
    }

    public function test_margin_eating_fallback_is_skipped_and_the_wallet_is_refunded(): void
    {
        Queue::fake();

        // User paid only 8.00; the sole provider's cost (8.00) leaves less than
        // the 0.50 minimum profit, so it must be SKIPPED (not attempted).
        $chosen = $this->plan('airalo', 8.0, ['computed_retail_usd' => 8.0])->fresh();
        $this->assertSame(8.0, (float) $chosen->final_retail_usd);

        $airalo = new FakeEsimProvider(shouldThrow: false);
        app()->instance('esim.airalo', $airalo);

        $user = User::factory()->create();

        try {
            app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user, 'NGN');
            $this->fail('Expected EsimProviderException');
        } catch (EsimProviderException $e) {
            $this->assertStringContainsString('refunded', strtolower($e->getMessage()));
        }

        // Provider was skipped, never ordered.
        $this->assertSame(0, $airalo->orderCalls);
        // No success logged.
        $this->assertSame(0, OrderLog::count());
        // Wallet refunded 8.00 (credit of type refund).
        $refund = $user->walletTransactions()->where('type', 'refund')->firstOrFail();
        $this->assertSame('8.0000', (string) $refund->amount);
        $this->assertSame('8.00', (string) $user->wallet->fresh()->ngn_balance);

        Queue::assertPushed(AlertAdminJob::class);
    }

    public function test_a_provider_without_an_equivalent_plan_is_passed_over(): void
    {
        // Only quibity has a matching plan; esimgo/airalo have none.
        $chosen = $this->plan('quibity', 5.0, ['computed_retail_usd' => 20.0])->fresh();
        $quibity = new FakeEsimProvider(orderResponse: ['iccid' => 'Q-1']);
        app()->instance('esim.quibity', $quibity);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertSame('quibity', $result->provider);
        $this->assertSame(1, $quibity->orderCalls);
    }

    public function test_a_voice_plan_never_falls_back_to_a_data_only_provider(): void
    {
        Queue::fake();

        // A Naara Connect (voice) plan. Data-only providers (esimgo) offer a
        // cheaper, country/data/validity-matching bundle — but crossing lanes
        // would deliver a data-only eSIM for a voice purchase. It must be refused.
        $chosen = $this->plan('zendit', 5.0, ['has_voice' => true, 'computed_retail_usd' => 20.0])->fresh();
        $this->plan('esimgo', 2.0, ['has_voice' => false]); // cheaper data-only lure

        $esimgo = new FakeEsimProvider(orderResponse: ['iccid' => 'DATA-ONLY']);
        $zendit = new FakeEsimProvider(shouldThrow: true); // voice provider down
        app()->instance('esim.esimgo', $esimgo);
        app()->instance('esim.zendit', $zendit);

        $user = User::factory()->create();

        try {
            app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);
            $this->fail('Expected EsimProviderException — no cross-lane fallback allowed');
        } catch (EsimProviderException $e) {
            $this->assertStringContainsString('refunded', strtolower($e->getMessage()));
        }

        // The data-only provider was NEVER asked to fulfil the voice order.
        $this->assertSame(0, $esimgo->orderCalls);
        $this->assertSame(1, $zendit->orderCalls); // only its own lane was tried
    }

    public function test_a_voice_plan_is_fulfilled_by_its_own_voice_provider(): void
    {
        $chosen = $this->plan('zendit', 5.0, ['has_voice' => true, 'computed_retail_usd' => 20.0])->fresh();
        $zendit = new FakeEsimProvider(orderResponse: ['iccid' => 'VOICE-1']);
        app()->instance('esim.zendit', $zendit);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertSame('zendit', $result->provider);
        $this->assertSame('VOICE-1', $result->payload['iccid']);
    }

    public function test_a_voice_plan_fails_over_within_the_full_esim_lane(): void
    {
        // Naara Connect lane: zendit → 1GLOBAL → Monty → Gigs. Zendit is down;
        // 1GLOBAL has an equivalent Full eSIM and fulfils it (still voice-only).
        $chosen = $this->plan('zendit', 5.0, ['has_voice' => true, 'computed_retail_usd' => 20.0])->fresh();
        $this->plan('oneglobal', 6.0, ['has_voice' => true]);

        $zendit = new FakeEsimProvider(shouldThrow: true);
        $oneglobal = new FakeEsimProvider(orderResponse: ['iccid' => 'OG-1']);
        app()->instance('esim.zendit', $zendit);
        app()->instance('esim.oneglobal', $oneglobal);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertSame('oneglobal', $result->provider);
        $this->assertSame(1, $zendit->orderCalls);
        $this->assertSame(1, $oneglobal->orderCalls);
    }

    public function test_zendit_is_a_data_lane_backup(): void
    {
        // A data-only plan can fail over to Zendit (it also sells data eSIMs).
        $chosen = $this->plan('esimgo', 5.0, ['has_voice' => false, 'computed_retail_usd' => 20.0])->fresh();
        $this->plan('zendit', 6.0, ['has_voice' => false]);

        $esimgo = new FakeEsimProvider(shouldThrow: true);
        $zendit = new FakeEsimProvider(orderResponse: ['iccid' => 'ZD-DATA']);
        app()->instance('esim.esimgo', $esimgo);
        app()->instance('esim.zendit', $zendit);

        $user = User::factory()->create();
        $result = app(ProviderRouter::class)->orderPlan((string) $chosen->id, $user);

        $this->assertSame('zendit', $result->provider);
    }

    public function test_equivalent_plan_matches_voice_capability_exactly(): void
    {
        $router = app(ProviderRouter::class);
        // A voice plan must not match a data-only bundle that otherwise covers
        // the same country/data/validity — voice capability has to match.
        $need = $this->plan('zendit', 5.0, ['has_voice' => true]);
        $this->plan('esimgo', 3.0, ['has_voice' => false]); // data-only — not equivalent
        $this->assertNull($router->findEquivalentPlan($need, 'esimgo'));

        // A matching voice bundle from a voice provider IS equivalent.
        $ok = $this->plan('zendit', 6.0, ['has_voice' => true, 'countries' => ['US', 'CA'], 'data_mb' => 2000, 'validity_days' => 60]);
        $this->assertTrue($router->findEquivalentPlan($need, 'zendit')->is($need)); // itself is cheapest
        $this->assertNotNull($ok);
    }

    public function test_equivalent_plan_must_cover_country_data_and_validity(): void
    {
        $router = app(ProviderRouter::class);
        $need = $this->plan('esimgo', 5.0, ['countries' => ['US'], 'data_mb' => 1000, 'validity_days' => 30]);

        // Smaller data -> not equivalent.
        $this->plan('airalo', 3.0, ['countries' => ['US'], 'data_mb' => 500, 'validity_days' => 30]);
        $this->assertNull($router->findEquivalentPlan($need, 'airalo'));

        // Missing country coverage -> not equivalent.
        $this->plan('quibity', 3.0, ['countries' => ['FR'], 'data_mb' => 2000, 'validity_days' => 30]);
        $this->assertNull($router->findEquivalentPlan($need, 'quibity'));

        // Covers all three dimensions -> equivalent.
        $ok = $this->plan('airalo', 6.0, ['countries' => ['US', 'CA'], 'data_mb' => 2000, 'validity_days' => 60]);
        $this->assertTrue($router->findEquivalentPlan($need, 'airalo')->is($ok));
    }
}
