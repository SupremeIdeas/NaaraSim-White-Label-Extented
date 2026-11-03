<?php

namespace Tests\Feature;

use App\Livewire\GiftCards;
use App\Models\GiftCardProduct;
use App\Models\Setting;
use App\Services\GiftCards\ZenditVoucherService;
use App\Services\Pricing\PricingEngine;
use App\Support\GiftCardCategories;
use App\Support\GiftCardPricing;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** Gift cards: priced from the provider's REAL wholesale cost (Zendit lists it per offer) and sorted by one shared category taxonomy. */
class GiftCardRealCostAndCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
    }

    private function zendit(float $face, float $discount, array $extra = []): GiftCardProduct
    {
        return GiftCardProduct::create(array_merge([
            'provider' => 'zendit', 'provider_product_id' => 'z'.uniqid(), 'brand_key' => 'brand'.uniqid(), 'brand_name' => 'Some Brand',
            'country' => 'US', 'currency' => 'USD', 'denomination_type' => 'FIXED', 'fixed_denominations' => [$face],
            'cost_meta' => ['cost' => ['currencyDivisor' => 100, 'discount' => $discount, 'fee' => 0, 'feePct' => 0]],
            'provider_enabled' => true, 'admin_enabled' => true, 'is_primary' => true, 'priceable' => true,
        ], $extra));
    }

    public function test_face_mode_charges_the_face_value_and_the_provider_discount_is_the_margin(): void
    {
        $p = $this->zendit(100, 0.05);                         // Zendit sells us a $100 card for $95
        $pricing = app(GiftCardPricing::class);

        $this->assertSame(100.0, $pricing->retail($p, 100.0)); // customer pays exactly what they see
    }

    public function test_cost_plus_mode_still_available_and_uses_the_real_cost(): void
    {
        Setting::setValue('pricing.giftcard_mode', 'cost_plus');
        Setting::setValue('pricing.giftcard_markup_pct', 8);
        $p = $this->zendit(100, 0.05);

        $this->assertSame(102.6, app(GiftCardPricing::class)->retail($p, 100.0));   // 95 x 1.08, not 100 x 1.08
    }

    public function test_a_card_that_costs_more_than_face_is_never_sold_below_cost(): void
    {
        $p = $this->zendit(35.75, -0.089);                     // like American Eagle CA: cost 38.93 on a 35.75 face
        $retail = app(GiftCardPricing::class)->retail($p, 35.75);

        $this->assertGreaterThanOrEqual(38.93 + 0.25, $retail);
        $this->assertGreaterThan(35.75, $retail);
    }

    public function test_a_row_without_the_cost_object_falls_back_to_face_as_cost(): void
    {
        $p = $this->zendit(10, 0.0, ['cost_meta' => []]);

        $this->assertSame(10.25, app(GiftCardPricing::class)->retail($p, 10.0));            // face + the 0.25 floor
    }

    public function test_an_absurd_provider_discount_is_capped_not_trusted(): void
    {
        $p = $this->zendit(100, 0.9);                          // 90% off is a feed error: we assume at most 25% off
        $retail = app(GiftCardPricing::class)->retail($p, 100.0);

        $this->assertSame(100.0, $retail);                     // face mode keeps face; cost assumed 75, never 10
    }

    public function test_zendit_withholds_an_offer_whose_cost_is_far_above_face(): void
    {
        $svc = app(ZenditVoucherService::class);
        $m = new \ReflectionMethod($svc, 'map');
        $m->setAccessible(true);
        $row = fn (float $disc) => $m->invoke($svc, [
            'offerId' => 'X', 'brandName' => 'X', 'country' => 'US', 'priceType' => 'FIXED',
            'price' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fixed' => 1000], 'cost' => ['currency' => 'USD', 'discount' => $disc], 'subTypes' => ['Gaming & Entertainment'],
        ]);

        $this->assertFalse($row(-2.6)['priceable']);           // AMAZON_AE_OPEN style glitch
        $this->assertTrue($row(0.05)['priceable']);
    }

    public function test_categories_resolve_provider_wording_to_one_taxonomy(): void
    {
        $this->assertSame('games', GiftCardCategories::resolve('Gaming & Entertainment', 'Steam'));
        $this->assertSame('games-entertainment', GiftCardCategories::resolve('Gaming & Entertainment', 'Netflix'));
        $this->assertSame('digital-apps', GiftCardCategories::resolve('Digital Apps', 'Google Play'));
        $this->assertSame('shopping', GiftCardCategories::resolve('Online shopping', 'Jumia'));
        $this->assertSame('shopping', GiftCardCategories::resolve('Digital Apps', 'Amazon'));         // Amazon is shopping whatever Zendit files it under
        $this->assertSame('clothing-accessories', GiftCardCategories::resolve('apparel', 'Zara'));    // "apparel" must not match "app"
        $this->assertSame('food-beverage', GiftCardCategories::resolve('Restaurant', 'Nando'));
        $this->assertSame('travel', GiftCardCategories::resolve('Travel & Experience', 'Airbnb'));
        $this->assertSame('top-ups', GiftCardCategories::resolve('pin refill', 'Vectone'));
        $this->assertSame('shopping', GiftCardCategories::resolve('Online Shopping', 'Some Shop'));   // "shopping" must not match "pin"
        $this->assertSame('software', GiftCardCategories::resolve(null, 'NordVPN'));
    }

    public function test_storefront_filters_by_category_and_only_offers_chips_that_have_cards(): void
    {
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
        $this->zendit(10, 0.02, ['brand_name' => 'Zorblax Arcade', 'brand_key' => 'zorblax', 'category_key' => 'games']);
        $this->zendit(10, 0.02, ['brand_name' => 'Airbnb', 'brand_key' => 'airbnb', 'category_key' => 'travel']);

        Livewire::test(GiftCards::class)
            ->assertSee('Games')->assertSee('Travel')->assertDontSee('Software')   // no software cards: no software chip
            ->call('pickCategory', 'travel')
            ->assertSee('Airbnb')->assertDontSee('Zorblax Arcade')
            ->call('pickCategory', 'travel')                                        // tapping the active chip clears it
            ->assertSee('Zorblax Arcade')->assertSee('Airbnb');
    }

    public function test_face_pricing_never_treats_a_non_usd_face_as_dollars(): void
    {
        $p = GiftCardProduct::create([
            'provider' => 'reloadly', 'provider_product_id' => 'r'.uniqid(), 'brand_key' => 'ng', 'brand_name' => 'Naira Card', 'country' => 'NG', 'currency' => 'NGN',
            'denomination_type' => 'FIXED', 'fixed_denominations' => [5000],
            'cost_meta' => ['discountPercentage' => 0, 'senderFee' => 0, 'senderCurrencyCode' => 'USD', 'senderMap' => ['5000' => 3.2]],
            'provider_enabled' => true, 'admin_enabled' => true, 'is_primary' => true, 'priceable' => true,
        ]);

        $this->assertLessThan(10.0, app(GiftCardPricing::class)->retail($p, 5000.0));   // ~$3.46, not $5,000
    }

    public function test_our_own_logos_load_from_the_current_site_whatever_host_the_sync_stored(): void
    {
        $own = $this->zendit(10, 0.02, ['logo_url' => 'http://localhost/images/giftcards/logos/uber.webp']);
        $stor = $this->zendit(10, 0.02, ['logo_url' => 'https://old-staging.example:8125/storage/giftcards/logos/x.webp']);
        $ext = $this->zendit(10, 0.02, ['logo_url' => 'https://cdn.example.com/amazon.png']);
        $none = $this->zendit(10, 0.02, ['logo_url' => null]);

        $this->assertSame('/images/giftcards/logos/uber.webp', $own->fresh()->logo_url);       // a stale host never breaks our own files
        $this->assertSame('/storage/giftcards/logos/x.webp', $stor->fresh()->logo_url);
        $this->assertSame('https://cdn.example.com/amazon.png', $ext->fresh()->logo_url);      // a third-party address is left alone
        $this->assertNull($none->fresh()->logo_url);
    }

    public function test_pill_counts_equal_the_cards_you_get_and_follow_the_search(): void
    {
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
        foreach ([['Airbnb', 'travel'], ['Air Canada', 'travel'], ['Zorblax Arcade', 'games']] as [$n, $k]) {
            $this->zendit(10, 0.02, ['brand_name' => $n, 'brand_key' => str($n)->slug()->toString(), 'category_key' => $k]);
        }

        $c = Livewire::test(GiftCards::class);
        $this->assertSame(3, $c->viewData('categoryTotal'));
        $this->assertSame(['games' => 1, 'travel' => 2], collect($c->viewData('categories'))->pluck('n', 'key')->all());

        $c->set('search', 'air');                                  // typing narrows the pills too: Games disappears, Travel keeps both
        $this->assertSame(['travel' => 2], collect($c->viewData('categories'))->pluck('n', 'key')->all());
        $this->assertSame(2, $c->viewData('categoryTotal'));
    }
}
