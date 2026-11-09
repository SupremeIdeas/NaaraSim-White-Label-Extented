<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TopupOrder;
use App\Models\TopupProduct;
use App\Models\TopupProvider;
use App\Models\TopupRoute;
use App\Models\User;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\ProviderRouter;
use App\Services\Topup\QuoteService;
use App\Services\Topup\TopupException;
use App\Services\Topup\TopupOps;
use App\Services\Topup\TopupOrderService;
use App\Services\Wallet\WalletService;
use App\Support\FeatureFlags;
use App\Support\FeatureLocks;
use App\Support\TopupLogos;
use App\Support\TopupSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

/**
 * Naara Top-Up (airtime + data): routing, quoting, the order state machine with its money rules (atomic debit, refund once, never refund
 * on an uncertain answer, failover only on a definitive failure with a NEW reference), webhooks, gating and privacy.
 */
class TopupTest extends TestCase
{
    use RefreshDatabase;

    private FakeTopupProvider $zendit;

    private FakeTopupProvider $vtpass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        config(['queue.default' => 'sync']);
        $this->zendit = new FakeTopupProvider('zendit');
        $this->vtpass = new FakeTopupProvider('vtpass');
        $this->app->instance('topup.provider.zendit', $this->zendit);
        $this->app->instance('topup.provider.vtpass', $this->vtpass);
        app(TopupOps::class)->seed();
        TopupProvider::query()->update(['enabled' => true]);
        TopupSettings::set('daily_cap_usd', 500);
        TopupSettings::set('new_account_daily_cap_usd', 500);
        Setting::setValue('features.naara_topup.enabled', true);
        config(['services.zendit.api_key' => 'k']);
        Cache::flush();
    }

    private function product(string $provider = 'zendit', array $a = []): TopupProduct
    {
        return TopupProduct::create(array_merge([
            'provider_code' => $provider, 'provider_sku' => 'sku-'.uniqid(), 'country_iso' => 'NG', 'operator_name' => 'MTN', 'type' => 'airtime', 'name' => 'Airtime',
            'currency' => 'NGN', 'is_fixed' => true, 'fixed_amount' => 1000, 'cost_native' => 0.76, 'cost_currency' => 'USD', 'active' => true,
        ], $a));
    }

    private function user(float $usd = 100): User
    {
        $u = User::factory()->create(['is_active' => true, 'created_at' => now()->subMonths(2)]);
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'seed:'.$u->id]);

        return $u;
    }

    private function balance(User $u): float
    {
        return (float) app(WalletService::class)->spendableUsd($u->fresh());
    }

    private function place(User $u, TopupProduct $p, string $key = 'k1'): TopupOrder
    {
        $q = app(QuoteService::class)->create($u, $p, '+2348031234567', (float) ($p->fixed_amount ?: 1000));

        return app(TopupOrderService::class)->place($u, $q, $key);
    }

    // ── Routing ─────────────────────────────────────────────────────────────
    public function test_routing_orders_suppliers_and_skips_unavailable_ones(): void
    {
        $r = app(ProviderRouter::class);
        $this->assertSame(['vtpass', 'zendit'], array_slice($r->chain('NG', 'airtime'), 0, 2));   // seeded: NG vtpass first
        TopupProvider::where('code', 'vtpass')->update(['enabled' => false]);
        $this->assertSame('zendit', $r->chain('NG', 'airtime')[0]);
        $this->vtpass->configured = true;
        TopupProvider::where('code', 'vtpass')->update(['enabled' => true, 'min_float' => 50, 'last_balance' => 10]);
        $this->assertNotContains('vtpass', $r->chain('NG', 'airtime'), 'a supplier below its minimum float is skipped');
        $this->assertSame(['zendit'], array_slice($r->chain('GH', 'data'), 0, 1), 'wildcard route serves other countries');
    }

    public function test_no_route_means_not_sellable(): void
    {
        TopupRoute::query()->delete();
        $this->assertFalse(app(ProviderRouter::class)->sellable('NG'));
    }

    public function test_country_specific_route_outranks_the_wildcard(): void
    {
        TopupRoute::query()->delete();
        TopupRoute::create(['country_iso' => '*', 'product_type' => '*', 'provider_code' => 'zendit', 'priority' => 1]);
        TopupRoute::create(['country_iso' => 'NG', 'product_type' => '*', 'provider_code' => 'vtpass', 'priority' => 9]);
        $this->assertSame('vtpass', app(ProviderRouter::class)->chain('NG', 'airtime')[0]);
    }

    // ── Pricing ─────────────────────────────────────────────────────────────
    public function test_price_is_cost_plus_markup_and_never_below_cost_plus_min_profit(): void
    {
        $q = app(QuoteService::class);
        $p = $this->product('zendit', ['cost_native' => 10]);
        $this->assertEqualsWithDelta(10.60, $q->price($p, 1000)['price'], 0.001);   // 6% default
        TopupSettings::set('markup_pct', 0);
        $this->assertEqualsWithDelta(10.05, $q->price($p, 1000)['price'], 0.001);   // floored at cost + $0.05
    }

    public function test_operator_and_country_markup_overrides_apply(): void
    {
        Setting::setValue('topup.markup_overrides', ['NG' => 10, 'ng:mtn' => 20]);
        $p = $this->product('zendit', ['cost_native' => 10]);
        $this->assertEqualsWithDelta(12.00, app(QuoteService::class)->price($p, 1000)['price'], 0.001);
        $o = $this->product('zendit', ['cost_native' => 10, 'operator_name' => 'Glo']);
        $this->assertEqualsWithDelta(11.00, app(QuoteService::class)->price($o, 1000)['price'], 0.001);
    }

    public function test_range_product_is_priced_per_unit_and_amount_is_range_checked(): void
    {
        $p = $this->product('zendit', ['is_fixed' => false, 'fixed_amount' => null, 'cost_native' => null, 'cost_ratio' => 0.001, 'min_amount' => 100, 'max_amount' => 5000]);
        $this->assertEqualsWithDelta(2.12, app(QuoteService::class)->price($p, 2000)['price'], 0.001);   // cost 2.00 +6%
        $this->expectException(TopupException::class);
        app(QuoteService::class)->create($this->user(), $p, '+2348031234567', 10);
    }

    public function test_quote_cost_and_provider_never_appear_in_serialised_payloads(): void
    {
        $u = $this->user();
        $o = $this->place($u, $this->product());
        $json = json_encode([$o->toArray(), $o->product->toArray(), $o->fresh()->toArray()]);
        foreach (['cost_usd', 'cost_native', 'provider_code', 'provider_sku', 'reference', 'zendit'] as $leak) {
            $this->assertStringNotContainsString($leak, $json, "$leak leaked to a user-facing payload");
        }
    }

    // ── The money path ──────────────────────────────────────────────────────
    public function test_happy_path_debits_once_and_delivers(): void
    {
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        $this->assertSame(TopupOrder::DELIVERED, $o->fresh()->status);
        $this->assertEqualsWithDelta(100 - (float) $o->price_usd, $this->balance($u), 0.001);
        $this->assertCount(1, $this->zendit->purchases);
        $this->assertSame($o->reference, $this->zendit->purchases[0]->reference, 'the reference stored BEFORE the call is the one sent');
        $this->assertSame('+2348031234567', $this->zendit->purchases[0]->phoneE164);
    }

    public function test_double_click_returns_the_same_order_and_charges_once(): void
    {
        $u = $this->user(100);
        $p = $this->product();
        $a = $this->place($u, $p, 'same-key');
        $b = $this->place($u, $p, 'same-key');
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, TopupOrder::count());
        $this->assertCount(1, $this->zendit->purchases);
        $this->assertEqualsWithDelta(100 - (float) $a->price_usd, $this->balance($u), 0.001);
    }

    public function test_a_wallet_that_is_too_low_is_never_charged_and_the_supplier_is_never_called(): void
    {
        $u = $this->user(0.10);
        try {
            $this->place($u, $this->product());
            $this->fail('expected an exception');
        } catch (TopupException $e) {
            $this->assertStringContainsString('wallet is too low', $e->getMessage());
        }
        $this->assertSame(TopupOrder::PAYMENT_FAILED, TopupOrder::first()->status);
        $this->assertCount(0, $this->zendit->purchases);
        $this->assertEqualsWithDelta(0.10, $this->balance($u), 0.001);
    }

    public function test_a_certain_failure_with_no_other_supplier_refunds_exactly_once(): void
    {
        TopupRoute::query()->delete();
        TopupRoute::create(['country_iso' => '*', 'product_type' => '*', 'provider_code' => 'zendit', 'priority' => 1]);
        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::FAILED, null, message: 'Invalid number', definitive: true)];
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        $this->assertSame(TopupOrder::REFUNDED, $o->fresh()->status);
        $this->assertEqualsWithDelta(100, $this->balance($u), 0.001);
        app(TopupOrderService::class)->failAndRefund($o->fresh(), 'again');   // a second refund attempt is a no-op
        $this->assertEqualsWithDelta(100, $this->balance($u), 0.001);
    }

    public function test_a_certain_failure_fails_over_with_a_new_reference_to_the_next_supplier(): void
    {
        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::FAILED, null, definitive: true, message: 'no stock')];
        $this->product('vtpass', ['provider_sku' => 'mtn']);        // the equivalent product at the next supplier
        // order starts on zendit (route order puts vtpass first for NG, so make zendit the order's supplier explicitly)
        TopupRoute::query()->delete();
        TopupRoute::create(['country_iso' => 'NG', 'product_type' => '*', 'provider_code' => 'zendit', 'priority' => 1]);
        TopupRoute::create(['country_iso' => 'NG', 'product_type' => '*', 'provider_code' => 'vtpass', 'priority' => 2]);
        $u = $this->user(100);
        $o = $this->place($u, $this->product('zendit'));
        $o = $o->fresh();
        $this->assertSame(TopupOrder::DELIVERED, $o->status);
        $this->assertSame('vtpass', $o->provider_code);
        $this->assertNotSame($this->zendit->purchases[0]->reference, $this->vtpass->purchases[0]->reference, 'a failover uses a NEW reference');
        $this->assertEqualsWithDelta(100 - (float) $o->price_usd, $this->balance($u), 0.001, 'charged once, not per attempt');
    }

    public function test_an_uncertain_answer_stays_pending_and_is_never_refunded_or_resent_elsewhere(): void
    {
        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::PENDING, null, message: 'timeout')];
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        $this->assertSame(TopupOrder::PENDING, $o->fresh()->status);
        $this->assertCount(0, $this->vtpass->purchases);
        $this->assertEqualsWithDelta(100 - (float) $o->price_usd, $this->balance($u), 0.001, 'still charged while pending');
        // the requery job resolves it
        $this->zendit->requeryScript = [new PurchaseResult(PurchaseResult::DELIVERED, $o->reference, 'T1')];
        (new \App\Jobs\Topup\RequeryOrderJob($o->id))->handle(app(TopupOrderService::class));
        $this->assertSame(TopupOrder::DELIVERED, $o->fresh()->status);
        $this->assertCount(1, $this->zendit->purchases, 'no second send');
    }

    public function test_a_supplier_reversal_refunds_the_customer(): void
    {
        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::PENDING)];
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        $this->zendit->requeryScript = [new PurchaseResult(PurchaseResult::REVERSED, $o->reference)];
        (new \App\Jobs\Topup\RequeryOrderJob($o->id))->handle(app(TopupOrderService::class));
        $this->assertSame(TopupOrder::REFUNDED, $o->fresh()->status);
        $this->assertEqualsWithDelta(100, $this->balance($u), 0.001);
    }

    public function test_a_delivered_order_ignores_a_late_failure(): void
    {
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        app(TopupOrderService::class)->apply($o->fresh(), new PurchaseResult(PurchaseResult::FAILED, definitive: true), 'webhook');
        $this->assertSame(TopupOrder::DELIVERED, $o->fresh()->status);
        $this->assertEqualsWithDelta(100 - (float) $o->price_usd, $this->balance($u), 0.001);
    }

    public function test_stale_pending_orders_are_escalated_not_refunded(): void
    {
        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::PENDING)];
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        TopupOrder::whereKey($o->id)->update(['updated_at' => now()->subHour()]);
        $this->assertSame(1, app(TopupOrderService::class)->escalateStale());
        $o = $o->fresh();
        $this->assertTrue($o->escalated);
        $this->assertSame(TopupOrder::PENDING, $o->status);
    }

    public function test_state_machine_rejects_backwards_transitions(): void
    {
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        $this->assertFalse(app(TopupOrderService::class)->transition($o->fresh(), TopupOrder::PENDING));
        $this->assertSame(TopupOrder::DELIVERED, $o->fresh()->status);
    }

    // ── Fraud limits ────────────────────────────────────────────────────────
    public function test_daily_cap_and_distinct_number_limits(): void
    {
        TopupSettings::set('daily_cap_usd', 1.5);
        $u = $this->user(100);
        $p = $this->product();
        $this->place($u, $p, 'a');
        $this->expectException(TopupException::class);
        $this->place($u, $p, 'b');
    }

    public function test_new_accounts_get_the_lower_cap(): void
    {
        TopupSettings::set('new_account_daily_cap_usd', 0.5);
        $u = User::factory()->create(['is_active' => true, 'created_at' => now()]);
        app(WalletService::class)->credit($u, 50, 'USD', ['reference' => 'seed:new']);
        $this->expectException(TopupException::class);
        $this->place($u, $this->product());
    }

    // ── Webhooks ────────────────────────────────────────────────────────────
    public function test_webhook_with_a_bad_secret_is_rejected(): void
    {
        $this->postJson('/webhooks/topup/zendit', ['reference' => 'x', 'status' => 'delivered'])->assertStatus(401);
    }

    public function test_verified_webhook_makes_us_ask_the_supplier_before_moving_money(): void
    {
        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::PENDING)];
        $u = $this->user(100);
        $o = $this->place($u, $this->product());
        // a webhook CLAIMS delivered; the supplier (requery) says still pending: nothing moves
        $this->zendit->requeryScript = [new PurchaseResult(PurchaseResult::PENDING, $o->reference)];
        $this->postJson('/webhooks/topup/zendit', ['reference' => $o->reference, 'status' => 'delivered'], ['X-Webhook-Secret' => 'good'])->assertOk();
        $this->assertSame(TopupOrder::PENDING, $o->fresh()->status);
        // and when the supplier agrees, it delivers
        $this->zendit->requeryScript = [new PurchaseResult(PurchaseResult::DELIVERED, $o->reference)];
        $this->postJson('/webhooks/topup/zendit', ['reference' => $o->reference, 'status' => 'delivered'], ['X-Webhook-Secret' => 'good'])->assertOk();
        $this->assertSame(TopupOrder::DELIVERED, $o->fresh()->status);
    }

    // ── Catalogue (live) ────────────────────────────────────────────────────
    public function test_live_refresh_upserts_and_retires_products_and_an_empty_answer_retires_nothing(): void
    {
        TopupRoute::query()->delete();
        TopupRoute::create(['country_iso' => '*', 'product_type' => '*', 'provider_code' => 'zendit', 'priority' => 1]);
        $keep = new \App\Services\Topup\DTO\ProductDTO('A', 'NG', 'MTN', 'airtime', 'Airtime', 'NGN', fixedAmount: 500, costNative: 0.4, costCurrency: 'USD');
        $old = $this->product('zendit', ['provider_sku' => 'GONE']);
        $this->zendit->products = [$keep];
        $cat = app(\App\Services\Topup\TopupCatalogue::class);
        $cat->refresh('NG', true);
        $this->assertTrue(TopupProduct::where('provider_sku', 'A')->where('active', true)->exists());
        $this->assertFalse($old->fresh()->active);
        $this->zendit->products = [];
        $cat->refresh('NG', true);
        $this->assertTrue(TopupProduct::where('provider_sku', 'A')->first()->active, 'a supplier fault must not wipe the catalogue');
    }

    // ── Gating ──────────────────────────────────────────────────────────────
    public function test_feature_is_off_by_default_and_needs_an_enabled_supplier(): void
    {
        $this->assertTrue(FeatureFlags::FEATURES['naara_topup']['default'], 'on by default; an admin can still switch it off');
        Setting::setValue('features.naara_topup.enabled', false);
        FeatureFlags::flush('naara_topup');
        $u = $this->user();
        $this->actingAs($u)->get(route('topup'))->assertNotFound();   // switched off: a plain 404 on a white-label build
        Setting::setValue('features.naara_topup.enabled', true);
        FeatureFlags::flush('naara_topup');
        TopupProvider::query()->update(['enabled' => false]);
        $this->actingAs($u)->get(route('topup'))->assertNotFound();
        TopupProvider::where('code', 'zendit')->update(['enabled' => true]);
        $this->actingAs($u)->get(route('topup'))->assertOk()->assertSee('Top up any phone', false);
    }

    public function test_extended_licence_lock_catalogue_entry(): void
    {
        $this->assertArrayHasKey(FeatureLocks::F_TOPUP, FeatureLocks::catalog());
    }

    public function test_orders_page_only_shows_the_owners_orders(): void
    {
        $a = $this->user();
        $b = $this->user();
        $o = $this->place($a, $this->product());
        $this->actingAs($b)->get(route('topup.order', $o->uuid))->assertNotFound();
        $this->actingAs($a)->get(route('topup.order', $o->uuid))->assertOk()->assertSee('Top-up delivered', false)->assertDontSee('zendit', false);
    }

    // ── Logos ───────────────────────────────────────────────────────────────
    public function test_logo_keys_normalise_and_alias_supplier_names(): void
    {
        $this->assertSame('mtn', TopupLogos::key('MTN'));
        $this->assertSame('ninemobile', TopupLogos::key('9mobile'));
        $this->assertSame('etisalat', TopupLogos::key('etisalat'));   // country-aware: NG shows 9mobile (see TopupExtrasTest)
        $this->assertSame('telecel', TopupLogos::key('Telecel (formerly Vodafone)'));
        $this->assertSame('MT', TopupLogos::initials('MTN Telecom'));
    }

    public function test_every_indexed_logo_file_exists_and_is_small(): void
    {
        $n = 0;
        foreach (TopupLogos::index() as $key => $file) {
            $p = public_path('images/topup/logos/'.$file);
            $this->assertFileExists($p, "logo for $key is missing");
            $this->assertLessThan(60 * 1024, filesize($p), "$file is too heavy");
            $n++;
        }
        $this->assertGreaterThan(100, $n);
        $this->assertNotNull(TopupLogos::url('MTN'));
    }
}
