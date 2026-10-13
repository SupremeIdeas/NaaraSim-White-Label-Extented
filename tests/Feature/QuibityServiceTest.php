<?php

namespace Tests\Feature;

use App\Exceptions\EsimProviderException;
use App\Models\EsimPlan;
use App\Services\eSIM\CatalogueSyncService;
use App\Services\eSIM\QuibityService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Quibity / eSIM.sm Partner API in the shapes its public docs (docs.esim.sm) and the live account return. */
class QuibityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        config(['services.quibity.api_key' => 'k', 'services.quibity.base_url' => 'https://esim.sm/api/reseller/v1', 'services.quibity.sandbox' => false]);
    }

    public function test_the_catalogue_is_every_countrys_plans_deduplicated_by_plan_id(): void
    {
        Http::fake(function ($request) {
            $id = [] ;
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $id);
            if (! isset($id['id'])) {
                return Http::response(['success' => true, 'data' => [['id' => 'NG'], ['id' => 'GH']]]);
            }
            $regional = ['id' => 900, 'name' => '5GB 15 days - Africa', 'mb' => 5120, 'days' => 15, 'price' => 20.0, 'salePrice' => 14.0, 'countries' => ['NG', 'GH'], 'isActive' => true, 'isUnlimited' => false, 'hasPhoneNumber' => 0];
            $local = ['id' => $id['id'] === 'NG' ? 1 : 2, 'name' => '1GB 7 days - '.$id['id'], 'mb' => 1024, 'days' => 7, 'price' => 8.4, 'salePrice' => 6.0, 'countries' => [$id['id']], 'isActive' => true, 'isUnlimited' => false, 'hasPhoneNumber' => 0];

            return Http::response(['success' => true, 'data' => ['country' => ['id' => $id['id']], 'plans' => [$local, $regional]]]);
        });

        $cat = app(QuibityService::class)->getCatalogue();

        $this->assertSame([1, 900, 2], array_column($cat['plans'], 'id'), 'the regional plan that both countries list appears once');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer k') && ! $r->hasHeader('x-sandbox'));
    }

    public function test_the_sync_reads_the_real_field_names_uses_the_higher_price_as_cost_and_skips_number_bundles(): void
    {
        config(['services.quibity.api_key' => 'k']);
        $this->app->instance('esim.quibity', new class extends QuibityService
        {
            public function getCatalogue(): array
            {
                return ['plans' => [
                    ['id' => 1044, 'name' => '1GB 7 days - Nigeria', 'mb' => 1024, 'days' => 7, 'price' => 8.4, 'salePrice' => 6.0, 'countries' => ['NG'], 'isActive' => true, 'isUnlimited' => false, 'hasPhoneNumber' => 0],
                    ['id' => 2000, 'name' => 'Nigeria Unlimited - 3 days', 'mb' => 20480, 'days' => 3, 'price' => 15.0, 'salePrice' => 10.5, 'countries' => ['NG'], 'isActive' => true, 'isUnlimited' => true, 'hasPhoneNumber' => 0],
                    ['id' => 3000, 'name' => 'Nigeria with number', 'mb' => 1024, 'days' => 7, 'price' => 20.0, 'salePrice' => 15.0, 'countries' => ['NG'], 'isActive' => true, 'isUnlimited' => false, 'hasPhoneNumber' => 1],
                    ['id' => 4000, 'name' => 'Retired plan', 'mb' => 1024, 'days' => 7, 'price' => 5.0, 'salePrice' => 4.0, 'countries' => ['NG'], 'isActive' => false, 'isUnlimited' => false, 'hasPhoneNumber' => 0],
                ]];
            }
        });

        app(CatalogueSyncService::class)->sync('quibity');

        $this->assertSame(['1044', '2000'], EsimPlan::where('provider', 'quibity')->orderBy('provider_plan_id')->pluck('provider_plan_id')->all());
        $p = EsimPlan::where('provider_plan_id', '1044')->firstOrFail();
        $this->assertSame(1024, (int) $p->data_mb);
        $this->assertSame(7, (int) $p->validity_days);
        $this->assertEquals(8.4, (float) $p->cost_price_usd, 'never below what the plan can really cost');
        $this->assertNull(EsimPlan::where('provider_plan_id', '2000')->firstOrFail()->data_mb, 'unlimited is null across the platform');
    }

    public function test_a_purchase_returns_the_esim_and_the_lpa_string(): void
    {
        Http::fake(['esim.sm/api/reseller/v1/esim/purchase' => Http::response(['success' => true, 'pending' => false, 'data' => ['price' => 6.0, 'currency' => 'USD', 'esim' => [['iccid' => '8944000000000000001', 'lpaCode' => 'LPA:1$esimsm.TEST$TN1']]]])]);

        $out = app(QuibityService::class)->orderBundle('1044', 1);

        $this->assertSame('8944000000000000001', $out['iccid']);
        $this->assertSame('LPA:1$esimsm.TEST$TN1', \App\Support\Niche\LpaActivation::fromPayload($out));
        $this->assertSame(6.0, (float) $out['charged_usd']);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['plan_id'] === 1044 && $r['quantity'] === 1);
    }

    public function test_not_enough_credit_and_pending_orders_throw_so_the_router_fails_over(): void
    {
        Http::fake(['esim.sm/api/reseller/v1/esim/purchase' => Http::response(['success' => false, 'error' => 'Not enough credit.', 'data' => ['price' => 6.0]], 400)]);
        try {
            app(QuibityService::class)->orderBundle('1044');
            $this->fail('A refused order was accepted');
        } catch (EsimProviderException) {
            $this->assertTrue(true);
        }
    }

    public function test_a_pending_order_is_not_delivered_to_a_waiting_buyer(): void
    {
        Http::fake(['esim.sm/api/reseller/v1/esim/purchase' => Http::response(['success' => true, 'pending' => true, 'message' => 'Processing'])]);
        $this->expectException(EsimProviderException::class);
        $this->expectExceptionMessage('pending');
        app(QuibityService::class)->orderBundle('1044');
    }

    public function test_info_and_refund_use_the_documented_paths(): void
    {
        Http::fake([
            'esim.sm/api/reseller/v1/esim?iccid=*' => Http::response(['success' => true, 'data' => ['iccid' => '89', 'mbTotal' => 1024, 'mbUsed' => 12, 'status' => 'installed']]),
            'esim.sm/api/reseller/v1/esim/refund' => Http::response(['success' => true, 'error' => 'Refund request has been sent.']),
        ]);

        $this->assertSame(12, app(QuibityService::class)->getUsage('89', 'x')['mbUsed']);
        $this->assertTrue(app(QuibityService::class)->revoke('89', 'x')['success']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/esim/refund') && $r['iccid'] === '89');
    }
}
