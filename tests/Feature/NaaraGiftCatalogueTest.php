<?php

namespace Tests\Feature;

use App\Models\GiftCardProduct;
use App\Services\GiftCards\GiftCardCatalogueSyncService;
use App\Services\GiftCards\ReloadlyGiftCardService;
use App\Services\GiftCards\ZenditVoucherService;
use App\Support\GiftCardPricing;
use App\Support\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Naara Gift — Phase 1: dual-provider catalogue. Reloadly (primary) + Zendit
 * (failover) normalize into one catalogue; Reloadly wins per brand+country;
 * provider identity + cost are never exposed (scrub rule). Sandbox-only, HTTP
 * faked — no real API, no money path (that's Phase 2).
 */
class NaaraGiftCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.reloadly.client_id' => 'id',
            'services.reloadly.client_secret' => 'secret',
            'services.reloadly.sandbox' => true,
            'services.reloadly.auth_url' => 'https://auth.reloadly.com/oauth/token',
            'services.zendit.api_key' => 'zkey',
            'services.zendit.base_url' => 'https://test-api.zendit.io/v1',
        ]);
    }

    private function fakeReloadly(array $products): void
    {
        Http::fake([
            'auth.reloadly.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'giftcards-sandbox.reloadly.com/products*' => Http::response(['content' => $products, 'last' => true]),
            'giftcards-sandbox.reloadly.com/accounts/balance' => Http::response(['balance' => 500, 'currencyCode' => 'USD']),
        ]);
    }

    private function reloadlyProduct(string $brand, string $iso, int $id): array
    {
        return [
            'productId' => $id, 'productName' => "{$brand} Gift Card", 'denominationType' => 'FIXED',
            'recipientCurrencyCode' => 'USD', 'senderCurrencyCode' => 'USD',
            'fixedRecipientDenominations' => [25, 50, 100],
            'senderFee' => 1.0, 'discountPercentage' => 7.5,
            'logoUrls' => ["https://cdn/{$brand}.png"], 'brand' => ['brandName' => $brand],
            'category' => ['name' => 'Shopping'], 'country' => ['isoName' => $iso],
            'redeemInstruction' => ['verbose' => 'Redeem at site', 'concise' => 'Redeem'],
        ];
    }

    private function zenditOffer(string $brand, string $iso, string $id): array
    {
        return [
            'offerId' => $id, 'brandName' => $brand, 'country' => $iso, 'priceType' => 'FIXED',
            'enabled' => true, 'subType' => 'Shopping',
            'price' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fixed' => [2500, 5000], 'min' => 2500, 'max' => 5000],
            'cost' => ['fixed' => 4500], 'requiredFields' => [['key' => 'email', 'label' => 'Email']],
        ];
    }

    public function test_reloadly_service_normalizes_products(): void
    {
        $this->fakeReloadly([$this->reloadlyProduct('Amazon', 'US', 1)]);

        $cat = app(ReloadlyGiftCardService::class)->getCatalogue();

        $this->assertCount(1, $cat);
        $this->assertSame('reloadly', $cat[0]['provider']);
        $this->assertSame('amazon', $cat[0]['brand_key']);
        $this->assertSame('FIXED', $cat[0]['denomination_type']);
        $this->assertSame([25, 50, 100], $cat[0]['fixed_denominations']);
    }

    public function test_zendit_voucher_service_normalizes_and_scales(): void
    {
        Http::fake(['test-api.zendit.io/*' => Http::response(['list' => [$this->zenditOffer('Steam', 'US', 'z1')]])]);

        $cat = app(ZenditVoucherService::class)->getCatalogue();

        $this->assertSame('zendit', $cat[0]['provider']);
        $this->assertSame('steam', $cat[0]['brand_key']);
        $this->assertSame([25.0, 50.0], $cat[0]['fixed_denominations']); // scaled by divisor 100
    }

    public function test_zendit_backfills_a_missing_logo_from_the_brands_endpoint(): void
    {
        $offer = $this->zenditOffer('Steam', 'US', 'z1');
        $offer['brand'] = 'steam-brand-id';

        Http::fake([
            'test-api.zendit.io/*/vouchers/offers*' => Http::response(['list' => [$offer]]),
            'test-api.zendit.io/*/brands/steam-brand-id' => Http::response(['logoUrl' => 'https://cdn.example/steam.png']),
        ]);

        $cat = app(ZenditVoucherService::class)->getCatalogue();

        $this->assertSame('https://cdn.example/steam.png', $cat[0]['logo_url']);
        $this->assertArrayNotHasKey('_brand_lookup_key', $cat[0]);
    }

    public function test_zendit_logo_backfill_never_fails_the_sync_on_a_bad_lookup(): void
    {
        $offer = $this->zenditOffer('Steam', 'US', 'z1');
        $offer['brand'] = 'steam-brand-id';

        Http::fake([
            'test-api.zendit.io/*/vouchers/offers*' => Http::response(['list' => [$offer]]),
            'test-api.zendit.io/*/brands/steam-brand-id' => Http::response(['error' => 'not found'], 404),
        ]);

        $cat = app(ZenditVoucherService::class)->getCatalogue();

        $this->assertCount(1, $cat);
        $this->assertNull($cat[0]['logo_url']);
    }

    public function test_reloadly_wins_where_both_carry_a_brand_zendit_fills_gaps(): void
    {
        $this->fakeReloadly([$this->reloadlyProduct('Amazon', 'US', 1)]);
        $sync = app(GiftCardCatalogueSyncService::class);
        $sync->sync('reloadly');

        Http::fake(['test-api.zendit.io/*' => Http::response(['list' => [
            $this->zenditOffer('Amazon', 'US', 'z-amazon'),  // overlaps Reloadly
            $this->zenditOffer('Jumia', 'NG', 'z-jumia'),    // Reloadly doesn't have it
        ]])]);
        $sync->sync('zendit');

        // Reloadly Amazon is primary; Zendit Amazon is not.
        $this->assertTrue(GiftCardProduct::where('provider', 'reloadly')->where('brand_key', 'amazon')->first()->is_primary);
        $this->assertFalse(GiftCardProduct::where('provider', 'zendit')->where('brand_key', 'amazon')->first()->is_primary);
        // Zendit-only Jumia is primary.
        $this->assertTrue(GiftCardProduct::where('provider', 'zendit')->where('brand_key', 'jumia')->first()->is_primary);
        // Storefront scope only returns primaries.
        $this->assertSame(2, GiftCardProduct::storefront()->count());
    }

    public function test_provider_and_cost_are_never_exposed(): void
    {
        $this->fakeReloadly([$this->reloadlyProduct('Amazon', 'US', 1)]);
        app(GiftCardCatalogueSyncService::class)->sync('reloadly');

        $array = GiftCardProduct::first()->toArray();
        $this->assertArrayNotHasKey('provider', $array);
        $this->assertArrayNotHasKey('provider_product_id', $array);
        $this->assertArrayNotHasKey('cost_meta', $array);
        $this->assertArrayHasKey('brand_name', $array);
    }

    public function test_unconfigured_provider_records_a_failed_sync(): void
    {
        config(['services.reloadly.client_id' => null, 'services.reloadly.client_secret' => null]);

        $this->assertSame(0, app(GiftCardCatalogueSyncService::class)->sync('reloadly'));
        $this->assertFalse(SyncStatus::for('giftcards:reloadly')['ok']);
    }

    public function test_a_non_usd_card_is_priced_from_the_sender_map_not_the_face(): void
    {
        // A ₦5,000 face that really costs us ~$4 must NOT be priced as $5,000.
        $this->fakeReloadly([[
            'productId' => 9, 'productName' => 'Naija Store', 'denominationType' => 'FIXED',
            'recipientCurrencyCode' => 'NGN', 'senderCurrencyCode' => 'USD',
            'fixedRecipientDenominations' => [5000], 'fixedRecipientToSenderDenominationsMap' => ['5000' => 4.00],
            'senderFee' => 0.5, 'discountPercentage' => 0, 'brand' => ['brandName' => 'Naija Store'],
            'country' => ['isoName' => 'NG'],
        ]]);
        app(GiftCardCatalogueSyncService::class)->sync('reloadly');

        $p = GiftCardProduct::where('brand_key', 'naija-store')->first();
        $this->assertTrue($p->priceable);
        $retail = app(GiftCardPricing::class)->retail($p, 5000.0);

        // Cost basis is ~$4.50 (sender 4.00 + $0.50 fee), so retail is a few
        // dollars — nowhere near the ₦5,000 face number.
        $this->assertGreaterThan(4.5, $retail);
        $this->assertLessThan(20, $retail);
    }

    public function test_a_card_we_cannot_price_is_withheld_from_the_storefront(): void
    {
        // Non-USD, FIXED, but NO sender map → unpriceable → must not be sellable.
        $this->fakeReloadly([[
            'productId' => 10, 'productName' => 'Mystery', 'denominationType' => 'FIXED',
            'recipientCurrencyCode' => 'KWD', 'senderCurrencyCode' => 'USD',
            'fixedRecipientDenominations' => [10], 'brand' => ['brandName' => 'Mystery'],
            'country' => ['isoName' => 'KW'],
        ]]);
        app(GiftCardCatalogueSyncService::class)->sync('reloadly');

        $p = GiftCardProduct::where('brand_key', 'mystery')->first();
        $this->assertFalse($p->priceable);
        $this->assertSame(0, GiftCardProduct::storefront()->where('id', $p->id)->count());
    }

    public function test_reloadly_preflight_reports_a_working_key(): void
    {
        Http::fake([
            'auth.reloadly.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'giftcards-sandbox.reloadly.com/accounts/balance' => Http::response(['balance' => 500, 'currencyCode' => 'USD']),
            'giftcards-sandbox.reloadly.com/products*' => Http::response(['content' => [], 'totalElements' => 4200, 'last' => true]),
        ]);

        $r = app(ReloadlyGiftCardService::class)->preflight();

        $this->assertTrue($r['ok']);
        $this->assertSame(500.0, $r['balance']);
        $this->assertSame('USD', $r['currency']);
        $this->assertSame(4200, $r['products']);
    }

    public function test_reloadly_preflight_surfaces_a_bad_key_without_throwing(): void
    {
        Http::fake([
            'auth.reloadly.com/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $r = app(ReloadlyGiftCardService::class)->preflight();

        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['error']);
    }

    public function test_preflight_tells_the_admin_to_add_keys_when_missing(): void
    {
        config(['services.reloadly.client_id' => null, 'services.reloadly.client_secret' => null]);

        $r = app(ReloadlyGiftCardService::class)->preflight();

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Admin', $r['error']);
    }
}
