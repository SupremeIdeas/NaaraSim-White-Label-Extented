<?php

namespace Tests\Feature;

use App\Models\JobHeartbeat;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\ReferralEarning;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Hardening\AutomationStatus;
use App\Services\Payouts\PayoutQuoter;
use App\Services\Payouts\PayoutService;
use App\Services\Pricing\CurrencyService;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The owner's rule: with the DEFAULT settings (every optional extra OFF — step-up, manual rail, tax gate, dual
 * control, float tracking) a Paystack payout must run end to end with no human: request -> Guardian approves ->
 * transfer sent -> webhook confirms. Only payouts switched on, a configured Paystack key and the scheduler running.
 */
class PayoutAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_x', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(CurrencyService::class)->flushNgnRate();
        Setting::setValue(PayoutSettings::FLAG, true);          // the ONLY thing the owner switches on
        foreach (['payouts:guard-sweep', 'payouts:guard-metrics'] as $job) {
            JobHeartbeat::create(['job_name' => $job, 'started_at' => now(), 'finished_at' => now(), 'duration_ms' => 5, 'outcome' => 'success']);
        }
    }

    /** A clean, mature, low-value earnings withdrawal to a verified Paystack account. */
    private function withdraw(float $usd = 20.0): PayoutRequest
    {
        $user = User::factory()->create(['name' => 'Chinedu Okafor', 'country_code' => 'NG', 'created_at' => now()->subDays(60)]);
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => (string) random_int(1000000000, 9999999999), 'account_name' => 'CHINEDU OKAFOR', 'provider' => 'paystack',
            'is_verified' => true, 'is_default' => true, 'provider_recipient_ref' => 'RCP_1',
        ]);
        $account->forceFill(['created_at' => now()->subDays(10)])->save();   // past the cooling-off window

        $earn = app(ReferralEarningsService::class);
        $row = $earn->accrue($user, User::factory()->create(), 'esim', $usd + 30, 'seed:'.uniqid());
        ReferralEarning::whereKey($row->id)->update(['created_at' => now()->subDays(30)]);   // matured

        $ref = 'ref:'.uniqid();
        $q = app(PayoutQuoter::class)->quote($usd, 'NGN', $account);
        $earn->hold($user, $usd, 'earn-hold:'.$ref);
        // Exactly like the real withdrawal services: hold + request + held amount in ONE transaction; the Guardian
        // (queued afterCommit) only sees it once everything is written.
        $r = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $q, $account, $ref, $usd) {
            $r = app(PayoutService::class)->createRequest($user, $q['local_amount'], 'NGN', 'referral_earnings', $account, $ref, $q['quote']);
            $r->forceFill(['credit_amount' => $usd])->save();

            return $r;
        });

        return $r->fresh();
    }

    private function signedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/payouts/paystack', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_x'), 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_a_paystack_payout_runs_start_to_finish_on_default_settings_with_no_human(): void
    {
        Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'pending', 'transfer_code' => 'TRF_77']], 200)]);

        $r = $this->withdraw(20.0);                // the queue is synchronous in tests: Guardian -> approve -> send

        $r->refresh();
        $this->assertSame(PayoutRequest::PROCESSING, $r->status, 'sent by the system, not parked for an admin');
        $this->assertSame('system', $r->approval_source);
        $this->assertNull($r->approved_by, 'no person approved it');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/transfer') && $req['reference'] === $r->provider_reference);

        $this->signedWebhook(['event' => 'transfer.success', 'data' => ['reference' => $r->provider_reference, 'transfer_code' => 'TRF_77']])->assertOk();
        $this->assertSame(PayoutRequest::PAID, $r->fresh()->status);
    }

    public function test_none_of_the_optional_extras_stand_in_the_way(): void
    {
        $this->assertFalse(PayoutSettings::stepUpRequired());
        $this->assertFalse(PayoutSettings::manualExternalEnabled());
        $this->assertSame(0.0, PayoutSettings::dualControlUsd());
        $this->assertSame(0.0, PayoutSettings::taxFormOverUsd());
        $this->assertSame(0, PayoutSettings::sendDelayMinutes(false), 'no send delay on a local rail');
        Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'pending', 'transfer_code' => 'T']], 200)]);

        $this->assertSame(PayoutRequest::PROCESSING, $this->withdraw(15.0)->status);
        $this->assertSame(0, \App\Models\PayoutFloatBalance::count(), 'float tracking is off, and that is fine');
    }

    public function test_the_status_page_says_automatic_and_names_what_is_missing_when_it_is_not(): void
    {
        $ok = app(AutomationStatus::class)->report();
        $this->assertTrue($ok['automatic']);
        $this->assertContains('paystack', $ok['rails']);

        Setting::setValue(PayoutSettings::AUTO_SHADOW, true);
        $r = app(AutomationStatus::class)->report();
        $this->assertFalse($r['automatic']);
        $row = collect($r['checks'])->firstWhere('label', 'Learning (shadow) mode is off');
        $this->assertFalse($row['ok']);
        $this->assertStringContainsString('Learning (shadow) mode', $row['fix']);
    }

    public function test_a_stalled_scheduler_is_called_out_and_pauses_automation_instead_of_failing_silently(): void
    {
        JobHeartbeat::where('job_name', 'payouts:guard-sweep')->update(['finished_at' => now()->subHour()]);
        Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'pending']], 200)]);

        $report = app(AutomationStatus::class)->report();
        $this->assertFalse($report['automatic']);
        $this->assertStringContainsString('schedule:run', collect($report['checks'])->firstWhere('label', 'The Guardian sweeper is running')['fix']);

        $r = $this->withdraw(20.0);
        $this->assertNotSame(PayoutRequest::PROCESSING, $r->status, 'not sent while the Guardian cannot be trusted');
        Http::assertNothingSent();
    }

    public function test_large_or_unusual_requests_still_go_to_a_person_automation_is_not_a_blank_cheque(): void
    {
        Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'pending']], 200)]);

        $r = $this->withdraw(400.0);       // above the $100 new-payee limit

        $this->assertSame(PayoutRequest::PENDING, $r->status);
        $this->assertSame(PayoutRequest::REVIEW_MANUAL, $r->review_state);
        Http::assertNothingSent();
    }

    public function test_a_rail_that_is_not_in_the_tested_set_stays_manual_until_the_admin_turns_it_on(): void
    {
        $this->assertTrue(PayoutSettings::providerAutoApprove('paystack'));
        $this->assertTrue(PayoutSettings::providerAutoApprove('flutterwave'));
        $this->assertTrue(PayoutSettings::providerAutoApprove('stripe'));
        $this->assertFalse(PayoutSettings::providerAutoApprove('paypal'));
        $this->assertFalse(PayoutSettings::providerAutoApprove('cryptomus'));
        $this->assertFalse(PayoutSettings::providerAutoApprove('manual_external'));
        $this->assertFalse(PayoutSettings::providerAutoApprove('payoneer'));

        Setting::setValue('payouts.provider.paypal.auto_approve', true);
        $this->assertTrue(PayoutSettings::providerAutoApprove('paypal'), 'an explicit admin choice wins');
    }

    public function test_an_install_already_using_payouts_keeps_its_old_manual_behaviour_after_the_update(): void
    {
        Setting::where('key', 'like', 'payouts.%')->where('key', '!=', PayoutSettings::FLAG)->get()->each->delete();
        (require base_path('database/migrations/2026_10_08_100000_pin_legacy_payout_approval_settings.php'))->up();

        $this->assertSame('manual', PayoutSettings::mode());
        $this->assertFalse(PayoutSettings::autoApprovalEnabled());
        $this->assertTrue(PayoutSettings::shadowMode());
        $this->assertFalse(PayoutSettings::providerAutoApprove('paystack'));

        // ...and an admin's own choice is never overwritten.
        Setting::setValue(PayoutSettings::MODE, 'auto');
        (require base_path('database/migrations/2026_10_08_100000_pin_legacy_payout_approval_settings.php'))->up();
        $this->assertSame('auto', PayoutSettings::mode());
    }

    public function test_a_brand_new_install_gets_the_automatic_defaults(): void
    {
        Setting::where('key', PayoutSettings::FLAG)->first()?->delete();    // payouts never used, nothing stored
        Setting::where('key', 'like', 'payouts.%')->get()->each->delete();
        (require base_path('database/migrations/2026_10_08_100000_pin_legacy_payout_approval_settings.php'))->up();

        $this->assertSame('auto', PayoutSettings::mode());
        $this->assertTrue(PayoutSettings::autoApprovalEnabled());
        $this->assertFalse(PayoutSettings::shadowMode());
        $this->assertFalse(PayoutSettings::enabled(), 'but payouts themselves stay OFF until the owner switches them on');
    }
}
