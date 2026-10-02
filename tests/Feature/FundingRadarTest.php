<?php

namespace Tests\Feature;

use App\Jobs\RadarBumpJob;
use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\Partner;
use App\Models\PartnerEarning;
use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutExposureSnapshot;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Models\PayoutWithdrawalStatHourly;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Payouts\FloatService;
use App\Services\Payouts\PayoutEligibility;
use App\Services\Payouts\Rail\FundingRadar;
use App\Services\Payouts\Rail\RadarSnapshotter;
use App\Services\Payouts\Rail\RailEnrollmentService;
use App\Services\Payouts\Rail\WithdrawableBalanceResolver;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\Money4;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Funding Radar (Addendum A) R1–R3: enrollment + Stripe eligibility, the shared balance
 * resolver and eligibility predicates, and the exposure / forecast / top-up maths.
 */
class FundingRadarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
        Setting::setValue(PayoutSettings::MIN, 5);
        Carbon::setTestNow('2026-10-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $country = 'KE'): User
    {
        return User::factory()->create(['country_code' => $country, 'created_at' => now()->subYear()]);
    }

    private function account(User $u, string $provider = 'payoneer', bool $verified = true, string $country = 'KE'): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => $country, 'currency' => 'USD', 'bank_code' => 'x',
            'account_number' => (string) random_int(1000000000, 9999999999), 'account_name' => 'T', 'provider' => $provider,
            'is_verified' => $verified, 'is_default' => true,
        ]);
    }

    private function referral(User $u, float $usd): void
    {
        app(ReferralEarningsService::class)->accrue($u, User::factory()->create(), 'esim', $usd, 'seed:'.uniqid());
    }

    /** An ACTIVE enrolled user with a verified account and a referral balance. */
    private function enrolled(float $usd, string $country = 'KE', string $currency = 'USD', string $provider = 'payoneer'): User
    {
        $u = $this->user($country);
        $this->account($u, $provider, true, $country);
        PayoutRailEnrollment::where('user_id', $u->id)->update(['currency' => $currency]);
        $this->referral($u, $usd);

        return $u;
    }

    // ---- R1 enrollment ----

    public function test_selecting_a_global_rail_records_stripe_eligibility_and_reason(): void
    {
        $svc = app(RailEnrollmentService::class);

        $a = $svc->select($this->user('KE'), 'payoneer', 'KE', 'usd');
        $this->assertSame([false, 'country_not_supported', 'selected'], [$a->stripe_connect_eligible, $a->ineligible_reason, $a->status]);

        PayoutCorridor::create(['country' => 'US', 'currency' => 'USD', 'provider' => 'stripe', 'method' => 'stripe_connect', 'enabled' => true]);
        $b = $svc->select($this->user('US'), 'payoneer', 'US');
        $this->assertSame([true, 'user_choice'], [$b->stripe_connect_eligible, $b->ineligible_reason]);

        $declinedUser = $this->user('US');
        PayoutAccount::create(['user_id' => $declinedUser->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD', 'bank_code' => 'stripe',
            'account_number' => 'acct_9', 'account_name' => 'acct_9', 'provider' => 'stripe', 'provider_status' => 'rejected', 'is_verified' => false]);
        $c = $svc->select($declinedUser, 'payoneer', 'US');
        $this->assertSame('onboarding_declined', $c->ineligible_reason);
    }

    public function test_reselecting_is_idempotent_and_never_resets_progress(): void
    {
        $svc = app(RailEnrollmentService::class);
        $u = $this->user();
        $first = $svc->select($u, 'payoneer', 'KE');
        $first->forceFill(['status' => 'active'])->save();

        $again = $svc->select($u, 'payoneer', 'KE');

        $this->assertSame($first->id, $again->id);
        $this->assertSame('active', $again->fresh()->status);
        $this->assertSame(1, PayoutRailEnrollment::count());
        $this->expectException(\InvalidArgumentException::class);
        $svc->select($u, 'paystack', 'NG');
    }

    public function test_saving_a_global_rail_account_keeps_the_enrollment_in_step(): void
    {
        $u = $this->user();
        $account = $this->account($u, 'payoneer', verified: false);
        $this->assertSame('onboarding', PayoutRailEnrollment::first()->status);

        $account->forceFill(['is_verified' => true])->save();
        $e = PayoutRailEnrollment::first();
        $this->assertSame('active', $e->status);
        $this->assertNotNull($e->activated_at);

        $account->forceFill(['provider_status' => 'declined', 'is_verified' => false])->save();
        $this->assertSame('declined', PayoutRailEnrollment::first()->status);
    }

    public function test_an_admin_pause_survives_provider_status_changes_and_a_local_rail_never_enrolls(): void
    {
        $u = $this->user();
        $account = $this->account($u);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $e = PayoutRailEnrollment::first();
        app(RailEnrollmentService::class)->pause($e, $admin, 'investigating');

        $account->forceFill(['is_verified' => true])->save();
        $this->assertSame('paused', $e->fresh()->status);

        app(RailEnrollmentService::class)->resume($e->fresh(), $admin, 'cleared');
        $this->assertSame('active', $e->fresh()->status);

        $this->account($this->user('NG'), 'paystack', true, 'NG');
        $this->assertSame(1, PayoutRailEnrollment::count(), 'only global-rail accounts enroll');
    }

    public function test_backfill_is_idempotent(): void
    {
        $u = $this->user();
        $this->account($u);
        PayoutRailEnrollment::query()->delete();

        $this->artisan('payouts:rail-backfill')->expectsOutputToContain('enrollments=1')->assertSuccessful();
        $this->artisan('payouts:rail-backfill')->assertSuccessful();
        $this->assertSame(1, PayoutRailEnrollment::count());
    }

    // ---- R2 resolver + eligibility ----

    public function test_resolver_matches_the_five_services_for_a_multi_bucket_user_and_ignores_deposits(): void
    {
        $u = $this->user();
        $this->referral($u, 12.5);
        app(CreditService::class)->earn($u, 1000, 'referral', 'cr:1', 'x', withdrawable: true);          // withdrawable credits
        app(CreditService::class)->earn($u, 5000, 'checkin', 'cr:2', 'x', withdrawable: false);          // NOT withdrawable
        $m = Merchant::create(['owner_user_id' => $u->id, 'business_name' => 'B', 'slug' => 'b', 'status' => 'active']);
        MerchantEarning::create(['merchant_id' => $m->id, 'type' => 'accrual', 'amount' => 7, 'balance_after' => 7, 'currency' => 'USD', 'reference' => 'm:1']);
        $p = Partner::create(['owner_user_id' => $u->id, 'status' => 'active']);
        PartnerEarning::create(['partner_id' => $p->id, 'type' => 'accrual', 'amount' => 3, 'balance_after' => 3, 'currency' => 'USD', 'reference' => 'p:1']);
        // A top-up must never show up.
        app(\App\Services\Wallet\WalletService::class)->creditTopUp($u, 40000, 'NGN', ['gateway' => 'paystack']);

        $b = app(WithdrawableBalanceResolver::class)->forUser($u->fresh());

        $credits = \App\Support\CreditSettings::creditsToUsd(1000);
        $this->assertSame(Money4::str(Money4::units($credits)), $b['credits']);
        $this->assertSame('12.5000', $b['referral']);
        $this->assertSame('7.0000', $b['merchant']);
        $this->assertSame('3.0000', $b['partner']);
        $this->assertSame('0.0000', $b['staff']);
        $this->assertSame(Money4::str(Money4::units($credits) + 125000 + 70000 + 30000), $b['total']);
    }

    public function test_batch_resolution_does_not_scale_queries_with_the_number_of_users(): void
    {
        $ids = collect(range(1, 25))->map(fn () => tap($this->user(), fn ($u) => $this->referral($u, 10))->id);
        $resolver = app(WithdrawableBalanceResolver::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $out = $resolver->forUsers($ids);
        $queries = count(DB::getQueryLog());

        $this->assertCount(25, $out);
        $this->assertLessThanOrEqual(8, $queries, "forUsers must use a fixed number of queries, used {$queries}");
        $this->assertSame('10.0000', $out[$ids->first()]['referral']);
    }

    public function test_next_sweep_eligibility_names_the_reason(): void
    {
        $e = app(PayoutEligibility::class);
        $u = $this->user();
        $enr = PayoutRailEnrollment::create(['user_id' => $u->id, 'provider' => 'payoneer', 'country' => 'KE', 'status' => 'active']);

        $this->assertSame('manual_mode', $e->nextSweep($u, 50, $enr)['reason']);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        $this->assertSame('below_minimum', $e->nextSweep($u, 1, $enr)['reason']);
        $this->assertSame('no_verified_account', $e->nextSweep($u, 50, $enr)['reason']);
        $this->account($u);
        $this->assertTrue($e->nextSweep($u, 50, $enr)['eligible']);

        $enr->forceFill(['status' => 'paused']);
        $this->assertSame('enrollment_paused', $e->nextSweep($u, 50, $enr)['reason']);
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, 'KE');
        $enr->forceFill(['status' => 'active']);
        $this->assertSame('country_denied', $e->nextSweep($u, 50, $enr)['reason']);
    }

    // ---- R3 radar maths ----

    public function test_exposure_committed_in_flight_and_due_next_sweep(): void
    {
        $a = $this->enrolled(100);
        $b = $this->enrolled(40);
        $this->enrolled(2);                                  // below the minimum: exposed, not swept
        Setting::setValue(PayoutSettings::MODE, 'auto');

        PayoutRequest::create(['user_id' => $a->id, 'amount' => 30, 'usd_amount' => 30, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'pending', 'provider' => 'payoneer', 'reference' => 'c:1']);
        PayoutRequest::create(['user_id' => $a->id, 'amount' => 20, 'usd_amount' => 20, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'awaiting_funds', 'provider' => 'payoneer', 'reference' => 'c:2']);
        PayoutRequest::create(['user_id' => $b->id, 'amount' => 10, 'usd_amount' => 10, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'processing', 'provider' => 'payoneer', 'reference' => 'i:1']);

        $r = app(FundingRadar::class)->compute('payoneer');

        $this->assertSame('142.0000', $r['max_exposure']);
        $this->assertSame('50.0000', $r['committed_unsent']);
        $this->assertSame('10.0000', $r['in_flight']);
        $this->assertSame('140.0000', $r['due_next_sweep'], '100 + 40 swept; the 2 is under the minimum');
        $this->assertSame(['selected' => 0, 'onboarding' => 0, 'active' => 3], $r['users']);
        $this->assertContains('max_exposure', $r['accuracy']['exact']);
        $this->assertContains('forecast_7d_p50', $r['accuracy']['estimate']);
    }

    public function test_manual_mode_sweeps_nothing(): void
    {
        $this->enrolled(100);
        $r = app(FundingRadar::class)->compute('payoneer');

        $this->assertSame('0.0000', $r['due_next_sweep']);
        $this->assertSame('100.0000', $r['max_exposure']);
    }

    public function test_low_confidence_forecast_uses_the_default_ratio_and_the_spike_factor(): void
    {
        $this->enrolled(100);                                  // manual: S = 0, R = 100
        $r = app(FundingRadar::class)->compute('payoneer');

        $this->assertTrue($r['low_confidence']);
        $this->assertSame(0, $r['history_weeks']);
        $this->assertSame('25.0000', $r['forecast_7d_p50'], '100 x default 25%');
        $this->assertSame('40.0000', $r['forecast_7d_p90'], 'p50 x 1.6');
        $this->assertSame('25.0000', $r['recommended_topup_p50'], 'float unknown -> before any recorded float');
        $this->assertTrue($r['float_unknown']);
    }

    public function test_four_or_more_weeks_of_history_replaces_the_default_with_the_measured_ratio(): void
    {
        $u = $this->enrolled(100);                             // exposure stays 100
        foreach ([4, 3, 2, 1] as $w) {
            $start = now()->subWeeks($w);
            PayoutExposureSnapshot::create(['captured_at' => $start->copy()->addHour(), 'provider' => 'payoneer', 'country' => null, 'max_exposure_usd' => 100]);
            $req = PayoutRequest::create(['user_id' => $u->id, 'amount' => 10, 'usd_amount' => 10, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'paid', 'provider' => 'payoneer', 'reference' => "h:{$w}"]);
            $req->forceFill(['created_at' => $start->copy()->addDays(2)])->saveQuietly();
        }

        $r = app(FundingRadar::class)->compute('payoneer');

        $this->assertFalse($r['low_confidence']);
        $this->assertSame(4, $r['history_weeks']);
        $this->assertEquals(0.1, $r['weekly_ratio'], 'every week withdrew 10% of the 100 exposure');
        $this->assertSame('10.0000', $r['forecast_7d_p50']);
    }

    public function test_recommended_topup_subtracts_recorded_float_and_flags_a_stall(): void
    {
        $this->enrolled(100);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        app(FloatService::class)->track('payoneer', 'USD', 60);

        $r = app(FundingRadar::class)->compute('payoneer');

        // C 0 + S 100 + F(0, nothing remaining) = 100, minus float 60
        $this->assertSame('40.0000', $r['recommended_topup_p50']);
        $this->assertSame('60.0000', $r['float_available']);
        $this->assertTrue($r['will_stall'], 'float 60 < due-at-sweep 100');
        $this->assertFalse($r['float_unknown']);
    }

    public function test_the_fx_buffer_applies_only_to_the_non_usd_share(): void
    {
        $this->enrolled(100, 'KE', 'USD');
        $this->enrolled(100, 'GB', 'GBP');
        Setting::setValue(PayoutSettings::MODE, 'auto');

        $r = app(FundingRadar::class)->compute('payoneer');

        // need = 200 x (1 + 3% x 50% non-USD share) = 203.0
        $this->assertSame('203.0000', $r['recommended_topup_p50']);
        $this->assertArrayHasKey('GBP', $r['by_currency']);
    }

    public function test_a_rail_that_debits_float_later_adds_in_flight_to_the_requirement(): void
    {
        config(['payouts.debits_float_at_submit.payoneer' => false]);
        $u = $this->enrolled(0.0 + 100);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        PayoutRequest::create(['user_id' => $u->id, 'amount' => 25, 'usd_amount' => 25, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'processing', 'provider' => 'payoneer', 'reference' => 'i:9']);

        $this->assertSame('125.0000', app(FundingRadar::class)->compute('payoneer')['recommended_topup_p50']);
    }

    public function test_paused_users_are_exposed_but_not_swept_and_declined_users_are_ignored(): void
    {
        $paused = $this->enrolled(50);
        $declined = $this->enrolled(70);
        PayoutRailEnrollment::where('user_id', $paused->id)->update(['status' => 'paused']);
        PayoutRailEnrollment::where('user_id', $declined->id)->update(['status' => 'declined']);
        Setting::setValue(PayoutSettings::MODE, 'auto');

        $r = app(FundingRadar::class)->compute('payoneer');

        $this->assertSame('50.0000', $r['max_exposure']);
        $this->assertSame('0.0000', $r['due_next_sweep']);
    }

    public function test_country_scoped_compute_only_counts_that_country(): void
    {
        $this->enrolled(100, 'KE');
        $this->enrolled(30, 'GH');

        $this->assertSame('100.0000', app(FundingRadar::class)->compute('payoneer', 'ke')['max_exposure']);
        $this->assertSame('130.0000', app(FundingRadar::class)->compute('payoneer')['max_exposure']);
    }

    // ---- snapshots, cache, stats ----

    public function test_snapshot_writes_append_only_rows_per_provider_and_country_and_refreshes_the_cache(): void
    {
        $this->enrolled(100, 'KE');
        $this->enrolled(30, 'GH');
        $snap = app(RadarSnapshotter::class);

        $rows = $snap->snapshot();
        $snap->snapshot(now()->addMinutes(5));

        $this->assertGreaterThanOrEqual(3, $rows);
        $this->assertSame(1, PayoutExposureSnapshot::where('provider', 'payoneer')->whereNull('country')->where('captured_at', now())->count());
        $this->assertSame(2, PayoutExposureSnapshot::where('provider', 'payoneer')->whereNull('country')->count(), 'append-only: a second run adds, never edits');
        $this->assertEquals(130.0, (float) PayoutExposureSnapshot::where('provider', 'payoneer')->whereNull('country')->first()->max_exposure_usd);
        $this->assertNotNull(Cache::get('payout:radar:payoneer'));

        $this->expectException(\LogicException::class);
        PayoutExposureSnapshot::first()->update(['max_exposure_usd' => 1]);
    }

    public function test_a_bump_event_moves_the_live_numbers_and_the_next_snapshot_corrects_any_drift(): void
    {
        $u = $this->enrolled(100);
        $radar = app(FundingRadar::class);
        $radar->refreshCache('payoneer');

        Queue::fake();
        $req = PayoutRequest::create(['user_id' => $u->id, 'amount' => 40, 'usd_amount' => 40, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'pending', 'provider' => 'payoneer', 'reference' => 'b:1']);
        \App\Events\PayoutRequested::dispatch($req);
        Queue::assertPushed(RadarBumpJob::class, fn ($j) => $j->provider === 'payoneer');

        // Stale cache until the job runs; running it moves the number...
        $this->assertSame('0.0000', Cache::get('payout:radar:payoneer')['committed_unsent']);
        (new RadarBumpJob('payoneer'))->handle($radar);
        $this->assertSame('40.0000', Cache::get('payout:radar:payoneer')['committed_unsent']);

        // ...and a poisoned cache value is overwritten by the snapshot (source of truth).
        Cache::put('payout:radar:payoneer', ['committed_unsent' => '999.0000'] + $radar->compute('payoneer'), 120);
        app(RadarSnapshotter::class)->snapshot();
        $this->assertSame('40.0000', Cache::get('payout:radar:payoneer')['committed_unsent']);

        // A non-global provider never bumps the radar.
        Queue::fake();
        \App\Events\PayoutRequested::dispatch(new PayoutRequest(['provider' => 'paystack']));
        Queue::assertNotPushed(RadarBumpJob::class);
    }

    public function test_hourly_stats_are_idempotent_and_a_late_webhook_corrects_the_bucket(): void
    {
        $u = $this->enrolled(100);
        $acct = PayoutAccount::where('user_id', $u->id)->first();
        $r = PayoutRequest::create(['user_id' => $u->id, 'payout_account_id' => $acct->id, 'amount' => 40, 'usd_amount' => 40, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'processing', 'provider' => 'payoneer', 'reference' => 's:1']);
        $snap = app(RadarSnapshotter::class);

        $snap->statsHourly();
        $snap->statsHourly();
        $row = PayoutWithdrawalStatHourly::first();
        $this->assertSame(1, PayoutWithdrawalStatHourly::count());
        $this->assertSame([1, 0, 'KE'], [$row->requests_count, $row->paid_count, $row->country]);

        $r->forceFill(['status' => 'paid', 'settled_at' => now()->addMinutes(10)])->save();
        $snap->statsHourly();
        $row->refresh();
        $this->assertSame([1, 1], [$row->requests_count, $row->paid_count]);
        $this->assertEquals(40.0, (float) $row->paid_usd);
        $this->assertSame(600, $row->avg_time_to_paid_sec);
    }

    public function test_prune_keeps_one_row_per_hour_after_14_days_and_one_per_day_after_90(): void
    {
        $at = now()->subDays(20)->startOfHour();
        foreach ([0, 5, 10, 55] as $m) {
            PayoutExposureSnapshot::create(['captured_at' => $at->copy()->addMinutes($m), 'provider' => 'payoneer', 'country' => null]);
        }
        $recent = PayoutExposureSnapshot::create(['captured_at' => now()->subDay(), 'provider' => 'payoneer', 'country' => null]);
        $oldDay = now()->subDays(120)->startOfDay();
        foreach ([1, 6, 12] as $h) {
            PayoutExposureSnapshot::create(['captured_at' => $oldDay->copy()->addHours($h), 'provider' => 'payoneer', 'country' => null]);
        }

        $removed = app(RadarSnapshotter::class)->prune();

        $this->assertSame(3 + 2, $removed);
        $this->assertSame(1 + 1 + 1, PayoutExposureSnapshot::count());
        $this->assertNotNull(PayoutExposureSnapshot::find($recent->id), 'recent raw rows are untouched');
    }

    public function test_the_radar_never_mutates_money_tables(): void
    {
        $u = $this->enrolled(100);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        $before = ['ReferralEarningCount' => \App\Models\ReferralEarning::count(), 'requests' => PayoutRequest::count(), 'floats' => PayoutFloatBalance::count()];
        app(RadarSnapshotter::class)->snapshot();
        app(RadarSnapshotter::class)->statsHourly();

        $this->assertSame($before, ['ReferralEarningCount' => \App\Models\ReferralEarning::count(), 'requests' => PayoutRequest::count(), 'floats' => PayoutFloatBalance::count()]);
    }
}
