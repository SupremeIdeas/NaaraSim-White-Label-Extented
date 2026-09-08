<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The wallet page surfaces payout/withdrawal setup directly (owner request:
 * "make the payout area very easy to locate in wallet") instead of only at
 * the separate /rewards/withdraw route. Setting up a bank account is always
 * free (NAARA-BUILD-22 §3, owner request: "let payout setup, adding of their
 * bank account be free without KYC") — only withdrawing PAST the unified
 * free-payout threshold prompts identity verification, and that gate is a
 * display decision only: WithdrawalService::request() independently
 * re-enforces it server-side regardless of which page renders the form.
 */
class WalletPayoutSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_user_with_free_payouts_left_sees_the_withdraw_form(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertDontSee('You\'ve used your free withdrawals')
            ->assertSee('Withdraw earnings'); // the embedded Withdraw component's own heading
    }

    public function test_a_user_past_the_free_threshold_sees_a_verify_identity_prompt(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            PayoutRequest::create([
                'user_id' => $user->id, 'amount' => 1, 'currency' => 'USD',
                'source_bucket' => 'referral_earnings', 'status' => PayoutRequest::PAID,
                'provider' => 'paystack', 'reference' => 'burn'.$i,
            ]);
        }

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSee('used your free withdrawals')
            // Bank-account setup itself stays visible/usable even past the
            // threshold — only submitting a withdrawal is blocked.
            ->assertSee('Withdraw earnings');
    }

    public function test_a_default_payout_account_shows_in_the_collapsed_summary(): void
    {
        $user = User::factory()->create();
        PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'bank_name' => 'GTBank', 'account_number' => '0123456789',
            'account_name' => 'Test User', 'provider' => 'flutterwave',
            'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSee('GTBank')
            ->assertDontSee('Set up your bank account to withdraw earnings');
    }
}
