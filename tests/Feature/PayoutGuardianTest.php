<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Jobs\SendPayoutJob;
use App\Models\AuditLog;
use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutDecision;
use App\Models\PayoutRequest;
use App\Models\PayoutTrustProfile;
use App\Models\ReferralEarning;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Guardian\FundingChecker;
use App\Services\Payouts\Guardian\NameMatcher;
use App\Services\Payouts\Guardian\PayoutGuardian;
use App\Services\Payouts\PayoutQuoter;
use App\Services\Payouts\PayoutService;
use App\Services\Pricing\CurrencyService;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Addendum C A4/A5: every gate (G1–G9), the soft score and bands, breakers, shadow vs
 * acting mode, and the fail-safe rules. Evaluation runs synchronously here; the
 * queue is faked so nothing sends and the job does not pre-empt the test.
 */
class PayoutGuardianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
        config(['services.paystack.secret_key' => 'sk_test']);
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(CurrencyService::class)->flushNgnRate();
        $this->guardianJobsRunning();
    }

    /** The pre-automation baseline some tests describe: the Guardian only watches and records. */
    private function advisoryMode(): void
    {
        Setting::setValue(PayoutSettings::MODE, 'manual');
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, false);
        Setting::setValue(PayoutSettings::AUTO_SHADOW, true);
    }

    /** The Guardian fails closed unless its sweeper + metrics jobs have run recently (Addendum D-3.20). */
    private function guardianJobsRunning(): void
    {
        foreach (['payouts:guard-sweep', 'payouts:guard-metrics'] as $job) {
            \App\Models\JobHeartbeat::create(['job_name' => $job, 'started_at' => now(), 'finished_at' => now(), 'duration_ms' => 5, 'outcome' => 'success']);
        }
    }

    /** A clean, mature, low-value referral-earnings withdrawal that passes every gate. */
    private function scenario(array $o = []): PayoutRequest
    {
        $user = $o['user'] ?? User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG', 'created_at' => now()->subDays(60)]);
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => $o['number'] ?? (string) random_int(1000000000, 9999999999), 'account_name' => 'CHINEDU OKAFOR', 'provider' => $o['provider'] ?? 'paystack',
            'is_verified' => $o['verified'] ?? true, 'is_default' => true,
        ]);
        $account->forceFill(['created_at' => now()->subDays(10)])->save(); // past the cooling-off window

        $usd = $o['usd'] ?? 20.0;
        $earn = app(ReferralEarningsService::class);
        $referred = User::factory()->create();
        $row = $earn->accrue($user, $referred, 'esim', $usd + 30, 'seed:'.uniqid());
        ReferralEarning::whereKey($row->id)->update(['created_at' => now()->subDays(30)]); // mature earnings

        $ref = $o['ref'] ?? 'ref:'.uniqid();
        $q = app(PayoutQuoter::class)->quote($usd, 'NGN', $account);
        $earn->hold($user, $usd, 'earn-hold:'.$ref);
        $request = app(PayoutService::class)->createRequest($user, $q['local_amount'], 'NGN', 'referral_earnings', $account, $ref, $q['quote']);
        $request->forceFill(['credit_amount' => $usd])->save();

        return $request->refresh();
    }

    private function evaluate(PayoutRequest $r): ?PayoutDecision
    {
        return app(PayoutGuardian::class)->evaluate($r);
    }

    private function rule(PayoutDecision $d, string $id): array
    {
        return collect($d->rules)->firstWhere('id', $id) ?? [];
    }

    /** Switch the Guardian from advisory to acting for paystack. */
    private function goLive(): void
    {
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, true);
        Setting::setValue(PayoutSettings::AUTO_SHADOW, false);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        Setting::setValue('payouts.provider.paystack.auto_approve', true);
    }

    // ---- shadow vs acting ----

    public function test_shadow_mode_records_would_approve_and_changes_nothing(): void
    {
        $this->advisoryMode();
        $r = $this->scenario();
        $d = $this->evaluate($r);

        $this->assertSame('approve', $d->decision);
        $this->assertTrue($d->shadow);
        $r->refresh();
        $this->assertSame(PayoutRequest::PENDING, $r->status);
        $this->assertSame(PayoutRequest::REVIEW_MANUAL, $r->review_state);
        $this->assertSame('advisory_approve', $r->hold_reason);
        Queue::assertNotPushed(SendPayoutJob::class);
    }

    public function test_acting_mode_approves_by_system_and_queues_exactly_one_send(): void
    {
        $this->goLive();
        $r = $this->scenario();
        $d = $this->evaluate($r);

        $this->assertSame('approve', $d->decision);
        $this->assertFalse($d->shadow);
        $r->refresh();
        $this->assertSame(PayoutRequest::APPROVED, $r->status);
        $this->assertSame(PayoutRequest::APPROVAL_SYSTEM, $r->approval_source);
        $this->assertSame(PayoutRequest::REVIEW_APPROVED_AUTO, $r->review_state);
        Queue::assertPushed(SendPayoutJob::class, 1);
    }

    public function test_provider_switch_off_holds_instead_of_approving(): void
    {
        $this->goLive();
        Setting::setValue('payouts.provider.paystack.auto_approve', false);
        $d = $this->evaluate($this->scenario());

        $this->assertSame('hold', $d->decision);
        $this->assertSame('provider_auto_approve_off', $d->reason);
    }

    public function test_manual_mode_never_approves_even_with_everything_else_on(): void
    {
        $this->goLive();
        Setting::setValue(PayoutSettings::MODE, 'manual');
        $r = $this->scenario();
        $this->evaluate($r);

        $this->assertSame(PayoutRequest::PENDING, $r->refresh()->status);
        Queue::assertNotPushed(SendPayoutJob::class);
    }

    public function test_an_already_actioned_request_is_left_alone(): void
    {
        $this->goLive();
        $r = $this->scenario();
        $admin = User::factory()->create()->assignRole('admin');
        app(PayoutService::class)->approve($r, $admin);

        $this->assertNull($this->evaluate($r));
        $this->assertSame(PayoutRequest::APPROVAL_ADMIN, $r->refresh()->approval_source);
        Queue::assertPushed(SendPayoutJob::class, 1);
    }

    public function test_decisions_are_append_only_and_attempts_increment(): void
    {
        $this->advisoryMode();
        $r = $this->scenario();
        $a = $this->evaluate($r);
        $r->forceFill(['status' => PayoutRequest::PENDING])->save();
        $b = $this->evaluate($r);

        $this->assertSame([1, 2], [$a->attempt, $b->attempt]);
        $this->expectException(\LogicException::class);
        $a->update(['decision' => 'reject']);
    }

    // ---- G1 ----

    public function test_g1_payouts_disabled_holds(): void
    {
        $r = $this->scenario();
        Setting::setValue(PayoutSettings::FLAG, false);
        $d = $this->evaluate($r);

        $this->assertSame(['hold', 'payouts_disabled'], [$d->decision, $d->reason]);
    }

    public function test_g1_denied_country_holds(): void
    {
        $r = $this->scenario();
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, 'ru, NG');

        $this->assertSame('country_denied', $this->evaluate($r)->reason);
    }

    public function test_g1_disabled_corridor_holds_and_unavailable_provider_defers(): void
    {
        $r = $this->scenario();
        PayoutCorridor::whereKey($r->corridor_id)->update(['enabled' => false]);
        $this->assertSame(['hold', 'corridor_disabled'], [($d = $this->evaluate($r))->decision, $d->reason]);

        PayoutCorridor::whereKey($r->corridor_id)->update(['enabled' => true]);
        config(['services.paystack.secret_key' => null]);
        $d = $this->evaluate($r);
        $this->assertSame(['defer', 'provider_unavailable'], [$d->decision, $d->reason]);
        $this->assertNotNull($d->next_check_at);
    }

    // ---- G2 ----

    public function test_g2_unverified_account_holds(): void
    {
        $r = $this->scenario();
        $r->account->forceFill(['is_verified' => false])->save();

        $this->assertSame('account_unverified', $this->evaluate($r)->reason);
    }

    public function test_g2_account_belonging_to_someone_else_holds(): void
    {
        $r = $this->scenario();
        $r->account->forceFill(['user_id' => User::factory()->create()->id])->save();

        $this->assertSame('account_not_owned', $this->evaluate($r)->reason);
    }

    public function test_g2_rails_that_need_an_enrollment_cannot_be_auto_approved_yet(): void
    {
        $r = $this->scenario();
        $r->account->forceFill(['provider' => 'payoneer'])->save();
        $r->forceFill(['provider' => 'payoneer'])->save();

        $d = $this->evaluate($r);
        $this->assertSame('hold', $d->decision);
    }

    // ---- G3 (tampered holds) ----

    public function test_g3_missing_hold_holds_and_alerts(): void
    {
        $r = $this->scenario();
        ReferralEarning::where('reference', 'earn-hold:'.$r->reference)->delete();
        $d = $this->evaluate($r);

        $this->assertSame(['hold', 'hold_mismatch'], [$d->decision, $d->reason]);
        $this->assertSame('missing', $this->rule($d, 'G3_ledger_hold')['evidence']['status']);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_hold_mismatch');
    }

    public function test_g3_amount_mismatch_holds(): void
    {
        $r = $this->scenario();
        $r->forceFill(['credit_amount' => 500])->save(); // request claims far more than was held
        $d = $this->evaluate($r);

        $this->assertSame('hold_mismatch', $d->reason);
        $this->assertSame('mismatch', $this->rule($d, 'G3_ledger_hold')['evidence']['status']);
    }

    public function test_g3_a_released_hold_holds(): void
    {
        $r = $this->scenario();
        app(ReferralEarningsService::class)->release($r->user, 20, 'earn-release:'.$r->reference);
        $d = $this->evaluate($r);

        $this->assertSame('released', $this->rule($d, 'G3_ledger_hold')['evidence']['status']);
        $this->assertSame('hold', $d->decision);
    }

    public function test_g3_a_bucket_without_a_ledger_is_unverifiable_so_a_human_looks(): void
    {
        $r = $this->scenario();
        $r->forceFill(['source_bucket' => 'admin_core_margin'])->save();

        $this->assertSame('hold_unverifiable', $this->evaluate($r)->reason);
    }

    // ---- G4 ----

    public function test_g4_free_allowance_used_without_kyc_holds(): void
    {
        $user = User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG', 'created_at' => now()->subDays(60)]);
        $this->scenario(['user' => $user, 'number' => '1111111111']);
        $second = $this->scenario(['user' => $user, 'number' => '2222222222']);
        Setting::setValue(PayoutSettings::FREE_COUNT, 1); // allowance drops after both were filed (admission is the first line of defence)

        $this->assertSame('kyc_required', $this->evaluate($second)->reason);
    }

    public function test_g4_first_global_rail_payout_needs_kyc(): void
    {
        $r = $this->scenario();
        $r->forceFill(['provider' => 'paypal'])->save();
        $r->account->forceFill(['provider' => 'paypal'])->save();
        $d = $this->evaluate($r);

        $this->assertSame('hold', $d->decision);
        $this->assertContains($d->reason, ['kyc_required_global', 'corridor_disabled', 'provider_unavailable', 'provider_unknown']);
    }

    public function test_g4_a_hard_user_cap_rejects_and_returns_the_funds(): void
    {
        $this->goLive();
        Setting::setValue(PayoutSettings::USER_CAP_PREFIX.'daily', 10);
        $r = $this->scenario(['usd' => 20]);
        $d = $this->evaluate($r);

        $this->assertSame(['reject', 'cap_exceeded'], [$d->decision, $d->reason]);
        $r->refresh();
        $this->assertSame(PayoutRequest::REVERSED, $r->status);
        $this->assertSame(PayoutRequest::REVIEW_REJECTED_AUTO, $r->review_state);
        $this->assertStringNotContainsString('cap_exceeded', (string) $r->failure_reason, 'never a rule name to the user');
        // The hold is released by the PayoutReversed listener (sync).
        $this->assertTrue(ReferralEarning::where('reference', 'earn-release:'.$r->reference)->exists());
    }

    // ---- G5 ----

    public function test_g5_a_recent_password_change_defers_until_the_window_ends(): void
    {
        $r = $this->scenario();
        AuditLog::create(['user_id' => $r->user_id, 'action' => 'account.password_changed', 'payload' => []]);
        $d = $this->evaluate($r);

        $this->assertSame(['defer', 'cooling_off'], [$d->decision, $d->reason]);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $d->next_check_at->timestamp, 5);
    }

    public function test_g5_a_freshly_added_payout_account_defers(): void
    {
        $r = $this->scenario();
        $r->account->forceFill(['created_at' => now()->subHour()])->save();

        $this->assertSame('cooling_off', $this->evaluate($r)->reason);
    }

    public function test_g5_cooling_off_can_be_switched_off(): void
    {
        $r = $this->scenario();
        AuditLog::create(['user_id' => $r->user_id, 'action' => 'account.2fa_disabled', 'payload' => []]);
        Setting::setValue(PayoutSettings::COOLING_OFF_HOURS, 0);

        $this->assertSame('approve', $this->evaluate($r)->decision);
    }

    // ---- G6 ----

    public function test_g6_one_destination_used_by_two_users_holds(): void
    {
        $this->scenario(['number' => '5550001111']);                  // user A saves it
        $b = $this->scenario(['number' => '5550001111']);             // user B saves the SAME destination

        $this->assertSame(['hold', 'destination_shared'], [($d = $this->evaluate($b))->decision, $d->reason]);
    }

    public function test_g6_the_same_user_reusing_their_destination_is_fine(): void
    {
        $user = User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG', 'created_at' => now()->subDays(60)]);
        $this->scenario(['user' => $user, 'number' => '5550002222']);
        $again = $this->scenario(['user' => $user, 'number' => '5550002222']);

        $this->assertNotSame('destination_shared', $this->evaluate($again)->reason);
    }

    // ---- G7 ----

    public function test_g7_provider_resolved_names_are_skipped_and_recorded(): void
    {
        $d = $this->evaluate($this->scenario());
        $this->assertSame('skipped_provider_resolved', $this->rule($d, 'G7_name_match')['evidence']['skipped']);
    }

    public function test_g7_a_name_mismatch_holds_when_the_provider_did_not_resolve_it(): void
    {
        $r = $this->scenario();
        $r->account->forceFill(['payee_kyc_name' => 'Totally Different Person'])->save();
        $d = $this->evaluate($r);

        $this->assertSame(['hold', 'name_mismatch'], [$d->decision, $d->reason]);
    }

    public function test_g7_matching_names_pass_regardless_of_order_case_and_accents(): void
    {
        $r = $this->scenario();
        $r->account->forceFill(['payee_kyc_name' => 'OKAFOR, Chinèdu'])->save();

        $d = $this->evaluate($r);
        $this->assertSame('pass', $this->rule($d, 'G7_name_match')['result']);
    }

    public function test_name_matcher_unit(): void
    {
        $this->assertGreaterThanOrEqual(0.85, NameMatcher::score('Chinedu Okafor', 'okafor chinedu'));
        $this->assertGreaterThanOrEqual(0.85, NameMatcher::score('Chinedu Okafor', 'Chinedu Emeka Okafor'));
        $this->assertLessThan(0.5, NameMatcher::score('Chinedu Okafor', 'Amina Bello'));
        $this->assertSame(0.0, NameMatcher::score('', 'Amina Bello'));
        $this->assertFalse(NameMatcher::looksLikeName('jane@example.com'));
        $this->assertFalse(NameMatcher::looksLikeName('acct_1234567'));
        $this->assertTrue(NameMatcher::looksLikeName('Jane Doe'));
    }

    // ---- G8 ----

    public function test_g8_an_unexpired_quote_passes(): void
    {
        $d = $this->evaluate($this->scenario());
        $this->assertSame('pass', $this->rule($d, 'G8_fx_quote')['result']);
    }

    public function test_g8_expired_quote_within_tolerance_warns_and_still_approves(): void
    {
        $r = $this->scenario();
        $r->forceFill(['quote_expires_at' => now()->subHour(), 'fx_rate' => 1500 / 1.02])->save(); // 2% drift
        $d = $this->evaluate($r);

        $this->assertSame('warn', $this->rule($d, 'G8_fx_quote')['result']);
        $this->assertSame('approve', $d->decision);
    }

    public function test_g8_expired_quote_beyond_tolerance_holds_for_a_human(): void
    {
        $r = $this->scenario();
        $r->forceFill(['quote_expires_at' => now()->subHour(), 'fx_rate' => 1500 / 1.10])->save(); // 10% drift
        $d = $this->evaluate($r);

        $this->assertSame(['hold', 'fx_drift'], [$d->decision, $d->reason]);
    }

    // ---- G9 ----

    public function test_g9_a_short_float_defers(): void
    {
        $this->app->bind(FundingChecker::class, fn () => new class implements FundingChecker
        {
            public function check(PayoutRequest $request): array
            {
                return ['status' => 'short', 'evidence' => ['float' => 0]];
            }
        });
        $d = $this->evaluate($this->scenario());

        $this->assertSame(['defer', 'float_short'], [$d->decision, $d->reason]);
    }

    // ---- score + bands ----

    public function test_a_brand_new_user_on_a_first_withdrawal_is_scored_but_small_amounts_still_approve(): void
    {
        $user = User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG']); // created now
        $d = $this->evaluate($this->scenario(['user' => $user, 'usd' => 20]));

        $this->assertGreaterThan(0, $d->score);
        $this->assertContains('S_new_account', array_column($d->rules, 'id'));
        $this->assertSame('approve', $d->decision);
    }

    public function test_amount_above_the_tier_limit_holds(): void
    {
        $d = $this->evaluate($this->scenario(['usd' => 150])); // new tier limit is 100

        $this->assertSame(['hold', 'over_tier_limit'], [$d->decision, $d->reason]);
    }

    public function test_a_trusted_tier_lifts_the_limit_and_lowers_the_score(): void
    {
        $user = User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG', 'created_at' => now()->subDays(60)]);
        PayoutTrustProfile::create(['user_id' => $user->id, 'tier' => 'trusted', 'clean_payouts' => 5, 'updated_at' => now()]);
        $d = $this->evaluate($this->scenario(['user' => $user, 'usd' => 150]));

        $this->assertSame('approve', $d->decision);
        $this->assertContains('S_trust_tier', array_column($d->rules, 'id'));
    }

    public function test_a_high_score_holds_even_for_a_small_amount(): void
    {
        Setting::setValue(PayoutSettings::BAND_HOLD, 40);
        Setting::setValue(PayoutSettings::BAND_APPROVE, 20);
        $user = User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG']); // new account: +15, first withdrawal: +10, then more
        PayoutTrustProfile::create(['user_id' => $user->id, 'tier' => 'new', 'clean_payouts' => 0, 'last_incident_at' => now()->subDays(5), 'updated_at' => now()]);
        $d = $this->evaluate($this->scenario(['user' => $user]));

        $this->assertGreaterThanOrEqual(40, $d->score);
        $this->assertSame(['hold', 'risk_high'], [$d->decision, $d->reason]);
    }

    public function test_the_unestablishable_usd_value_always_holds(): void
    {
        $r = $this->scenario();
        $r->forceFill(['usd_amount' => null, 'credit_amount' => null])->save();
        // Without a hold amount G3 fails first; either way it is never approved.
        $this->assertNotSame('approve', $this->evaluate($r)->decision);
    }

    // ---- breakers + QA ----

    public function test_daily_cap_breaker_stops_further_auto_approvals_and_alerts_once(): void
    {
        $this->goLive();
        Setting::setValue(PayoutSettings::DAILY_CAP_USD, 30);

        $first = $this->evaluate($this->scenario(['usd' => 20]));
        $second = $this->evaluate($this->scenario(['usd' => 20]));
        $third = $this->evaluate($this->scenario(['usd' => 20]));

        $this->assertSame('approve', $first->decision);
        $this->assertSame(['hold', 'breaker_daily_cap'], [$second->decision, $second->reason]);
        $this->assertSame('hold', $third->decision);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'guardian_breaker_tripped');
        $alerts = Queue::pushed(AlertAdminJob::class)->filter(fn ($j) => $j->code === 'guardian_breaker_tripped');
        $this->assertCount(1, $alerts, 'one alert per hour, not per request');
    }

    public function test_hourly_rate_breaker_trips_when_volume_spikes(): void
    {
        $this->goLive();
        Setting::setValue(PayoutSettings::BREAKER_HOURLY_FLOOR, 2);
        Setting::setValue(PayoutSettings::DAILY_CAP_USD, 100000);

        $decisions = collect(range(1, 4))->map(fn () => $this->evaluate($this->scenario(['usd' => 6]))->decision);

        $this->assertSame(['approve', 'approve', 'hold', 'hold'], $decisions->all());
    }

    public function test_qa_sampling_flags_a_share_of_auto_approvals(): void
    {
        $this->goLive();
        Setting::setValue(PayoutSettings::QA_SAMPLE_PCT, 100);
        $d = $this->evaluate($this->scenario());

        $this->assertTrue($d->qa_sampled);
        Setting::setValue(PayoutSettings::QA_SAMPLE_PCT, 0);
        $this->assertFalse($this->evaluate($this->scenario())->qa_sampled);
    }

    // ---- deferral ----

    public function test_a_deferred_request_is_parked_with_a_next_check_time_in_acting_mode(): void
    {
        $this->goLive();
        $r = $this->scenario();
        $r->account->forceFill(['created_at' => now()->subHour()])->save();
        $this->evaluate($r);

        $r->refresh();
        $this->assertSame(PayoutRequest::REVIEW_DEFERRED, $r->review_state);
        $this->assertSame('cooling_off', $r->hold_reason);
        $this->assertTrue($r->next_check_at->isFuture());
        $this->assertSame(PayoutRequest::PENDING, $r->status);
    }

    // ---- A5: sweeper, job fail-safe, cron jobs ----

    public function test_sweeper_claims_each_due_request_once_even_with_two_sweepers(): void
    {
        $r = $this->scenario();
        $r->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $a = app(\App\Services\Payouts\Guardian\GuardianSweeper::class)->sweep();
        $b = app(\App\Services\Payouts\Guardian\GuardianSweeper::class)->sweep();

        $this->assertSame([1, 0], [$a, $b], 'the second sweeper finds it already claimed');
        Queue::assertPushed(\App\Jobs\EvaluatePayoutRequestJob::class, fn ($j) => $j->payoutRequestId === $r->id);
        $this->assertNotNull($r->refresh()->evaluating_at);
    }

    public function test_sweeper_ignores_fresh_manual_review_and_not_yet_due_deferred_requests(): void
    {
        $fresh = $this->scenario();                                                         // just created: its own job has first go
        $manual = $this->scenario();
        $manual->forceFill(['review_state' => PayoutRequest::REVIEW_MANUAL, 'created_at' => now()->subHour()])->save();
        $notDue = $this->scenario();
        $notDue->forceFill(['review_state' => PayoutRequest::REVIEW_DEFERRED, 'next_check_at' => now()->addHour()])->save();
        $due = $this->scenario();
        $due->forceFill(['review_state' => PayoutRequest::REVIEW_DEFERRED, 'next_check_at' => now()->subMinute()])->save();

        $this->assertSame(1, app(\App\Services\Payouts\Guardian\GuardianSweeper::class)->sweep());
        Queue::assertPushed(\App\Jobs\EvaluatePayoutRequestJob::class, fn ($j) => $j->payoutRequestId === $due->id);
    }

    public function test_an_abandoned_claim_is_retaken_after_the_ttl(): void
    {
        $r = $this->scenario();
        $r->forceFill(['created_at' => now()->subHour(), 'evaluating_at' => now()->subMinutes(30)])->save();

        $this->assertSame(1, app(\App\Services\Payouts\Guardian\GuardianSweeper::class)->sweep());
    }

    public function test_float_retry_only_touches_float_deferrals(): void
    {
        $float = $this->scenario();
        $float->forceFill(['review_state' => PayoutRequest::REVIEW_DEFERRED, 'hold_reason' => 'float_short', 'next_check_at' => now()->addHour()])->save();
        $cooling = $this->scenario();
        $cooling->forceFill(['review_state' => PayoutRequest::REVIEW_DEFERRED, 'hold_reason' => 'cooling_off', 'next_check_at' => now()->addHour()])->save();

        $this->artisan('payouts:float-retry')->expectsOutputToContain('claimed=1')->assertSuccessful();

        // (The fake queue never releases the unique-job lock taken at creation, so assert on the claim.)
        $this->assertNotNull($float->refresh()->evaluating_at, 'the float deferral was claimed for retry');
        $this->assertNull($cooling->refresh()->evaluating_at, 'cooling-off is not a float retry');
    }

    public function test_guardian_error_fails_safe_to_manual_review_never_approval(): void
    {
        $this->goLive();
        $r = $this->scenario();
        $job = new \App\Jobs\EvaluatePayoutRequestJob($r->id);

        $job->failed(new \RuntimeException('boom'));

        $r->refresh();
        $this->assertSame(PayoutRequest::PENDING, $r->status);
        $this->assertSame(PayoutRequest::REVIEW_MANUAL, $r->review_state);
        $this->assertSame('guardian_error', $r->hold_reason);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'guardian_error');
        Queue::assertNotPushed(SendPayoutJob::class);
    }

    public function test_a_throwing_gate_never_leads_to_an_approval(): void
    {
        $this->goLive();
        $r = $this->scenario();
        $this->app->bind(\App\Services\Payouts\Guardian\HoldVerifier::class, fn () => new class implements \App\Services\Payouts\Guardian\HoldVerifier
        {
            public function verify(PayoutRequest $request): \App\Services\Payouts\Guardian\HoldCheck
            {
                throw new \RuntimeException('ledger unreachable');
            }
        });

        try {
            $this->evaluate($r);
            $this->fail('the exception must propagate to the job, which parks the request');
        } catch (\RuntimeException) {
            $this->assertSame(PayoutRequest::PENDING, $r->refresh()->status);
            Queue::assertNotPushed(SendPayoutJob::class);
        }
    }

    public function test_trust_recompute_promotes_after_clean_payouts_and_knocks_back_on_incident(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 3) as $i) {
            PayoutRequest::create(['user_id' => $user->id, 'amount' => 10, 'currency' => 'USD', 'source_bucket' => 'referral_earnings',
                'status' => PayoutRequest::PAID, 'provider' => 'paystack', 'reference' => "p:{$i}"]);
        }
        app(\App\Services\Payouts\Guardian\TrustRecomputer::class)->run();
        $this->assertSame('trusted', PayoutTrustProfile::find($user->id)->tier);

        PayoutRequest::create(['user_id' => $user->id, 'amount' => 10, 'currency' => 'USD', 'source_bucket' => 'referral_earnings',
            'status' => PayoutRequest::REVERSED, 'provider' => 'paystack', 'reference' => 'p:bad']);
        app(\App\Services\Payouts\Guardian\TrustRecomputer::class)->run();
        $this->assertSame('new', PayoutTrustProfile::find($user->id)->tier);
    }

    public function test_trust_recompute_never_overrides_a_human_decision(): void
    {
        $user = User::factory()->create();
        PayoutRequest::create(['user_id' => $user->id, 'amount' => 10, 'currency' => 'USD', 'source_bucket' => 'referral_earnings',
            'status' => PayoutRequest::PAID, 'provider' => 'paystack', 'reference' => 'p:1']);
        $admin = User::factory()->create();
        PayoutTrustProfile::create(['user_id' => $user->id, 'tier' => 'vip', 'override_by' => $admin->id, 'override_reason' => 'known partner', 'updated_at' => now()]);

        app(\App\Services\Payouts\Guardian\TrustRecomputer::class)->run();

        $this->assertSame('vip', PayoutTrustProfile::find($user->id)->tier);
    }

    public function test_metrics_and_digest(): void
    {
        $this->advisoryMode();
        $this->evaluate($this->scenario());
        $m = app(\App\Services\Payouts\Guardian\GuardianMetrics::class)->roll();

        $this->assertSame(['approve' => 1], $m['advisory_24h']);
        $this->assertSame(1, $m['manual_review']);

        $this->artisan('payouts:guard-digest')->assertSuccessful();
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'guardian_digest');
    }

    public function test_an_idle_digest_says_nothing(): void
    {
        $this->artisan('payouts:guard-digest')->assertSuccessful();
        Queue::assertNotPushed(AlertAdminJob::class);
    }

    public function test_stuck_watchdog_alerts_on_unverifiable_payouts_and_never_reverses_them(): void
    {
        $r = $this->scenario();
        $r->forceFill(['status' => PayoutRequest::PROCESSING, 'provider' => 'paystack', 'updated_at' => now()->subHours(5)])->saveQuietly();
        PayoutRequest::whereKey($r->id)->update(['updated_at' => now()->subHours(5)]);
        \Illuminate\Support\Facades\Http::fake(['api.paystack.co/*' => \Illuminate\Support\Facades\Http::response('boom', 500)]);

        $s = app(\App\Services\Payouts\PayoutStuckWatchdog::class)->run();

        $this->assertSame(1, $s['alerted']);
        $this->assertSame(PayoutRequest::PROCESSING, $r->refresh()->status, 'no blind refund');
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_stuck');

        app(\App\Services\Payouts\PayoutStuckWatchdog::class)->run();
        $this->assertCount(1, Queue::pushed(AlertAdminJob::class)->filter(fn ($j) => $j->code === 'payout_stuck'), 'alert is not repeated every tick');
    }

    public function test_the_guardian_fails_closed_when_its_own_jobs_have_stalled(): void
    {
        \App\Models\JobHeartbeat::where('job_name', 'payouts:guard-sweep')->update(['finished_at' => now()->subMinutes(10)]);

        $trip = app(\App\Services\Payouts\Guardian\GuardianBreakers::class)->tripped(5.0);

        $this->assertSame('breaker_stale_heartbeat', $trip['code']);

        Setting::setValue(PayoutSettings::FAIL_CLOSED_HEARTBEAT, false);
        $this->assertNull(app(\App\Services\Payouts\Guardian\GuardianBreakers::class)->tripped(5.0));
    }

    public function test_an_unhealthy_provider_defers_and_never_redirects_the_request(): void
    {
        $r = $this->scenario();
        \Illuminate\Support\Facades\Cache::put(\App\Services\Payouts\Rail\RadarAlerts::UNHEALTHY_KEY.'paystack', true, 600);

        $d = $this->evaluate($r);

        $this->assertSame(['defer', 'provider_unhealthy'], [$d->decision, $d->reason]);
        $this->assertSame('paystack', $r->fresh()->provider, 'the destination stays bound to its rail');
        $deferred = new PayoutRequest(['status' => 'pending', 'review_state' => 'deferred', 'hold_reason' => 'provider_unhealthy']);
        $this->assertSame('queued', \App\Support\PayoutStatusText::key($deferred), 'the user sees "queued, no action needed"');
    }

    public function test_a_long_outage_raises_one_alert_an_hour(): void
    {
        $r = $this->scenario();
        $r->forceFill(['created_at' => now()->subHours(3)])->save();
        \Illuminate\Support\Facades\Cache::put(\App\Services\Payouts\Rail\RadarAlerts::UNHEALTHY_KEY.'paystack', true, 600);

        $this->evaluate($r);
        $this->evaluate($r->fresh());

        $this->assertCount(1, Queue::pushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'payout_provider_outage'));
    }

    public function test_a_frozen_payee_is_held_not_approved(): void
    {
        $r = $this->scenario();
        \App\Models\PayoutUserFreeze::create(['user_id' => $r->user_id, 'reason' => 'not_me', 'frozen_at' => now()]);

        $d = $this->evaluate($r);

        $this->assertSame(['hold', 'user_frozen'], [$d->decision, $d->reason]);
    }
}
