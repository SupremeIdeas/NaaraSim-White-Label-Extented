<?php

namespace Tests\Feature;

use App\Exceptions\EsimProviderException;
use App\Models\EsimPlan;
use App\Services\eSIM\CatalogueSyncService;
use App\Services\eSIM\GigsService;
use App\Services\eSIM\ZenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Request/response shapes taken from the providers' own published API docs (Zendit OpenAPI 1.0.13, Gigs developer docs, checked 2026-10-18). */
class EsimProviderDocsConformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.zendit.api_key' => 'zk', 'services.zendit.base_url' => 'https://api.zendit.io/v1', 'services.zendit.poll_wait_ms' => 0, 'services.zendit.poll_tries' => 3,
            'services.gigs.api_key' => 'gk', 'services.gigs.project' => 'prj', 'services.gigs.base_url' => 'https://api.gigs.com', 'services.gigs.poll_wait_ms' => 0, 'services.gigs.poll_tries' => 3]);
    }

    public function test_zendit_polls_the_async_purchase_until_done_and_flattens_the_confirmation(): void
    {
        Http::fake([
            'api.zendit.io/v1/esim/purchases' => Http::response([], 200),
            'api.zendit.io/v1/esim/purchases/*' => Http::sequence()
                ->push(['status' => 'PENDING'])
                ->push(['status' => 'DONE', 'confirmation' => ['iccid' => '8944', 'activationCode' => 'LPA:1$x', 'smdpAddress' => 'smdp.example']]),
        ]);
        $out = app(ZenditService::class)->orderBundle('ESIM-FR-7D-1GB');
        $this->assertSame(['8944', 'LPA:1$x', 'smdp.example', false], [$out['iccid'], $out['activationCode'], $out['smdpAddress'], $out['pending']]);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/esim/purchases') && $r['offerId'] === 'ESIM-FR-7D-1GB' && str_starts_with($r['transactionId'], 'naara-') && ! isset($r['iccid']));
    }

    public function test_zendit_failed_purchase_throws_so_the_money_layer_can_refund_and_fail_over(): void
    {
        Http::fake(['api.zendit.io/v1/esim/purchases' => Http::response([], 200), 'api.zendit.io/v1/esim/purchases/*' => Http::response(['status' => 'FAILED', 'error' => ['message' => 'out of stock']])]);
        $this->expectException(EsimProviderException::class);
        app(ZenditService::class)->orderBundle('X');
    }

    public function test_zendit_purchase_still_in_flight_is_returned_pending_not_failed_over(): void
    {
        Http::fake(['api.zendit.io/v1/esim/purchases' => Http::response([], 200), 'api.zendit.io/v1/esim/purchases/*' => Http::response(['status' => 'IN_PROGRESS'])]);
        $out = app(ZenditService::class)->orderBundle('X');
        $this->assertTrue($out['pending']);
        $this->assertStringStartsWith('naara-', $out['transactionId']);
    }

    public function test_gigs_lists_plans_by_cursor_with_the_documented_base_and_filters(): void
    {
        Http::fake(function ($r) {
            $after = $r->data()['after'] ?? null;

            return Http::response($after === null
                ? ['object' => 'list', 'items' => [['id' => 'pln_1']], 'moreItemsAfter' => 'pln_1']
                : ['object' => 'list', 'items' => [['id' => 'pln_2']], 'moreItemsAfter' => null]);
        });
        $cat = app(GigsService::class)->getCatalogue();
        $this->assertSame(['pln_1', 'pln_2'], array_column($cat['items'], 'id'));
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.gigs.com/projects/prj/plans?') && $r['simType'] === 'eSIM' && (int) $r['limit'] === 200 && $r->hasHeader('Authorization', 'Bearer gk'));
    }

    public function test_gigs_plans_map_voice_only_when_the_plan_really_has_it_and_skip_what_cannot_be_auto_ordered(): void
    {
        $plan = fn (string $id, array $over = []) => array_replace_recursive(['id' => $id, 'name' => $id, 'status' => 'available', 'simTypes' => ['eSIM'], 'allowances' => ['dataBytes' => 10 * 1073741824, 'voiceSeconds' => 0, 'smsMessages' => 0],
            'price' => ['amount' => 1500, 'currency' => 'USD'], 'coverage' => ['countries' => ['DE', 'FR']], 'requirements' => ['address' => 'none'], 'validity' => ['days' => 30]], $over);
        Http::fake(['api.gigs.com/projects/prj/plans*' => Http::response(['object' => 'list', 'moreItemsAfter' => null, 'items' => [
            $plan('data_only'),
            $plan('with_calls', ['allowances' => ['voiceSeconds' => 30000, 'smsMessages' => 100]]),
            $plan('needs_address', ['requirements' => ['address' => 'present']]),
            $plan('euro_priced', ['price' => ['currency' => 'EUR']]),
            $plan('archived', ['status' => 'archived']),
        ]])]);
        app(CatalogueSyncService::class)->sync('gigs');
        $this->assertEqualsCanonicalizing(['data_only', 'with_calls'], EsimPlan::where('provider', 'gigs')->pluck('provider_plan_id')->all());
        $this->assertFalse((bool) EsimPlan::where('provider_plan_id', 'data_only')->value('has_voice'));
        $calls = EsimPlan::where('provider_plan_id', 'with_calls')->first();
        $this->assertTrue((bool) $calls->has_voice);
        $this->assertSame([10240, 30, 15.0], [(int) $calls->data_mb, (int) $calls->validity_days, (float) $calls->cost_price_usd]);
    }

    public function test_gigs_order_creates_a_user_then_a_subscription_then_reads_the_install_credentials(): void
    {
        Http::fake([
            'api.gigs.com/projects/prj/users' => Http::response(['object' => 'user', 'id' => 'usr_1']),
            'api.gigs.com/projects/prj/subscriptions' => Http::response(['object' => 'subscription', 'id' => 'sub_1', 'status' => 'pending', 'sim' => null], 201),
            'api.gigs.com/projects/prj/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'status' => 'active', 'phoneNumber' => '+4915000000', 'sim' => ['id' => 'sim_1', 'iccid' => '8949']]),
            'api.gigs.com/projects/prj/sims/sim_1/credentials' => Http::response(['object' => 'simCredentials', 'activationCode' => 'LPA:1$g', 'qrCodeUrl' => 'https://qr.example/x.png']),
        ]);
        $out = app(GigsService::class)->orderBundle('pln_1');
        $this->assertSame(['sub_1', '8949', 'LPA:1$g', '+4915000000', false], [$out['subscriptionId'], $out['iccid'], $out['activationCode'], $out['phoneNumber'], $out['pending']]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/subscriptions') && $r['user'] === 'usr_1' && $r['plan'] === 'pln_1' && $r['sim'] === 'auto');
    }

    public function test_oneglobal_uses_the_documented_keycloak_token_and_v1_products_orders_paths(): void
    {
        config(['services.oneglobal.client_id' => 'cid', 'services.oneglobal.client_secret' => 'sec', 'services.oneglobal.base_url' => 'https://services.truphone.com/connect-api',
            'services.oneglobal.token_url' => 'https://services.truphone.com/auth/realms/tru-staff/protocol/openid-connect/token']);
        \Illuminate\Support\Facades\Cache::forget('oneglobal:access_token');
        Http::fake([
            'services.truphone.com/auth/*' => Http::response(['access_token' => 'tok', 'expires_in' => 300]),
            'services.truphone.com/connect-api/v1/products' => Http::response([['id' => 'prod_1', 'name' => 'Europe 5GB', 'period' => 30, 'period_unit' => 'DAYS', 'data' => ['value' => 5, 'units' => 'GB'], 'activeCountries' => ['DE', 'FR'], 'pricing' => ['amount' => 12.5], 'region' => 'Europe']]),
            'services.truphone.com/connect-api/v1/orders' => Http::response(['id' => 'ord_1', 'status' => 'COMPLETED', 'iccid' => '8944', 'lpa_url' => 'LPA:1$o', 'qrcode_url' => 'https://q/x.png']),
        ]);
        app(CatalogueSyncService::class)->sync('oneglobal');
        $p = EsimPlan::where('provider', 'oneglobal')->firstOrFail();
        $this->assertSame([5120, 30, 12.5, false], [(int) $p->data_mb, (int) $p->validity_days, (float) $p->cost_price_usd, (bool) $p->has_voice], 'documented products carry no voice, so they are data plans');
        $out = app(\App\Services\eSIM\OneGlobalService::class)->orderBundle('prod_1');
        $this->assertSame(['ord_1', '8944', 'LPA:1$o', false], [$out['id'], $out['iccid'], $out['activationCode'], $out['pending']]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/orders') && $r['input']['operationType'] === 'NEW_ESIM' && $r['input']['subscriptions'][0]['product_id'] === 'prod_1' && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_monty_esim_stays_off_until_the_partner_base_url_is_set(): void
    {
        config(['services.gigs.api_key' => null, 'services.montymobile.api_key' => 'k', 'services.montymobile.base_url' => null]);
        $this->assertFalse(\App\Support\ProviderStatus::isActive('montymobile'));
        $this->assertFalse(\App\Support\NaaraConnect::available());
        config(['services.montymobile.base_url' => 'https://partner.example']);
        $this->assertTrue(\App\Support\NaaraConnect::available());
    }
}
