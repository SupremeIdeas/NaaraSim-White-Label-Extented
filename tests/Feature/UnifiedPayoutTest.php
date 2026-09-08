<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Jobs\AlertAdminJob;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Referrals\ReferralEarningsService;
use App\Services\Referrals\ReferralWithdrawalService;
use App\Support\PayoutSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NAARA-BUILD-22 §2/§3/§4/§6 — the unified free-payout threshold, referral cash-out
 * on the shared engine, and the automatic recurring earnings-payout run.
 */
class UnifiedPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        Setting::setValue(PayoutSettings::MIN, 1.0, 'payouts');
        Setting::setValue(PayoutSettings::FREE_COUNT, 2, 'payouts');
    }

    private function verifiedAccount(User $user): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'USD',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'Test',
            'provider' => 'paystack', 'is_verified' => true,
        ]);
    }

    private function fund(User $user, float $usd): void
    {
        app(ReferralEarningsService::class)->accrue($user, null, 'esim', $usd, 'seed:'.$user->id.':'.uniqid());
    }

    public function test_threshold_counts_paid_payouts_across_all_earner_types(): void
    {
        $user = User::factory()->create();
        $threshold = app(PayoutThreshold::class);

        $this->assertFalse($threshold->requiresKyc($user));
        $this->assertSame(2, $threshold->remainingFree($user));

        // Two settled payouts of ANY bucket consume the free allowance.
        foreach (['partner_earnings', 'merchant_earnings'] as $i => $bucket) {
            PayoutRequest::create([
                'user_id' => $user->id, 'amount' => 5, 'currency' => 'USD',
                'source_bucket' => $bucket, 'status' => PayoutRequest::PAID,
                'provider' => 'paystack', 'reference' => 'p'.$i,
            ]);
        }

        $this->assertTrue($threshold->requiresKyc($user));
        $this->assertSame(0, $threshold->remainingFree($user));
    }

    public function test_referral_withdrawal_holds_the_bucket_on_the_shared_engine(): void
    {
        $user = User::factory()->create();
        $this->fund($user, 10.0);
        $account = $this->verifiedAccount($user);

        $request = app(ReferralWithdrawalService::class)->request($user, $account, 10.0);

        $this->assertSame('referral_earnings', $request->source_bucket);
        $this->assertSame('referral', $request->payee_type);
        // The bucket was held — balance now zero.
        $this->assertEqualsWithDelta(0.0, app(ReferralEarningsService::class)->balance($user), 0.0001);
    }

    public function test_a_reversed_referral_payout_returns_the_exact_hold(): void
    {
        $user = User::factory()->create();
        $this->fund($user, 10.0);
        $account = $this->verifiedAccount($user);

        $request = app(ReferralWithdrawalService::class)->request($user, $account, 10.0);
        $this->assertEqualsWithDelta(0.0, app(ReferralEarningsService::class)->balance($user), 0.0001);

        // Simulate a PSP reversal → the listener releases the held earnings.
        event(new PayoutReversed($request));
        $this->assertEqualsWithDelta(10.0, app(ReferralEarningsService::class)->balance($user), 0.0001);
    }

    public function test_withdrawal_past_the_free_threshold_is_blocked_without_kyc(): void
    {
        $user = User::factory()->create();
        $this->fund($user, 50.0);
        $account = $this->verifiedAccount($user);

        // Burn the 2 free payouts.
        foreach ([0, 1] as $i) {
            PayoutRequest::create([
                'user_id' => $user->id, 'amount' => 1, 'currency' => 'USD',
                'source_bucket' => 'referral_earnings', 'status' => PayoutRequest::PAID,
                'provider' => 'paystack', 'reference' => 'burn'.$i,
            ]);
        }

        $this->expectExceptionMessage('free payout limit');
        app(ReferralWithdrawalService::class)->request($user, $account, 10.0);
    }

    public function test_automatic_run_pays_eligible_referral_balances(): void
    {
        $user = User::factory()->create();
        $this->fund($user, 8.0);
        $this->verifiedAccount($user);

        // A user with a balance but NO verified account is skipped, not paid.
        $noAccount = User::factory()->create();
        $this->fund($noAccount, 8.0);

        // The auto run sends immediately — fake the PSP so no real network call
        // (a failed send would reverse the hold and defeat the assertion).
        config(['services.paystack.secret_key' => 'sk_test']);
        Http::fake([
            '*/transferrecipient' => Http::response(['data' => ['recipient_code' => 'RCP_1']]),
            '*/transfer' => Http::response(['status' => true, 'data' => ['status' => 'success', 'transfer_code' => 'TRF_1']]),
        ]);

        $this->artisan('payouts:earnings-run')->assertSuccessful();

        // The eligible user's balance was held (paid out); the other untouched.
        $this->assertEqualsWithDelta(0.0, app(ReferralEarningsService::class)->balance($user), 0.0001);
        $this->assertEqualsWithDelta(8.0, app(ReferralEarningsService::class)->balance($noAccount), 0.0001);
        $this->assertSame(1, PayoutRequest::where('source_bucket', 'referral_earnings')->count());
    }

    public function test_automatic_run_alerts_an_admin_when_a_payout_throws(): void
    {
        // The cron's own stdout is discarded in production (routes/console.php
        // pipes schedule:run to /dev/null) — a payout that throws must not be
        // invisible until someone happens to grep the log file.
        Bus::fake([AlertAdminJob::class]);
        $user = User::factory()->create();
        $this->fund($user, 8.0);
        $this->verifiedAccount($user);

        $failing = \Mockery::mock(ReferralWithdrawalService::class);
        $failing->shouldReceive('availableUsd')->andReturn(8.0);
        $failing->shouldReceive('request')->andThrow(new \RuntimeException('simulated infra failure'));
        $this->app->instance(ReferralWithdrawalService::class, $failing);

        $this->artisan('payouts:earnings-run')->assertSuccessful();

        Bus::assertDispatched(AlertAdminJob::class, fn ($job) => $job->code === 'earnings_payout_failed'
            && $job->context['user_id'] === $user->id);
    }
}
