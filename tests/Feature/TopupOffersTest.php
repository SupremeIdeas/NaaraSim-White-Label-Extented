<?php

namespace Tests\Feature;

use App\Services\Topup\TopupCredit;
use App\Services\Topup\TopupOffers;
use App\Support\Topup\TopupBrandTint;
use App\Support\TopupSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The "pay with credit" offers: a card never promises more than the quote can honour, and cost never reaches the page. */
class TopupOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PricingSettingsSeeder::class);
        config(['queue.default' => 'sync', 'services.zendit.api_key' => 'k']);
        $this->app->instance('topup.provider.zendit', new \Tests\Support\FakeTopupProvider('zendit'));
        app(\App\Services\Topup\TopupOps::class)->seed();
        \App\Models\TopupProvider::query()->update(['enabled' => true]);
        \App\Models\TopupProduct::query()->delete();   // the shipped snapshot is not part of these cases
        \App\Models\Setting::setValue('features.naara_topup.enabled', true);
        \App\Models\Setting::setValue('credits.enabled', true);
        \App\Support\CreditSettings::flush();
        TopupSettings::set('daily_cap_usd', 500);
        TopupSettings::set('new_account_daily_cap_usd', 500);
        TopupSettings::set('markup_pct', 10);
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function bundle(float $cost = 2.0): \App\Models\TopupProduct
    {
        return \App\Models\TopupProduct::create(['provider_code' => 'zendit', 'provider_sku' => 'sku-'.uniqid(), 'country_iso' => 'NG', 'operator_name' => 'MTN Nigeria', 'type' => 'data',
            'name' => '2GB', 'data_mb' => 2048, 'validity_text' => '30 days', 'currency' => 'NGN', 'is_fixed' => true, 'fixed_amount' => 3000, 'cost_native' => $cost, 'cost_currency' => 'USD', 'active' => true]);
    }

    private function member(float $usd = 50): \App\Models\User
    {
        $u = \App\Models\User::factory()->create(['is_active' => true, 'created_at' => now()->subMonths(2)]);
        app(\App\Services\Wallet\WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'seed:'.$u->id]);

        return $u;
    }

    public function test_an_offer_is_bought_in_one_tap_from_the_sheet_and_the_wallet_is_charged_once(): void
    {
        $p = $this->bundle();
        $u = $this->member();
        $c = \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Topup::class)
            ->call('openOffer', $p->id)->set('offerPhone', '08031234567');
        $this->assertNotNull($c->get('offerQuoteId'), $c->get('offerError') ?? 'no quote');
        $c->call('payOffer')->assertRedirect();
        $this->assertSame(1, \App\Models\TopupOrder::where('user_id', $u->id)->count());
    }

    public function test_a_number_on_the_wrong_country_never_gets_a_quote(): void
    {
        $p = $this->bundle();
        $c = \Livewire\Livewire::actingAs($this->member())->test(\App\Livewire\Topup::class)->call('openOffer', $p->id)->set('offerPhone', '+447911123456');
        $this->assertNull($c->get('offerQuoteId'));
        $this->assertNotEmpty($c->get('offerError'));
    }

    public function test_the_offer_markup_prices_offers_only_and_the_minimum_profit_still_holds(): void
    {
        $p = $this->bundle(2.0);
        $svc = app(\App\Services\Topup\QuoteService::class);
        $std = $svc->price($p, 3000)['price'];
        TopupSettings::set('offers_markup_pct', 25);
        $this->assertGreaterThan($std, $svc->price($p, 3000, offer: true)['price']);
        $this->assertSame($std, $svc->price($p, 3000)['price'], 'the widget price is untouched');
        TopupSettings::set('offers_markup_pct', 0);
        $this->assertGreaterThanOrEqual(2.05, $svc->price($p, 3000, offer: true)['price'], 'never below cost plus the minimum profit');
    }

    public function test_the_strip_shows_every_network_in_its_own_colours_not_just_the_best_percentage(): void
    {
        foreach (['MTN Nigeria' => 2.0, 'Glo Nigeria' => 2.4, 'Airtel Nigeria' => 9.0, 'Airtel Data Nigeria' => 9.5, '9mobile Nigeria' => 3.0] as $op => $cost) {
            foreach ([1, 2, 3] as $n) {
                $p = $this->bundle($cost + $n);
                $p->update(['operator_name' => $op]);
            }
        }
        $offers = app(TopupOffers::class)->for('NG', 8);
        $fams = array_unique(array_map(fn ($o) => TopupBrandTint::family($o['operator'], 'NG'), $offers));
        $this->assertGreaterThanOrEqual(4, count($fams), 'MTN, Glo, Airtel and 9mobile each appear');
        $first4 = array_slice(array_map(fn ($o) => TopupBrandTint::family($o['operator'], 'NG'), $offers), 0, 4);
        $this->assertCount(4, array_unique($first4), 'the first four cards are four different networks');
        foreach ($offers as $o) {
            $this->assertGreaterThanOrEqual(5.4, TopupBrandTint::contrast($o['tint'], $o['ink']));
        }
    }

    public function test_offers_are_sorted_by_country_and_the_customer_can_switch_country(): void
    {
        $ng = $this->bundle(2.0);
        $gh = $this->bundle(2.5);
        $gh->update(['country_iso' => 'GH', 'operator_name' => 'MTN Ghana']);
        \App\Models\TopupRoute::query()->update(['enabled' => true]);
        TopupOffers::flush();
        $countries = app(TopupOffers::class)->countries();
        $this->assertContains('NG', $countries);
        $this->assertContains('GH', $countries);
        $this->assertSame('NG', $countries[0], 'popular countries lead, Nigeria first');
        $this->assertSame('MTN Ghana', app(TopupOffers::class)->for('GH')[0]['operator']);
        $this->assertSame('MTN Nigeria', app(TopupOffers::class)->for('NG')[0]['operator']);
        $c = \Livewire\Livewire::actingAs($this->member())->test(\App\Livewire\Topup::class)->call('setOfferCountry', 'gh');
        $this->assertSame('GH', $c->get('offerCountry'));
        $c->assertSee('MTN Ghana', false);
    }

    public function test_the_admin_can_hide_pin_and_switch_the_strip_off(): void
    {
        $p = $this->bundle(2.0);
        $this->assertCount(1, app(TopupOffers::class)->for('NG'));
        TopupSettings::set('offers_hidden', (string) $p->id);
        TopupOffers::flush();
        $this->assertCount(0, app(TopupOffers::class)->for('NG'));
        TopupSettings::set('offers_hidden', '');
        TopupSettings::set('offers_enabled', false);
        TopupOffers::flush();
        $this->assertSame([], app(TopupOffers::class)->for('NG'));
    }

    public function test_the_ceiling_is_a_slice_of_profit_capped_and_rounded_down(): void
    {
        TopupSettings::set('credit_discount_enabled', true);
        $c = TopupCredit::ceiling(10.0, 8.0);                    // profit 2.00, 20% share = 0.40, under the 0.50 cap
        $this->assertSame(0.4, $c);
        $this->assertLessThanOrEqual(0.5, TopupCredit::ceiling(100.0, 50.0));
        $this->assertSame(0.0, TopupCredit::ceiling(5.0, 5.0), 'no profit, no discount');
        $this->assertSame(0.0, TopupCredit::ceiling(5.0, 6.0));
    }

    public function test_it_is_zero_when_the_admin_switches_it_off(): void
    {
        TopupSettings::set('credit_discount_enabled', false);
        $this->assertSame(0.0, TopupCredit::ceiling(10.0, 8.0));
    }

    public function test_brand_tints_are_known_for_big_networks_and_stable_otherwise(): void
    {
        $this->assertSame('#FFC400', TopupBrandTint::for('MTN Nigeria'), 'yellow is already readable with dark ink: untouched');
        $airtel = TopupBrandTint::for('Airtel Nigeria');
        $this->assertGreaterThanOrEqual(5.5, TopupBrandTint::contrast($airtel, TopupBrandTint::ink($airtel)), 'a brand colour is nudged only as far as the contrast needs');
        $this->assertGreaterThan(hexdec(substr($airtel, 1, 2)), 0xE5, 'and it stays Airtel red');
        $this->assertSame(TopupBrandTint::for('Some Local Telco'), TopupBrandTint::for('Some Local Telco'));
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', TopupBrandTint::for('Some Local Telco'));
    }

    public function test_offers_for_an_unsold_country_are_empty(): void
    {
        $this->assertSame([], app(TopupOffers::class)->for('ZZ'));
    }
}
