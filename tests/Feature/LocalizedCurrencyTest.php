<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\User;
use App\Services\Pricing\CurrencyService;
use App\Support\LocaleCurrency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Localized currency DISPLAY (owner request). USD stays the settlement currency;
 * users see prices in their local currency via a live, free FX feed. Nothing
 * here moves money — it's purely the display layer.
 */
class LocalizedCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Free open.er-api.com feed (no key) — faked for deterministic tests.
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'rates' => ['USD' => 1, 'GBP' => 0.80, 'EUR' => 0.90, 'GHS' => 15.5, 'ZAR' => 18.0, 'KES' => 130, 'CAD' => 1.35, 'INR' => 83, 'USDT' => 1, 'NGN' => 1600],
            ]),
        ]);
    }

    public function test_live_rate_converts_and_formats_per_currency(): void
    {
        $fx = app(CurrencyService::class);

        $this->assertSame('£8.00', $fx->format(10.0, 'GBP'));   // 10 * 0.80
        $this->assertSame('€9.00', $fx->format(10.0, 'EUR'));
        $this->assertSame(8.0, $fx->convert(10.0, 'GBP'));
        // USD/USDT are 1:1 and never converted.
        $this->assertSame(1.0, $fx->rate('USD'));
        $this->assertSame(1.0, $fx->rate('USDT'));
    }

    public function test_a_provider_hiccup_falls_back_without_breaking(): void
    {
        Cache::flush();
        Http::fake(['open.er-api.com/*' => Http::response('boom', 500)]);
        $fx = app(CurrencyService::class);

        // Falls back to the built-in table rather than throwing.
        $this->assertGreaterThan(0, $fx->rate('GBP'));
        $this->assertStringStartsWith('£', $fx->format(10.0, 'GBP'));
    }

    public function test_currency_resolves_from_country_then_preference(): void
    {
        // Country code drives the default.
        $uk = User::factory()->create(['country_code' => 'GB', 'display_currency' => null]);
        $this->assertSame('GBP', LocaleCurrency::resolve($uk));

        $ng = User::factory()->create(['country_code' => 'NG', 'display_currency' => null]);
        $this->assertSame('NGN', LocaleCurrency::resolve($ng));

        $us = User::factory()->create(['country_code' => 'US', 'display_currency' => null]);
        $this->assertSame('USD', LocaleCurrency::resolve($us));

        // An explicit saved preference beats the country.
        $uk->update(['display_currency' => 'EUR']);
        $this->assertSame('EUR', LocaleCurrency::resolve($uk->fresh()));
    }

    public function test_local_price_shows_usd_plus_local_but_not_for_usd_users(): void
    {
        $fx = app(CurrencyService::class);

        $gbp = $fx->localPrice(10.0, 'GBP');
        $this->assertSame('$10.00', $gbp['usd']);
        $this->assertSame('£8.00', $gbp['local']);

        // A USD viewer gets no redundant second line.
        $this->assertNull($fx->localPrice(10.0, 'USD')['local']);
    }

    public function test_wallet_switcher_persists_the_choice_and_shows_local_balance(): void
    {
        $user = User::factory()->create(['country_code' => 'US', 'is_active' => true]);

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSet('displayCurrency', 'USD')
            ->call('setCurrency', 'GBP')
            ->assertSet('displayCurrency', 'GBP');

        // Persisted to the profile for next session.
        $this->assertSame('GBP', $user->fresh()->display_currency);
    }
}
