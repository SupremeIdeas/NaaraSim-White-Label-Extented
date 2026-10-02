<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutCountryRail;
use App\Models\PaymentCharge;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payouts\FlutterwaveBankResolver;
use App\Services\Payouts\PaystackBankResolver;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RadarAlerts;
use App\Services\Payouts\Rail\RailAdvisor;
use App\Services\Payouts\Rail\RailHistory;
use App\Services\Payouts\Rail\RailRegistrySeeder;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Rail Guide (Addendum B) G1 registry + G2 history/advisor. */
class PayoutRailGuideTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\InstallsFakeRail;

    protected function tearDown(): void
    {
        $this->removeFakeRails();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->installFakeRail('payoneer'); // the real Payoneer rail is delivered later; stand in for it
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config([
            'services.paystack.secret_key' => 'sk_p', 'services.flutterwave.secret_key' => 'sk_f', 'services.stripe.secret_key' => 'sk_s',
            'services.stripe.base_url' => 'https://api.stripe.test/v1', 'services.paystack.base_url' => 'https://api.paystack.test',
        ]);
    }

    private function corridor(string $country, string $provider, bool $enabled = true, string $ccy = 'USD'): PayoutCorridor
    {
        return PayoutCorridor::updateOrCreate(['country' => $country, 'currency' => $ccy, 'provider' => $provider, 'method' => $provider === 'stripe' ? 'stripe_connect' : 'bank'], ['enabled' => $enabled, 'priority' => 10]);
    }

    private function covers(string $country, string $rail, string $override = 'none'): PayoutCountryRail
    {
        return PayoutCountryRail::updateOrCreate(['country' => $country, 'rail' => $rail], ['provider_supports' => true, 'admin_override' => $override, 'verified_at' => now(), 'verified_by' => 'seed']);
    }

    private function user(string $country = 'KE'): User
    {
        return User::factory()->create(['country_code' => $country]);
    }

    private function topUps(User $u, string $gateway, int $n): void
    {
        foreach (range(1, $n) as $i) {
            WalletTransaction::create(['user_id' => $u->id, 'type' => 'credit', 'amount' => 10, 'currency' => 'USD', 'reference' => "topup:{$gateway}:R".uniqid().$i, 'balance_before' => 0, 'balance_after' => 10]);
        }
    }

    // ---- G1 registry ----

    public function test_migration_seeds_the_registry_from_the_resolver_constants_so_nothing_changes(): void
    {
        $registry = app(PayoutRailRegistry::class);
        $this->assertTrue($registry->seeded());

        $paystack = app(PaystackBankResolver::class);
        $flutterwave = app(FlutterwaveBankResolver::class);
        foreach (['NG', 'GH', 'ZA', 'KE', 'CI', 'EG'] as $c) {
            $this->assertTrue($paystack->supports($c), "paystack {$c}");
        }
        foreach (['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA', 'RW', 'ZM', 'CI', 'SN', 'CM', 'EG'] as $c) {
            $this->assertTrue($flutterwave->supports($c), "flutterwave {$c}");
        }
        foreach (['US', 'GB', 'BR', 'IN'] as $c) {
            $this->assertFalse($paystack->supports($c));
            $this->assertFalse($flutterwave->supports($c));
        }
        $this->assertFalse($paystack->supports('UG'), 'Paystack never covered Uganda');
    }

    public function test_the_resolvers_read_the_registry_once_seeded(): void
    {
        $this->covers('KE', 'paystack', 'force_off');
        app(PayoutRailRegistry::class)->flush();
        $this->assertFalse(app(PaystackBankResolver::class)->supports('KE'), 'an admin force_off is honoured by the money path');

        $this->covers('KE', 'paystack', 'none');
        app(PayoutRailRegistry::class)->flush();
        $this->assertTrue(app(PaystackBankResolver::class)->supports('KE'));
    }

    public function test_cell_states_never_show_coming_soon_as_available(): void
    {
        $r = app(PayoutRailRegistry::class);
        $this->corridor('GH', 'paystack', false, 'GHS');      // provider covers GH, but we have NOT enabled it
        $this->assertSame('coming_soon', $r->state('GH', 'paystack'));

        $this->corridor('GH', 'paystack', true, 'GHS');        // enabling the corridor flips it (cache is flushed by the model)
        $this->assertSame('available', $r->state('GH', 'paystack'));

        $this->assertSame('not_supported', $r->state('GH', 'stripe_connect'));
        $this->assertSame('not_supported', $r->state('US', 'paystack'));
    }

    public function test_deny_list_blocks_and_overrides_work(): void
    {
        $r = app(PayoutRailRegistry::class);
        $this->corridor('KE', 'paystack', true, 'KES');
        $this->assertSame('available', $r->state('KE', 'paystack'));

        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, 'KE');
        $this->assertSame('blocked', $r->state('KE', 'paystack'));
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, '');

        $this->covers('KE', 'paystack', 'force_off');
        $r->flush();
        $this->assertSame('not_supported', $r->state('KE', 'paystack'));
        $this->covers('KE', 'global', 'force_on');
        $r->flush();
        $this->assertSame('available', $r->state('KE', 'global'));
    }

    public function test_matrix_lists_every_country_with_the_four_columns(): void
    {
        $this->corridor('NG', 'paystack', true, 'NGN');
        $m = collect(app(PayoutRailRegistry::class)->matrix());

        $this->assertGreaterThan(190, $m->count());
        $ng = $m->firstWhere('country', 'NG');
        $this->assertSame(['paystack', 'flutterwave', 'stripe_connect', 'global'], array_keys($ng['rails']));
        $this->assertSame('available', $ng['rails']['paystack']);
        $this->assertSame('not_supported', $m->firstWhere('country', 'JP')['rails']['paystack']);
    }

    public function test_seeder_reads_stripe_country_specs_and_never_enables_a_corridor(): void
    {
        Http::fake([
            'api.stripe.test/v1/country_specs*' => Http::response(['data' => [['id' => 'US'], ['id' => 'GB']], 'has_more' => false]),
            'api.paystack.test/country' => Http::response(['data' => [['iso_code' => 'NG'], ['iso_code' => 'GH'], ['iso_code' => 'ZA'], ['iso_code' => 'KE'], ['iso_code' => 'CI'], ['iso_code' => 'EG']]]),
        ]);

        $out = app(RailRegistrySeeder::class)->run();

        $this->assertSame('coming_soon', app(PayoutRailRegistry::class)->state('US', 'stripe_connect'), 'covered by Stripe, but no corridor enabled by us');
        $this->assertSame('api', PayoutCountryRail::where('country', 'GB')->where('rail', 'stripe_connect')->value('verified_by'));
        $this->assertSame([], $out['drift']);
        $this->assertNotNull(PayoutCountryRail::where('country', 'KE')->where('rail', 'paystack')->value('verified_at'));
    }

    public function test_seeder_reports_a_provider_disagreement_without_overriding_it_and_never_touches_an_admin_override(): void
    {
        $this->covers('KE', 'paystack', 'force_off');
        Http::fake([
            'api.stripe.test/*' => Http::response('boom', 500),
            'api.paystack.test/country' => Http::response(['data' => [['iso_code' => 'NG']]]),     // provider "lost" the others
        ]);

        $out = app(RailRegistrySeeder::class)->run();

        $this->assertNotEmpty($out['drift']);
        $this->assertTrue(PayoutCountryRail::where('country', 'GH')->where('rail', 'paystack')->value('provider_supports'), 'drift is reported, never silently applied');
        $this->assertSame('force_off', PayoutCountryRail::where('country', 'KE')->where('rail', 'paystack')->value('admin_override'));
        $this->assertContains('stripe_connect (no key / API unreachable)', $out['skipped']);
    }

    public function test_audit_alerts_on_drift_and_counts_rows_needing_reverification(): void
    {
        Queue::fake();
        PayoutCountryRail::query()->update(['verified_at' => now()->subDays(60)]);
        Http::fake([
            'api.stripe.test/v1/country_specs*' => Http::response(['data' => [['id' => 'US']], 'has_more' => false]),
            'api.paystack.test/country' => Http::response(['data' => [['iso_code' => 'NG'], ['iso_code' => 'GH'], ['iso_code' => 'ZA'], ['iso_code' => 'KE'], ['iso_code' => 'CI'], ['iso_code' => 'EG']]]),
        ]);

        $r = app(RailRegistrySeeder::class)->audit();

        $this->assertNotEmpty($r['drift'], 'Stripe now lists US, the registry does not');
        $this->assertGreaterThan(0, $r['stale']);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'guide_registry_drift');
    }

    // ---- G2 history ----

    public function test_history_counts_gateways_from_the_users_own_topups(): void
    {
        $u = $this->user();
        $this->topUps($u, 'flutterwave', 3);
        $this->topUps($u, 'paystack', 1);
        $this->topUps($u, 'nowpayments', 2);
        WalletTransaction::create(['user_id' => $u->id, 'type' => 'credit', 'amount' => 1, 'currency' => 'USD', 'reference' => 'topup:paystack:OLD', 'balance_before' => 0, 'balance_after' => 1])
            ->forceFill(['created_at' => now()->subDays(400)])->saveQuietly();

        $this->assertSame(['flutterwave' => 3, 'cryptomus' => 2, 'paystack' => 1], app(RailHistory::class)->counts($u));
    }

    // ---- G2 advisor ----

    private function advise(User $u, ?string $country = null): array
    {
        return app(RailAdvisor::class)->advise($u, $country);
    }

    private function states(array $advice): array
    {
        return array_column($advice['options'], 'state', 'rail');
    }

    public function test_a_user_with_history_gets_the_rail_they_already_use(): void
    {
        $this->corridor('KE', 'paystack', true, 'KES');
        $this->corridor('KE', 'flutterwave', true, 'KES');
        $u = $this->user();
        $this->topUps($u, 'flutterwave', 3);

        $a = $this->advise($u);

        $this->assertSame('fast_available', $a['verdict']);
        $this->assertSame('flutterwave', $a['options'][0]['rail']);
        $this->assertSame('recommended', $a['options'][0]['state']);
        $this->assertContains('you_paid_with_it_3_times', $a['options'][0]['reasons']);
        $this->assertContains('you_use_this', $a['options'][0]['badges']);
        $this->assertSame('available', $this->states($a)['paystack']);
    }

    public function test_with_no_history_the_platform_ranking_decides_then_the_static_order(): void
    {
        $this->corridor('KE', 'paystack', true, 'KES');
        $this->corridor('KE', 'flutterwave', true, 'KES');
        $u = $this->user();

        // No platform volume anywhere: static order paystack > flutterwave.
        $this->assertSame('paystack', $this->advise($u)['options'][0]['rail']);

        // Flutterwave takes the platform's inbound volume: it now leads.
        PaymentCharge::create(['gateway' => 'flutterwave', 'reference' => 'X1', 'amount' => 900, 'currency' => 'USD']);
        PaymentCharge::create(['gateway' => 'paystack', 'reference' => 'X2', 'amount' => 10, 'currency' => 'USD']);
        Cache::flush();
        app(\App\Services\Payouts\PayoutService::class)->flushRanking();

        $a = $this->advise($u);
        $this->assertSame('flutterwave', $a['options'][0]['rail']);
        $this->assertContains('highest_platform_volume', $a['options'][0]['reasons']);
    }

    public function test_a_country_only_on_flutterwave_never_recommends_paystack(): void
    {
        $this->corridor('UG', 'flutterwave', true, 'UGX');
        $u = $this->user('UG');
        $this->topUps($u, 'paystack', 5);                    // even with Paystack history

        $a = $this->advise($u);

        $this->assertSame('flutterwave', $a['options'][0]['rail']);
        $this->assertSame('not_supported', $this->states($a)['paystack']);
    }

    public function test_stripe_is_only_offered_when_enabled_and_a_declined_onboarding_removes_it(): void
    {
        $u = $this->user('US');
        $this->covers('US', 'stripe_connect');
        $this->corridor('US', 'stripe', false);
        $this->assertSame('none_available', $this->advise($u)['verdict']);
        $this->assertSame('coming_soon', $this->states($this->advise($u))['stripe_connect']);

        $this->corridor('US', 'stripe', true);
        $this->assertSame('stripe_connect', $this->advise($u)['options'][0]['rail']);

        PayoutAccount::create(['user_id' => $u->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD', 'bank_code' => 'stripe', 'account_number' => 'acct_1',
            'account_name' => 'acct_1', 'provider' => 'stripe', 'provider_status' => 'rejected', 'is_verified' => false]);
        $this->assertSame('none_available', $this->advise($u)['verdict'], 'declined onboarding removes Stripe and re-runs the scoring');
    }

    public function test_an_unhealthy_provider_is_skipped_and_marked_unavailable(): void
    {
        $this->corridor('KE', 'paystack', true, 'KES');
        $this->corridor('KE', 'flutterwave', true, 'KES');
        Cache::put(RadarAlerts::UNHEALTHY_KEY.'paystack', true, 600);

        $a = $this->advise($this->user());

        $this->assertSame('flutterwave', $a['options'][0]['rail']);
        $this->assertSame('unavailable', $this->states($a)['paystack']);
    }

    public function test_enabled_but_unhealthy_rails_are_temporarily_unavailable_not_not_supported(): void
    {
        $this->corridor('KE', 'paystack', true, 'KES');
        $this->corridor('KE', 'flutterwave', true, 'KES');
        Cache::put(RadarAlerts::UNHEALTHY_KEY.'paystack', true, 600);
        Cache::put(RadarAlerts::UNHEALTHY_KEY.'flutterwave', true, 600);

        $a = $this->advise($this->user());

        $this->assertSame('fast_unavailable', $a['verdict']);
        $states = $this->states($a);
        $this->assertSame(['unavailable', 'unavailable'], [$states['paystack'], $states['flutterwave']]);
        foreach (['en', 'fr', 'sw', 'ar'] as $lang) {
            app()->setLocale($lang);
            $this->assertNotSame('payout_guide.verdict_fast_unavailable', __('payout_guide.verdict_fast_unavailable'));
        }
    }

    public function test_global_is_only_a_fallback_and_a_disabled_other_option_when_local_exists(): void
    {
        $this->covers('KE', 'global');
        $this->corridor('KE', 'payoneer', true);

        // No fast rail in KE: global is THE answer.
        $only = $this->advise($this->user());
        $this->assertSame('global_only', $only['verdict']);
        $this->assertSame('global_fallback', $only['options'][0]['state']);

        // A fast rail appears: global drops to a disabled "other option".
        $this->corridor('KE', 'paystack', true, 'KES');
        $a = $this->advise($this->user());
        $this->assertSame('paystack', $a['options'][0]['rail']);
        $this->assertSame('disabled', $this->states($a)['global']);

        Setting::setValue('payouts.global_rail.allow_when_local_available', true);
        $this->assertSame('available', $this->states($this->advise($this->user()))['global']);
    }

    public function test_a_blocked_country_gets_no_options(): void
    {
        $this->corridor('KE', 'paystack', true, 'KES');
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, 'KE');

        $a = $this->advise($this->user());

        $this->assertSame(['blocked', []], [$a['verdict'], $a['options']]);
    }

    public function test_nothing_enabled_anywhere_is_none_available_with_honest_states(): void
    {
        $a = $this->advise($this->user('JP'));

        $this->assertSame('none_available', $a['verdict']);
        $this->assertSame(['not_supported'], array_values(array_unique(array_column($a['options'], 'state'))));
    }

    public function test_ordering_is_deterministic_and_the_users_current_rail_is_reported(): void
    {
        $this->corridor('KE', 'paystack', true, 'KES');
        $this->corridor('KE', 'flutterwave', true, 'KES');
        $u = $this->user();
        PayoutAccount::create(['user_id' => $u->id, 'type' => 'bank', 'country' => 'KE', 'currency' => 'KES', 'bank_code' => '1', 'account_number' => '123',
            'account_name' => 'T', 'provider' => 'flutterwave', 'is_verified' => true, 'is_default' => true]);

        $a = $this->advise($u);

        $this->assertSame($a, $this->advise($u));
        $this->assertSame('flutterwave', $a['current']['rail']);
    }
}
