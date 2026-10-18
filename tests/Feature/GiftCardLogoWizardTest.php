<?php

namespace Tests\Feature;

use App\Models\GiftCardBrandLogo;
use App\Models\GiftCardProduct;
use App\Services\GiftCards\BitrefillService;
use App\Services\GiftCards\GiftCardCatalogueSyncService;
use App\Services\GiftCards\GiftCardLogoWizard;
use App\Services\GiftCards\ZenditVoucherService;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Logo Wizard: real logo stored locally per brand, honest fallback, no hot-links, and the live Zendit / Bitrefill field shapes. */
class GiftCardLogoWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaStorage::disk());
    }

    private function png(int $w = 120, int $h = 80): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function product(string $key, string $name, ?string $logo): GiftCardProduct
    {
        return GiftCardProduct::create(['provider' => 'zendit', 'provider_product_id' => $key.'-1', 'brand_key' => $key, 'brand_name' => $name, 'denomination_type' => 'FIXED', 'fixed_denominations' => [10], 'logo_url' => $logo, 'provider_enabled' => true, 'priceable' => true]);
    }

    public function test_a_provider_logo_is_downloaded_normalised_to_a_square_webp_and_pointed_at_locally(): void
    {
        Http::fake(['cdn.example/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $p = $this->product('acme', 'Acme', 'https://cdn.example/acme.png');

        $stats = app(GiftCardLogoWizard::class)->run();

        $this->assertSame(1, $stats['approved']);
        $row = GiftCardBrandLogo::where('brand_key', 'acme')->firstOrFail();
        $this->assertSame(['provider', 'approved'], [$row->source, $row->status]);
        Storage::disk(MediaStorage::disk())->assertExists($row->path);
        $info = getimagesizefromstring(Storage::disk(MediaStorage::disk())->get($row->path));
        $this->assertSame([256, 256, IMAGETYPE_WEBP], [$info[0], $info[1], $info[2]]);
        $this->assertStringNotContainsString('cdn.example', (string) $p->fresh()->logo_url, 'the product no longer hot-links the provider');
        $this->assertSame(64, strlen($row->sha256));
    }

    public function test_a_broken_provider_logo_falls_to_simple_icons_then_to_a_flagged_monogram(): void
    {
        Http::fake([
            'cdn.example/*' => Http::response('not an image', 200),
            'cdn.simpleicons.org/apple*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>', 200),
            'cdn.simpleicons.org/*' => Http::response('Not found', 404),
        ]);
        $this->product('apple', 'Apple', 'https://cdn.example/a.png');
        $this->product('zzq-store', 'ZZQ Store', 'https://cdn.example/b.png');

        $stats = app(GiftCardLogoWizard::class)->run();

        $this->assertSame(['approved' => 1, 'fallback' => 1], ['approved' => $stats['approved'], 'fallback' => $stats['fallback']]);
        $this->assertSame('simpleicons', GiftCardBrandLogo::where('brand_key', 'apple')->value('source'));
        $m = GiftCardBrandLogo::where('brand_key', 'zzq-store')->firstOrFail();
        $this->assertSame(['monogram', 'fallback'], [$m->source, $m->status]);
        $this->assertStringContainsString('>ZS<', Storage::disk(MediaStorage::disk())->get($m->path));
    }

    public function test_an_admin_entered_domain_is_fetched_from_the_official_site_and_trusted(): void
    {
        Http::fake([
            'brand.example/' => Http::response('<html><head><link rel="apple-touch-icon" href="/icon.png"></head></html>', 200),
            'brand.example/icon.png' => Http::response($this->png(180, 180), 200),
            '*' => Http::response('x', 404),
        ]);
        $row = app(GiftCardLogoWizard::class)->resolve('nobrand', 'No Brand', null, 'brand.example');
        $this->assertSame(['site', 'approved'], [$row->source, $row->status]);
    }

    public function test_it_will_not_fetch_from_a_private_address(): void
    {
        Http::fake();
        $row = app(GiftCardLogoWizard::class)->resolve('evil', 'Evil', null, '127.0.0.1');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '127.0.0.1'));
        $this->assertSame('fallback', $row->status);
    }

    public function test_a_rerun_keeps_approved_logos_and_a_sync_keeps_the_local_logo(): void
    {
        Http::fake(['cdn.example/*' => Http::response($this->png(), 200)]);
        $this->product('acme', 'Acme', 'https://cdn.example/acme.png');
        $wizard = app(GiftCardLogoWizard::class);
        $wizard->run();
        $this->assertSame(1, $wizard->run()['skipped']);

        $this->app->instance('giftcard.zendit', new class extends ZenditVoucherService
        {
            public function available(): bool { return true; }
            public function getCatalogue(): array { return [['provider' => 'zendit', 'provider_product_id' => 'acme-1', 'brand_key' => 'acme', 'brand_name' => 'Acme', 'logo_url' => 'https://cdn.example/acme.png', 'denomination_type' => 'FIXED', 'fixed_denominations' => [10], 'provider_enabled' => true, 'priceable' => true]]; }
        });
        app(GiftCardCatalogueSyncService::class)->sync('zendit');
        $this->assertStringNotContainsString('cdn.example', (string) GiftCardProduct::where('provider_product_id', 'acme-1')->value('logo_url'));
    }

    public function test_zendit_reads_the_live_brand_logo_field_in_one_pooled_lookup_per_brand(): void
    {
        config(['services.zendit.api_key' => 'k', 'services.zendit.base_url' => 'https://api.zendit.io/v1']);
        Http::fake([
            'api.zendit.io/v1/vouchers/offers*' => Http::response(['list' => [
                ['offerId' => 'A_1', 'brand' => 'ACME', 'brandName' => 'Acme', 'country' => 'US', 'priceType' => 'FIXED', 'subTypes' => ['Retail'], 'price' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fixed' => 1000]],
                ['offerId' => 'A_2', 'brand' => 'ACME', 'brandName' => 'Acme', 'country' => 'US', 'priceType' => 'FIXED', 'price' => ['currency' => 'USD', 'currencyDivisor' => 100, 'fixed' => 2500]],
            ]]),
            'api.zendit.io/v1/brands/ACME' => Http::response(['brand' => 'ACME', 'brandLogo' => 'https://img.example/acme.jpg', 'brandColor' => '#112233']),
        ]);
        $cat = app(ZenditVoucherService::class)->getCatalogue();
        $this->assertSame(['https://img.example/acme.jpg', '#112233', 'Retail'], [$cat[0]['logo_url'], $cat[0]['brand_color'], $cat[0]['category']]);
        $this->assertSame('https://img.example/acme.jpg', $cat[1]['logo_url']);
        Http::assertSentCount(2);
    }

    public function test_bitrefill_pages_by_start_and_maps_the_live_product_shape(): void
    {
        config(['services.bitrefill.api_key' => 'br']);
        $mk = fn ($id) => ['id' => $id, 'name' => ucfirst($id), 'country_code' => 'US', 'currency' => 'USD', 'categories' => ['food'], 'image' => $id, 'logo_background' => '#000000', 'in_stock' => true, 'range' => ['min' => 5, 'max' => 500, 'step' => 0.1], 'packages' => [['id' => $id.'<&>5', 'value' => '5']]];
        Http::fake(function ($r) use ($mk) {
            $start = (int) ($r->data()['start'] ?? 0);

            return Http::response(['meta' => ['total' => 3], 'data' => $start === 0 ? [$mk('alpha'), $mk('beta')] : [$mk('gamma')]]);
        });
        $cat = app(BitrefillService::class)->getCatalogue();
        $this->assertCount(3, $cat);
        $this->assertSame(['US', 'food', 'https://www.bitrefill.com/content/cn/alpha.png', '#000000', 'RANGE'], [$cat[0]['country'], $cat[0]['category'], $cat[0]['logo_url'], $cat[0]['brand_color'], $cat[0]['denomination_type']]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'start=2') && $r->hasHeader('Authorization', 'Bearer br'));
    }

    public function test_a_country_variant_inherits_the_brand_logo_instead_of_a_monogram(): void
    {
        Http::fake(['cdn.example/*' => Http::response($this->png(), 200), '*' => Http::response('x', 404)]);
        $this->product('birkenstock', 'Birkenstock', 'https://cdn.example/b.png');
        $this->product('birkenstock-qatar', 'Birkenstock Qatar', 'https://nowhere.example/missing.png');
        app(GiftCardLogoWizard::class)->run();
        $q = GiftCardBrandLogo::where('brand_key', 'birkenstock-qatar')->firstOrFail();
        $this->assertSame(['sibling', 'approved'], [$q->source, $q->status]);
    }

    public function test_per_denomination_offers_show_as_one_card_with_merged_denominations_and_a_fixed_offer_sends_no_value(): void
    {
        foreach ([10, 25, 50] as $i => $face) {
            GiftCardProduct::create(['provider' => 'zendit', 'provider_product_id' => 'ACME_US_'.$face, 'brand_key' => 'acme', 'brand_name' => 'Acme', 'country' => 'US', 'currency' => 'USD', 'denomination_type' => 'FIXED', 'fixed_denominations' => [$face], 'provider_enabled' => true, 'priceable' => true, 'is_primary' => true, 'admin_enabled' => true]);
        }
        $this->assertSame(1, GiftCardProduct::storefrontCards()->count());
        $card = GiftCardProduct::storefrontCards()->first();
        $d = app(\App\Support\GiftCardPricing::class)->denominations($card);
        $this->assertSame([10.0, 25.0, 50.0], array_column($d['options'], 'face'));

        config(['services.zendit.api_key' => 'k', 'services.zendit.base_url' => 'https://api.zendit.io/v1']);
        Http::fake(['api.zendit.io/*' => Http::response(['status' => 'DONE', 'receipt' => []])]);
        app(ZenditVoucherService::class)->order('ACME_US_25', 25.0, 'USD', [], 'tx-1');
        Http::assertSent(fn ($r) => $r['offerId'] === 'ACME_US_25' && ! isset($r['value']));
    }

    public function test_the_shipped_logo_pack_seeds_every_brand_without_network_and_survives_a_sync(): void
    {
        Http::fake();
        $manifest = json_decode((string) file_get_contents(database_path('seeders/giftcard-logos.json')), true);
        $this->assertGreaterThan(7000, count($manifest));
        foreach (array_slice($manifest, 0, 25) as $e) {
            $this->assertFileExists(public_path(GiftCardLogoWizard::PACK_DIR.$e['file']));
        }
        $first = $manifest[0];
        $this->product($first['brand_key'], $first['brand_name'], 'https://hotlink.example/x.png');
        $this->assertGreaterThan(7000, app(GiftCardLogoWizard::class)->seedFromPack());
        app(GiftCardLogoWizard::class)->applyToProducts();
        $url = (string) GiftCardProduct::where('brand_key', $first['brand_key'])->value('logo_url');
        $this->assertStringContainsString('images/giftcards/logos/', $url);
        Http::assertNothingSent();
    }
}
