<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\CorridorRouter;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutQuoter;
use App\Services\Pricing\CurrencyService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Global Payout Layer Phase 1: corridor routing, strict payout FX (no stale
 * fallback, no "unknown = USD"), and the locked quote on the request row.
 */
class PayoutCorridorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
        Cache::flush();
    }

    private function corridor(array $over = []): PayoutCorridor
    {
        $row = $over + [
            'country' => 'GH', 'currency' => 'GHS', 'provider' => 'paystack', 'method' => 'bank',
            'enabled' => true, 'priority' => 10,
        ];

        // The default corridors are already seeded (disabled) — update in place.
        return PayoutCorridor::updateOrCreate(
            array_intersect_key($row, array_flip(['country', 'currency', 'provider', 'method'])),
            $row,
        );
    }

    private function account(string $country = 'GH', string $currency = 'GHS', string $provider = 'paystack'): PayoutAccount
    {
        $u = User::factory()->create();

        return PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => $country, 'currency' => $currency, 'bank_code' => '058',
            'account_number' => '0123456789', 'account_name' => 'Test User', 'provider' => $provider,
            'is_verified' => true, 'is_default' => true,
        ]);
    }

    private function fakeFeed(array $rates = ['GHS' => 15.5, 'GBP' => 0.8]): void
    {
        Http::fake(['open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => $rates])]);
    }

    // ---- seed defaults ----

    public function test_default_corridors_ship_with_only_nigeria_enabled(): void
    {
        $enabled = PayoutCorridor::query()->enabled()->pluck('country')->unique()->values()->all();
        $this->assertSame(['NG'], $enabled);
        $this->assertTrue(PayoutCorridor::query()->where('country', 'GH')->exists(), 'GH is listed but disabled');
    }

    // ---- router ----

    public function test_router_orders_by_priority_and_skips_disabled_and_unconfigured_gateways(): void
    {
        config(['services.paystack.secret_key' => 'sk_test', 'services.flutterwave.secret_key' => null]);
        $this->corridor(['provider' => 'flutterwave', 'priority' => 5]);   // enabled but gateway not configured
        $this->corridor(['provider' => 'paystack', 'priority' => 20]);
        $this->corridor(['currency' => 'GHS', 'provider' => 'cryptomus', 'method' => 'crypto', 'enabled' => false, 'priority' => 1]);

        $options = app(CorridorRouter::class)->optionsFor('gh');

        $this->assertSame(['paystack'], $options->pluck('provider')->all());
    }

    public function test_router_filters_by_amount_limits(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        $this->corridor(['min_usd' => 10, 'max_usd' => 100]);
        $router = app(CorridorRouter::class);

        $this->assertNull($router->pick('GH', 'GHS', 5));
        $this->assertNotNull($router->pick('GH', 'GHS', 50));
        $this->assertNull($router->pick('GH', 'GHS', 500));
    }

    public function test_corridor_for_account_matches_country_currency_and_provider(): void
    {
        $match = $this->corridor();
        $router = app(CorridorRouter::class);

        $this->assertSame($match->id, $router->corridorForAccount($this->account())->id);
        $this->assertNull($router->corridorForAccount($this->account('GH', 'GHS', 'flutterwave')));
        $this->assertNull($router->corridorForAccount($this->account('KE', 'KES')));
    }

    // ---- strict FX ----

    public function test_usd_and_ngn_rates_never_need_the_feed(): void
    {
        Http::fake();
        $fx = app(CurrencyService::class);
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1600);
        $fx->flushNgnRate();

        $this->assertSame(1.0, $fx->usdTo('USD'));
        $this->assertSame(1.0, $fx->usdTo('usdt'));
        $this->assertEquals(1600.0, $fx->usdTo('NGN'));
        Http::assertNothingSent();
    }

    public function test_other_currency_uses_the_live_feed(): void
    {
        $this->fakeFeed();
        $fx = app(CurrencyService::class);

        $this->assertEquals(15.5, $fx->usdTo('ghs'));
        $this->assertEquals(155.0, $fx->usdToLocal(10, 'GHS'));
    }

    public function test_no_rate_means_refusal_not_a_silent_fallback(): void
    {
        // Feed reachable but the currency is absent.
        $this->fakeFeed(['GHS' => 15.5]);
        $this->expectException(PayoutException::class);
        app(CurrencyService::class)->usdTo('XXX');
    }

    public function test_feed_outage_refuses_instead_of_using_the_hardcoded_display_fallback(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response('boom', 500)]);

        // GHS has a display fallback (15.0) — it must NOT be used for a payout.
        $this->expectException(PayoutException::class);
        app(CurrencyService::class)->usdTo('GHS');
    }

    // ---- quoter ----

    public function test_legacy_usd_and_ngn_need_no_corridor(): void
    {
        $quoter = app(PayoutQuoter::class);
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(CurrencyService::class)->flushNgnRate();

        $ngn = $quoter->quote(10, 'NGN', $this->account('NG', 'NGN'));
        $this->assertEquals(15000.0, $ngn['local_amount']);
        $this->assertNotNull($ngn['quote']['corridor_id'], 'NG/NGN/paystack is a seeded enabled corridor');
        $this->assertEquals(1500.0, $ngn['quote']['fx_rate']);

        $usd = $quoter->quote(10, 'USD', $this->account('US', 'USD', 'paypal'));
        $this->assertEquals(10.0, $usd['local_amount']);
        $this->assertNull($usd['quote']['corridor_id'], 'no corridor row needed for legacy USD');
    }

    public function test_a_non_legacy_currency_without_an_enabled_corridor_is_refused(): void
    {
        $this->fakeFeed();
        $this->corridor(['enabled' => false]);

        $this->expectException(PayoutException::class);
        $this->expectExceptionMessage("GHS aren't available yet");
        app(PayoutQuoter::class)->quote(10, 'GHS', $this->account());
    }

    public function test_enabled_corridor_prices_the_payout_and_locks_the_quote(): void
    {
        $this->fakeFeed();
        $corridor = $this->corridor(['platform_fee_bps' => 100, 'platform_fee_flat_usd' => 0.5]); // 1% + $0.50
        $q = app(PayoutQuoter::class)->quote(100, 'GHS', $this->account());

        // fee = 1.00 + 0.50 = 1.50 ; sent = 98.50 USD * 15.5
        $this->assertEquals(1.5, $q['quote']['platform_fee_usd']);
        $this->assertEquals(1526.75, $q['local_amount']);
        $this->assertEquals(100.0, $q['quote']['usd_amount']);
        $this->assertSame($corridor->id, $q['quote']['corridor_id']);
        $this->assertTrue($q['quote']['quote_expires_at']->gt(now()->addHours(23)));
    }

    public function test_amount_outside_corridor_limits_or_below_the_fee_is_refused(): void
    {
        $this->fakeFeed();
        $this->corridor(['min_usd' => 20, 'platform_fee_flat_usd' => 25]);
        $quoter = app(PayoutQuoter::class);

        try {
            $quoter->quote(10, 'GHS', $this->account());
            $this->fail('below min should be refused');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('limits', $e->getMessage());
        }

        $this->expectException(PayoutException::class);
        $quoter->quote(22, 'GHS', $this->account()); // 22 - 25 fee <= 0
    }

    public function test_the_locked_quote_lands_on_the_payout_request_row(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(CurrencyService::class)->flushNgnRate();

        $user = User::factory()->create();
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => '0123456789', 'account_name' => 'T', 'provider' => 'paystack',
            'is_verified' => true, 'is_default' => true,
        ]);
        $earnings = app(\App\Services\Staff\StaffEarningsService::class);
        $earnings->accrue($user, 50, 'seed:1', now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(), 'seed');

        $request = app(\App\Services\Staff\StaffWithdrawalService::class)->request($user, $account, 20);

        $this->assertEquals(30000.0, (float) $request->amount);
        $this->assertEquals(20.0, (float) $request->usd_amount);
        $this->assertEquals(1500.0, (float) $request->fx_rate);
        $this->assertNotNull($request->fx_locked_at);
        $this->assertNotNull($request->quote_expires_at);
        $this->assertNotNull($request->corridor_id, 'NG/NGN/paystack corridor is seeded enabled');
    }

    public function test_corridor_migration_is_idempotent(): void
    {
        $before = PayoutCorridor::count();
        $migration = require base_path('database/migrations/2026_10_03_100300_seed_default_payout_corridors.php');
        $migration->up();
        $this->assertSame($before, PayoutCorridor::count());
    }

    public function test_est_provider_cost_is_never_serialised(): void
    {
        $c = $this->corridor(['est_provider_cost_bps' => 250]);
        $this->assertArrayNotHasKey('est_provider_cost_bps', $c->toArray());
    }

    // ---- encryption at rest + blind index ----

    public function test_account_number_is_encrypted_at_rest_and_round_trips(): void
    {
        $account = $this->account();

        $raw = \DB::table('payout_accounts')->where('id', $account->id)->value('account_number');
        $this->assertStringNotContainsString('0123456789', (string) $raw);
        $this->assertSame('0123456789', $account->fresh()->account_number);
        $this->assertSame('••••••6789', $account->fresh()->masked_number);
    }

    public function test_blind_index_matches_equal_destinations_only(): void
    {
        $a = $this->account();
        $this->assertSame(PayoutAccount::lookupHashFor('0123456789'), $a->fresh()->lookup_hash);
        $this->assertSame(PayoutAccount::lookupHashFor('0123 456 789'), $a->lookup_hash, 'whitespace-insensitive');
        $this->assertNotSame(PayoutAccount::lookupHashFor('0123456780'), $a->lookup_hash);

        $a->update(['account_number' => '9999999999']);
        $this->assertSame(PayoutAccount::lookupHashFor('9999999999'), $a->fresh()->lookup_hash);
    }

    public function test_re_encrypt_migration_converts_legacy_plaintext_and_is_resumable(): void
    {
        $u = User::factory()->create();
        // A legacy plaintext row, as written before the cast existed.
        $id = \DB::table('payout_accounts')->insertGetId([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => '0999888777', 'account_name' => 'Legacy', 'provider' => 'paystack',
            'is_verified' => true, 'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stripeId = \DB::table('payout_accounts')->insertGetId([
            'user_id' => $u->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD', 'bank_code' => 'stripe',
            'account_number' => 'acct_123', 'account_name' => 'acct_123', 'provider' => 'stripe',
            'is_verified' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_10_03_100400_encrypt_payout_account_numbers.php');
        $migration->up();
        $migration->up(); // second run must not double-encrypt

        $raw = \DB::table('payout_accounts')->where('id', $id)->value('account_number');
        $this->assertStringNotContainsString('0999888777', $raw);
        $this->assertSame('0999888777', PayoutAccount::find($id)->account_number);
        $this->assertSame(PayoutAccount::lookupHashFor('0999888777'), PayoutAccount::find($id)->lookup_hash);
        $this->assertSame('acct_123', PayoutAccount::find($stripeId)->provider_recipient_ref);

        $migration->down();
        $this->assertSame('0999888777', \DB::table('payout_accounts')->where('id', $id)->value('account_number'));
    }

    // ---- Stripe ----

    public function test_stripe_lookup_uses_provider_recipient_ref_not_the_encrypted_column(): void
    {
        $u = User::factory()->create();
        $acct = PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD', 'bank_code' => 'stripe',
            'account_number' => 'acct_abc', 'account_name' => 'acct_abc', 'provider' => 'stripe',
            'provider_recipient_ref' => 'acct_abc', 'is_verified' => false, 'is_default' => true,
        ]);

        $this->assertSame($acct->id, app(\App\Services\Payouts\StripeConnectService::class)->findByAccountId('acct_abc')?->id);
        $this->assertNull(app(\App\Services\Payouts\StripeConnectService::class)->findByAccountId('acct_nope'));
    }

    public function test_stripe_account_is_created_with_the_users_country(): void
    {
        config(['services.stripe.secret_key' => 'sk_test', 'services.stripe.base_url' => 'https://api.stripe.test/v1']);
        Http::fake(['api.stripe.test/*' => Http::response(['id' => 'acct_new'])]);
        $user = User::factory()->create(['country_code' => 'gb']);

        app(\App\Services\Payouts\StripeConnectService::class)->accountFor($user);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/accounts') && $r['country'] === 'GB');
    }

    public function test_stripe_is_refused_for_a_country_without_an_enabled_stripe_corridor(): void
    {
        config(['services.stripe.secret_key' => 'sk_test', 'services.stripe.base_url' => 'https://api.stripe.test/v1']);
        Http::fake(['api.stripe.test/*' => Http::response(['id' => 'acct_new'])]);
        $this->corridor(['country' => 'US', 'currency' => 'USD', 'provider' => 'stripe', 'method' => 'stripe_connect']);

        $this->expectException(PayoutException::class);
        app(\App\Services\Payouts\StripeConnectService::class)->accountFor(User::factory()->create(['country_code' => 'NG']));
    }
}
