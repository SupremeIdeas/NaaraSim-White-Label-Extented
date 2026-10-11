<?php

namespace Tests\Feature;

use App\Livewire\SendEarnings;
use App\Models\EarningsTransfer;
use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\ReferralEarning;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\EarningsTransferNotice;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\Peer\EarningsTransferService;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Member-to-member earnings transfer: escrow, accept/decline/expire, limits, no chaining, privacy, review of received money. */
class EarningsTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Setting::setValue(PayoutSettings::FLAG, true);
    }

    /** A member in a country with no rail, holding $earned of referral earnings. */
    private function sender(float $earned = 100.0): User
    {
        $u = User::factory()->create(['is_active' => true, 'country_code' => 'BR', 'created_at' => now()->subDays(30)]);
        app(ReferralEarningsService::class)->accrue($u, null, 'esim', $earned, 'seed:'.$u->id);

        return $u;
    }

    /** A member who can really be paid out: verified identity + a verified Paystack account in Nigeria. */
    private function receiver(string $email = 'ada@example.com'): User
    {
        $u = User::factory()->create(['is_active' => true, 'email' => $email, 'name' => 'Ada Okafor', 'country_code' => 'NG', 'created_at' => now()->subDays(60)]);
        KycVerification::create(['user_id' => $u->id, 'level' => 2, 'provider' => 'manual', 'status' => KycVerification::APPROVED, 'reference' => 'k:'.$u->id]);
        PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058', 'account_number' => '0123456789',
            'account_name' => 'ADA OKAFOR', 'provider' => 'paystack', 'is_verified' => true, 'is_default' => true, 'provider_recipient_ref' => 'RCP_1',
        ]);

        return $u;
    }

    private function svc(): EarningsTransferService
    {
        return app(EarningsTransferService::class);
    }

    private function bal(User $u): float
    {
        return app(ReferralEarningsService::class)->balance($u);
    }

    public function test_send_holds_the_money_and_accept_credits_the_receiver_exactly_once(): void
    {
        Notification::fake();
        $sender = $this->sender(100);
        $receiver = $this->receiver();

        $t = $this->svc()->send($sender, 'ada@example.com', 40.0, 'referral', 'for rent');
        $this->assertSame(60.0, $this->bal($sender));     // out of the sender's ledger at once (escrow)
        $this->assertSame(0.0, $this->bal($receiver));    // not the receiver's until accepted
        Notification::assertSentTo($receiver, EarningsTransferNotice::class);

        $this->svc()->accept($receiver, $t);
        $this->assertSame(40.0, $this->bal($receiver));
        $this->assertSame(60.0, $this->bal($sender));
        $this->assertSame(EarningsTransfer::ACCEPTED, $t->fresh()->status);

        try {
            $this->svc()->accept($receiver, $t->fresh());
            $this->fail('A second accept must be refused.');
        } catch (PayoutException) {
        }
        $this->assertSame(40.0, $this->bal($receiver));   // never credited twice
        $this->assertSame(1, ReferralEarning::where('type', ReferralEarning::TRANSFER_IN)->count());
    }

    public function test_transfers_are_never_accruals_so_platform_margin_maths_is_untouched(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();
        $before = (float) ReferralEarning::where('type', ReferralEarning::ACCRUAL)->sum('amount');

        $t = $this->svc()->send($sender, 'ada@example.com', 30.0, 'referral');
        $this->svc()->accept($receiver, $t);

        $this->assertSame($before, (float) ReferralEarning::where('type', ReferralEarning::ACCRUAL)->sum('amount'));
    }

    public function test_decline_cancel_and_expiry_each_return_the_money_once(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();

        $a = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');
        $this->svc()->decline($receiver, $a);
        $this->assertSame(100.0, $this->bal($sender));

        $b = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');
        $this->svc()->cancel($sender, $b);
        $this->assertSame(100.0, $this->bal($sender));

        $c = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');
        $this->assertSame(90.0, $this->bal($sender));
        $c->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertSame(1, $this->svc()->expireDue());
        $this->assertSame(0, $this->svc()->expireDue());          // nothing left to return the second time
        $this->assertSame(100.0, $this->bal($sender));
        $this->assertSame(EarningsTransfer::EXPIRED, $c->fresh()->status);
    }

    public function test_an_expired_transfer_cannot_be_accepted_and_one_answer_wins_a_race(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();
        $t = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');
        $t->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(PayoutException::class);
        $this->svc()->accept($receiver, $t);
    }

    public function test_a_cancelled_transfer_cannot_then_be_accepted(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();
        $t = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');
        $this->svc()->cancel($sender, $t);

        try {
            $this->svc()->accept($receiver, $t->fresh());
            $this->fail('accept after cancel must fail');
        } catch (PayoutException) {
        }
        $this->assertSame(0.0, $this->bal($receiver));
        $this->assertSame(100.0, $this->bal($sender));
    }

    public function test_only_the_addressee_can_accept_or_decline_and_only_the_sender_can_cancel(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();
        $other = User::factory()->create();
        $t = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');

        try {
            $this->svc()->accept($other, $t);
            $this->fail('x');
        } catch (PayoutException) {
        }
        $this->assertSame(403, $this->statusOf(fn () => $this->svc()->decline($other, $t)));
        $this->assertSame(403, $this->statusOf(fn () => $this->svc()->cancel($receiver, $t)));
        $this->assertSame(EarningsTransfer::PENDING, $t->fresh()->status);
    }

    private function statusOf(callable $fn): int
    {
        try {
            $fn();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $e->getStatusCode();
        }

        return 200;
    }

    public function test_you_cannot_send_more_than_you_have_or_outside_the_limits(): void
    {
        $sender = $this->sender(50);
        $this->receiver();

        foreach ([[60.0, 'balance'], [1.0, 'smallest'], [300.0, 'largest']] as [$amount, $word]) {
            try {
                $this->svc()->send($sender, 'ada@example.com', $amount, 'referral');
                $this->fail("{$amount} should be refused");
            } catch (PayoutException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(50.0, $this->bal($sender));
        $this->assertSame(0, EarningsTransfer::count());
    }

    public function test_receiver_must_be_verified_active_unfrozen_and_have_a_working_account_and_the_error_never_says_which(): void
    {
        $sender = $this->sender(100);
        $messages = [];

        // unknown email
        $messages[] = $this->refusal($sender, 'nobody@example.com');
        // no kyc
        $r = $this->receiver('nokyc@example.com');
        KycVerification::where('user_id', $r->id)->delete();
        $messages[] = $this->refusal($sender, 'nokyc@example.com');
        // no payout account
        $r2 = $this->receiver('noacct@example.com');
        PayoutAccount::where('user_id', $r2->id)->delete();
        $messages[] = $this->refusal($sender, 'noacct@example.com');
        // frozen
        $r3 = $this->receiver('frozen@example.com');
        app(PayoutFreeze::class)->freeze($r3);
        $messages[] = $this->refusal($sender, 'frozen@example.com');
        // inactive
        $r4 = $this->receiver('inactive@example.com');
        $r4->forceFill(['is_active' => false])->save();
        $messages[] = $this->refusal($sender, 'inactive@example.com');
        // provider switched off (no key)
        $this->receiver('ok@example.com');
        config(['services.paystack.secret_key' => null]);
        $messages[] = $this->refusal($sender, 'ok@example.com');

        $this->assertCount(1, array_unique($messages), 'every refusal must read the same so nothing can be probed');
        $this->assertSame(100.0, $this->bal($sender));
    }

    private function refusal(User $sender, string $email): string
    {
        try {
            $this->svc()->send($sender, $email, 10.0, 'referral');
        } catch (PayoutException $e) {
            return $e->getMessage();
        }
        $this->fail("send to {$email} should have been refused");
    }

    public function test_a_member_cannot_send_to_themselves(): void
    {
        $self = $this->receiver('me@example.com');
        app(ReferralEarningsService::class)->accrue($self, null, 'esim', 50.0, 'seed:self');
        $self->forceFill(['country_code' => 'BR'])->save();

        $this->expectException(PayoutException::class);
        $this->svc()->send($self, 'me@example.com', 10.0, 'referral');
    }

    public function test_only_members_we_cannot_pay_out_may_send_unless_the_owner_relaxes_it(): void
    {
        $nigerian = $this->receiver('ng@example.com');
        app(ReferralEarningsService::class)->accrue($nigerian, null, 'esim', 50.0, 'seed:ng');
        $this->receiver('other@example.com');

        $this->assertSame('has_own_rail', $this->svc()->senderBlock($nigerian));

        Setting::setValue(PayoutSettings::PEER_ONLY_UNSUPPORTED, false);
        $this->assertNull($this->svc()->senderBlock($nigerian));
    }

    public function test_no_chaining_a_member_who_received_cannot_send_and_a_sender_cannot_receive(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();
        $t = $this->svc()->send($sender, 'ada@example.com', 20.0, 'referral');
        $this->svc()->accept($receiver, $t);

        // the receiver (even if relaxed to allow Nigerians to send) is blocked from passing it on
        Setting::setValue(PayoutSettings::PEER_ONLY_UNSUPPORTED, false);
        $this->assertSame('received_recently', $this->svc()->senderBlock($receiver));

        // the sender has sent, so they cannot be someone's receiver
        $this->assertFalse($this->svc()->canReceive($sender->fresh()));
    }

    public function test_sender_and_receiver_30_day_caps_and_open_limit_hold(): void
    {
        Setting::setValue(PayoutSettings::PEER_SENDER_30D, 30);
        Setting::setValue(PayoutSettings::PEER_MAX_PENDING, 2);
        $sender = $this->sender(500);
        $this->receiver();

        $this->svc()->send($sender, 'ada@example.com', 20.0, 'referral');
        $this->expectException(PayoutException::class);
        $this->svc()->send($sender, 'ada@example.com', 20.0, 'referral');   // 40 > 30
    }

    public function test_a_young_account_and_a_switched_off_feature_are_refused(): void
    {
        $sender = $this->sender(100);
        $this->receiver();
        $sender->forceFill(['created_at' => now()->subDay()])->save();
        $this->assertSame('account_too_new', $this->svc()->senderBlock($sender));

        $sender->forceFill(['created_at' => now()->subDays(30)])->save();
        Setting::setValue(PayoutSettings::PEER_ENABLED, false);
        $this->assertSame('unavailable', $this->svc()->senderBlock($sender));
    }

    public function test_the_receiver_can_withdraw_the_money_through_the_normal_payout_path_and_it_is_sent_for_review(): void
    {
        $sender = $this->sender(100);
        $receiver = $this->receiver();
        $t = $this->svc()->send($sender, 'ada@example.com', 50.0, 'referral');
        $this->svc()->accept($receiver, $t);

        $this->assertSame(50.0, app(\App\Services\Referrals\ReferralWithdrawalService::class)->availableUsd($receiver));

        // Mostly received money: the Guardian adds the S_peer_funds signal (people look first).
        $req = PayoutRequest::create([
            'user_id' => $receiver->id, 'amount' => 50, 'currency' => 'USD', 'status' => PayoutRequest::PENDING, 'source_bucket' => 'referral_earnings',
            'reference' => 'rwd:test', 'credit_amount' => 50, 'provider' => 'paystack', 'payout_account_id' => PayoutAccount::where('user_id', $receiver->id)->value('id'),
        ]);
        $ctx = new \App\Services\Payouts\Guardian\GuardianContext($req->fresh());
        $codes = collect(app(\App\Services\Payouts\Guardian\RiskScorer::class)->signals($ctx))->map(fn ($g) => $g->id)->all();
        $this->assertContains('S_peer_funds', $codes);

        Setting::setValue(PayoutSettings::PEER_REVIEW_RECEIVED, false);
        $codes = collect(app(\App\Services\Payouts\Guardian\RiskScorer::class)->signals($ctx))->map(fn ($g) => $g->id)->all();
        $this->assertNotContains('S_peer_funds', $codes);
    }

    public function test_the_livewire_page_sends_accepts_and_declines(): void
    {
        Notification::fake();
        $sender = $this->sender(100);
        $receiver = $this->receiver();

        Livewire::actingAs($sender)->test(SendEarnings::class)
            ->set('email', 'ada@example.com')->set('amount', 25)->set('bucket', 'referral')
            ->call('send')->assertSet('error', null)->assertSee('held');
        $t = EarningsTransfer::first();

        Livewire::actingAs($receiver)->test(SendEarnings::class)
            ->assertSee('Waiting for your answer')->call('accept', $t->id)->assertSet('error', null);
        $this->assertSame(25.0, $this->bal($receiver));
    }

    public function test_the_page_explains_why_a_member_with_a_working_rail_cannot_send(): void
    {
        $nigerian = $this->receiver('ng2@example.com');
        Livewire::actingAs($nigerian)->test(SendEarnings::class)->assertSee('cash out to your own account');
    }

    public function test_the_rail_guide_offers_the_feature_where_no_rail_exists(): void
    {
        $br = User::factory()->create(['is_active' => true, 'country_code' => 'BR']);
        Livewire::actingAs($br)->test(\App\Livewire\PayoutGuide::class)->assertSee('Send earnings to a member');
    }

    public function test_sent_transfers_appear_in_the_expiry_command_and_it_is_scheduled(): void
    {
        $sender = $this->sender(100);
        $this->receiver();
        $t = $this->svc()->send($sender, 'ada@example.com', 10.0, 'referral');
        $t->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('payouts:peer-expire')->expectsOutput('returned=1')->assertSuccessful();
        $this->assertSame(100.0, $this->bal($sender));
    }

    public function test_one_unreturnable_transfer_does_not_stop_the_others_being_returned(): void
    {
        $this->receiver();
        $a = $this->sender(100);
        $b = $this->sender(100);
        $bad = $this->svc()->send($a, 'ada@example.com', 10.0, 'referral');
        $good = $this->svc()->send($b, 'ada@example.com', 10.0, 'referral');
        $bad->forceFill(['source_bucket' => 'merchant', 'expires_at' => now()->subMinute()])->save(); // no merchant account: cannot be returned
        $good->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertSame(1, $this->svc()->expireDue());
        $this->assertSame(100.0, $this->bal($b));
        $this->assertSame(EarningsTransfer::PENDING, $bad->fresh()->status); // rolled back, will be retried / reviewed
    }
}
