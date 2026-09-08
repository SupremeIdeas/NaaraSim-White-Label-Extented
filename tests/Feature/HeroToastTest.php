<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\EsimPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * The transaction "hero" toaster (platform audit §8). One toast engine, a hero
 * variant for money outcomes: dispatched only AFTER the action commits
 * (server-anchored), success auto-dismisses, failures are sticky and reassuring
 * ("you were not charged"). SVG glyphs only.
 */
class HeroToastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'ht-'.uniqid(), 'name' => 'USA 3GB 30D',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    public function test_the_hero_layer_and_glyphs_render_in_the_engine(): void
    {
        $html = Blade::render('<x-ui.toast-stack />');
        // One engine handles both shapes; the hero layer + drawn glyphs are present.
        $this->assertStringContainsString('nx-hero', $html);
        $this->assertStringContainsString("(detail.variant || 'toast') === 'hero'", $html);
        $this->assertStringContainsString('nx-hero__draw', $html);
        // SVG glyphs only — no emoji anywhere in the engine.
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    }

    public function test_a_successful_checkout_dispatches_a_hero_success_toast(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', true)
            ->assertDispatched('nx-toast', variant: 'hero', type: 'success');
    }

    public function test_a_failed_checkout_dispatches_a_sticky_hero_error_toast(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        // eSIM Go throws and no fallback exists → total failure, wallet refunded.
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: true));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', false)
            ->assertDispatched('nx-toast', variant: 'hero', type: 'error');

        // The user is reassured they were not charged (money-safety copy).
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
    }
}
