<?php

namespace Tests\Feature;

use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Payouts\PayoutEvent;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Payouts\WithdrawalService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * ROADMAP §Layer 1 — NaaraCredit → cash. Only withdrawable (first-referral)
 * credits can be cashed out; KYC is gated by the unified free-payout
 * threshold (NAARA-BUILD-22 §3), not a blanket KYC wall — the credits are
 * held on request and returned if the transfer fails.
 */
class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        PayoutSettings::enabled() ?: Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
    }

    private function credits(): CreditService
    {
        return app(CreditService::class);
    }

    private function verifiedUser(float $referralCredits): User
    {
        $user = User::factory()->create(['is_active' => true]);
        // First-referral reward → withdrawable bucket.
        $this->credits()->rewardReferral($user, 999, $referralCredits);
        // KYC L2 approved.
        KycVerification::create([
            'user_id' => $user->id, 'level' => 2, 'provider' => 'manual',
            'status' => KycVerification::APPROVED, 'reference' => 'kyc:'.$user->id,
        ]);

        return $user;
    }

    private function account(User $user): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);
    }

    public function test_referral_rewards_are_withdrawable_but_checkins_are_not(): void
    {
        $user = User::factory()->create();
        $this->credits()->rewardReferral($user, 5, 300);
        $this->credits()->earn($user, 200, 'checkin', 'checkin:x');

        $this->assertSame(500.0, $this->credits()->balance($user));
        $this->assertSame(300.0, $this->credits()->withdrawableBalance($user));
    }

    public function test_a_referral_is_only_rewarded_once(): void
    {
        $user = User::factory()->create();
        $this->credits()->rewardReferral($user, 7, 300);
        $this->credits()->rewardReferral($user, 7, 300); // replay — same referred person

        $this->assertSame(300.0, $this->credits()->withdrawableBalance($user));
    }

    public function test_redeeming_credits_spends_non_withdrawable_first(): void
    {
        $user = User::factory()->create();
        $this->credits()->rewardReferral($user, 1, 300);  // 300 withdrawable
        $this->credits()->earn($user, 200, 'checkin', 'c:1'); // +200 non-withdrawable = 500 total

        $this->credits()->spend($user, 150, 'redeem', 'r:1'); // eats non-withdrawable

        $this->assertSame(350.0, $this->credits()->balance($user));
        $this->assertSame(300.0, $this->credits()->withdrawableBalance($user)); // untouched
    }

    public function test_you_cannot_withdraw_more_than_the_withdrawable_bucket(): void
    {
        $user = $this->verifiedUser(1000);
        $account = $this->account($user);

        $this->expectException(PayoutException::class);
        app(WithdrawalService::class)->request($user, 2000, $account); // only 1000 withdrawable
    }

    public function test_a_withdrawal_holds_credits_and_creates_a_request(): void
    {
        $user = $this->verifiedUser(1000);   // $10 at 100/USD
        $account = $this->account($user);

        $request = app(WithdrawalService::class)->request($user, 1000, $account);

        $this->assertSame(0.0, $this->credits()->withdrawableBalance($user->fresh())); // held
        $this->assertSame(0.0, $this->credits()->balance($user->fresh()));
        $this->assertSame(PayoutRequest::PENDING, $request->status);
        $this->assertSame('referral_credits', $request->source_bucket);
        $this->assertEquals(1000, (float) $request->credit_amount);
        $this->assertSame('NGN', $request->currency);
        $this->assertGreaterThan(0, (float) $request->amount); // converted to local currency
    }

    public function test_below_minimum_is_rejected(): void
    {
        $user = $this->verifiedUser(100); // $1 — below the $5 default minimum
        $account = $this->account($user);

        $this->expectException(PayoutException::class);
        app(WithdrawalService::class)->request($user, 100, $account);
    }

    public function test_an_unverified_user_can_withdraw_within_the_free_threshold(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->credits()->rewardReferral($user, 1, 1000);
        $account = $this->account($user);

        $request = app(WithdrawalService::class)->request($user, 1000, $account);

        $this->assertSame(PayoutRequest::PENDING, $request->status);
    }

    public function test_a_user_past_the_free_threshold_cannot_withdraw_without_kyc(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->credits()->rewardReferral($user, 1, 5000);
        $account = $this->account($user);

        // Burn the default 5 free payouts (any bucket counts, per the unified
        // threshold).
        foreach (range(1, 5) as $i) {
            PayoutRequest::create([
                'user_id' => $user->id, 'amount' => 1, 'currency' => 'USD',
                'source_bucket' => 'referral_earnings', 'status' => PayoutRequest::PAID,
                'provider' => 'paystack', 'reference' => 'burn'.$i,
            ]);
        }

        $this->expectExceptionMessage('free payout limit');
        app(WithdrawalService::class)->request($user, 1000, $account);
    }

    public function test_a_failed_transfer_returns_the_held_credits(): void
    {
        // Fake gateway that rejects the transfer → engine fires PayoutReversed.
        $gateway = new class implements PayoutGatewayInterface
        {
            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return true;
            }

            public function createRecipient(PayoutAccount $account): string
            {
                return 'RCP';
            }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                return new PayoutTransferResult(status: 'failed', failureReason: 'Bank rejected');
            }

            public function verifyWebhook(Request $request): bool
            {
                return true;
            }

            public function parseWebhook(Request $request): ?PayoutEvent
            {
                return null;
            }
        };
        $this->app->instance(PayoutService::class, new PayoutService([$gateway]));

        $user = $this->verifiedUser(1000);
        $account = $this->account($user);

        $request = app(WithdrawalService::class)->request($user, 1000, $account);
        $this->assertSame(0.0, $this->credits()->withdrawableBalance($user->fresh())); // held

        app(PayoutService::class)->send($request); // fails → PayoutReversed → credits returned

        $this->assertSame(PayoutRequest::FAILED, $request->fresh()->status);
        $this->assertSame(1000.0, $this->credits()->withdrawableBalance($user->fresh())); // returned
        $this->assertSame(1000.0, $this->credits()->balance($user->fresh()));
    }
}
