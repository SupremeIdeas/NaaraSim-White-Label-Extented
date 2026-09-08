<?php

namespace Tests\Feature;

use App\Livewire\GiftCards;
use App\Models\GiftCardProduct;
use App\Models\PricingEngineLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Support\GiftCardPricing;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Naara Gift — Phase 2: storefront + pricing. Retail flows through PricingEngine
 * (never the provider's suggested price), cost stays hidden, and the storefront
 * is feature-gated (404 until naara_gift is live). No money path yet (Phase 3).
 */
class NaaraGiftStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
    }

    private function enableFeature(): void
    {
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
    }

    private function product(array $attrs = []): GiftCardProduct
    {
        return GiftCardProduct::create(array_merge([
            'provider' => 'reloadly', 'provider_product_id' => 'p'.uniqid(),
            'brand_key' => 'amazon', 'brand_name' => 'Amazon', 'country' => 'US', 'currency' => 'USD',
            'denomination_type' => 'FIXED', 'fixed_denominations' => [25, 50, 100],
            'cost_meta' => ['discountPercentage' => 8, 'senderFee' => 1],
            'provider_enabled' => true, 'admin_enabled' => true, 'is_primary' => true,
        ], $attrs));
    }

    public function test_gift_card_retail_beats_cost_and_never_logs_on_display(): void
    {
        // $50 face, 8% discount + $1 fee → cost = 46; retail = cost + markup, floored.
        $retail = app(PricingEngine::class)->giftCardRetail(46.0, 'reloadly', log: false);

        $this->assertGreaterThan(46.0, $retail);          // never at/below cost
        $this->assertSame(0, PricingEngineLog::count()); // display doesn't log
    }

    public function test_denominations_are_priced_at_retail(): void
    {
        $p = $this->product();
        $denoms = app(GiftCardPricing::class)->denominations($p);

        $this->assertSame('FIXED', $denoms['type']);
        $this->assertCount(3, $denoms['options']);
        // Retail for the $50 card exceeds its ~$46 cost.
        $fifty = collect($denoms['options'])->firstWhere('face', 50.0);
        $this->assertGreaterThan(46.0, $fifty['retail']);
    }

    public function test_storefront_shows_coming_soon_without_keys_then_flips_live(): void
    {
        // Hermetic: ensure no provider keys leak in from the environment.
        config(['services.reloadly.client_id' => null, 'services.reloadly.client_secret' => null]);
        $this->product();
        $user = User::factory()->create(['is_active' => true]);
        Cache::forget('features.enabled.naara_gift');

        // Admin-on by default, no API keys yet → reachable Coming-Soon, not the store.
        Livewire::actingAs($user)->test(GiftCards::class)
            ->assertOk()->assertSee('coming soon')->assertDontSee('Amazon');

        // Keys added → the store flips live automatically (no manual editing).
        $this->enableFeature();
        Livewire::actingAs($user)->test(GiftCards::class)->assertOk()->assertSee('Amazon');
    }

    public function test_storefront_404s_only_when_the_admin_switches_it_off(): void
    {
        Setting::setValue('features.naara_gift.enabled', false);
        Cache::forget('features.enabled.naara_gift');
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(GiftCards::class)->assertStatus(404);
    }

    public function test_storefront_never_exposes_the_provider(): void
    {
        $this->enableFeature();
        $this->product(['brand_name' => 'Steam']);
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(GiftCards::class)
            ->assertSee('Steam')
            ->assertDontSee('reloadly')
            ->assertDontSee('cost_meta');
    }

    public function test_selecting_a_brand_loads_its_required_fields(): void
    {
        $this->enableFeature();
        $p = $this->product(['required_fields' => [['key' => 'email', 'label' => 'Recipient email', 'type' => 'email']]]);
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(GiftCards::class)
            ->call('select', $p->id)
            ->assertSet('selectedId', $p->id)
            ->assertSet('fields', ['email' => '']);
    }
}
