<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Jobs\SendPayoutJob;
use App\Models\CreditLedger;
use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutProviderCall;
use App\Models\PayoutRequest;
use App\Models\PayoutUserFreeze;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PayoutRequestedNotice;
use App\Notifications\PayoutStepUpCode;
use App\Services\Account\AccountService;
use App\Services\Payouts\DeclaresCapabilities;
use App\Services\Payouts\Hardening\FxGuard;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\Hardening\PayoutReference;
use App\Services\Payouts\Hardening\StepUpAuth;
use App\Services\Payouts\ManualExternalPayoutGateway;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutQuoter;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\SupportsStatusLookup;
use App\Support\PayoutMoney;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Addendum D (D-H2 / D-H3): precision, FX band, economics, references, cancel, freeze, step-up, erasure, manual rail. */
class PayoutHardeningTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
        config(['services.paystack.secret_key' => 'sk_test', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Cache::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function account(User $u, string $provider = 'paystack', string $number = '0123456789'): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => $number, 'account_name' => 'Test User', 'provider' => $provider,
            'is_verified' => true, 'is_default' => true,
        ]);
    }

    private function request(?User $u = null, string $provider = 'paystack', ?PayoutAccount $account = null): PayoutRequest
    {
        $u ??= User::factory()->create();
        $account ??= $this->account($u, $provider);
        $ref = 'wd:'.uniqid();
        CreditLedger::create(['user_id' => $u->id, 'type' => 'spend', 'source' => 'withdraw', 'withdrawable' => true, 'amount' => 5, 'balance_after' => 0, 'reference' => 'wd-hold:'.$ref]);
        $r = app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $account, $ref);
        $r->forceFill(['credit_amount' => 5])->save();

        return $r->fresh();
    }

    // ── G-16 money precision ──

    public function test_local_amounts_round_half_even_to_the_currencys_own_minor_units(): void
    {
        $this->assertSame([2, 0, 0, 3], [PayoutMoney::decimals('NGN'), PayoutMoney::decimals('JPY'), PayoutMoney::decimals('ugx'), PayoutMoney::decimals('KWD')]);
        $this->assertSame(2.0, PayoutMoney::round(2.5, 'JPY'), 'half-even: 2.5 -> 2');
        $this->assertSame(4.0, PayoutMoney::round(3.5, 'JPY'));
        $this->assertSame(1234.0, PayoutMoney::round(1234.4, 'UGX'));
        $this->assertSame(1.234, PayoutMoney::round(1.2345, 'KWD'), 'tie -> even digit');
        $this->assertSame(1.236, PayoutMoney::round(1.2355, 'KWD'));
        $this->assertSame(0.001, PayoutMoney::minorUnit('KWD'));

        Setting::setValue('payouts.currency_decimals', json_encode(['NGN' => 0]));
        $this->assertSame(0, PayoutMoney::decimals('NGN'), 'the admin can correct a currency without a deploy');
    }

    public function test_the_quote_locks_one_rounded_local_amount_in_the_currencys_units(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500.555);
        app(\App\Services\Pricing\CurrencyService::class)->flushNgnRate();
        $u = User::factory()->create();

        $q = app(PayoutQuoter::class)->quote(10.0, 'NGN', $this->account($u));

        $this->assertSame(PayoutMoney::round(10.0 * $q['quote']['fx_rate'], 'NGN'), $q['local_amount']);
    }

    // ── G-16 FX sanity band ──

    public function test_a_rate_that_jumps_past_the_sanity_band_is_refused_and_alerted_once(): void
    {
        Queue::fake();
        $fx = app(FxGuard::class);
        $this->assertSame(100.0, $fx->vet('KES', 100.0));
        $this->assertSame(110.0, $fx->vet('KES', 110.0), 'within the 15% band');

        foreach ([1, 2] as $_) {
            try {
                $fx->vet('KES', 200.0);
                $this->fail('a 80% jump must be refused');
            } catch (PayoutException $e) {
                $this->assertStringContainsString('updating', $e->getMessage());
            }
        }
        $this->assertCount(1, Queue::pushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'fx_anomaly'));

        $fx->accept('KES', 200.0);
        $this->assertSame(205.0, $fx->vet('KES', 205.0));

        Setting::setValue(PayoutSettings::FX_BAND, 0);
        $this->assertSame(900.0, $fx->vet('KES', 900.0), 'band 0 switches the guard off');
    }

    // ── G-18 corridor economics ──

    public function test_a_payout_too_small_for_the_providers_fixed_fee_is_refused_in_plain_words(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(\App\Services\Pricing\CurrencyService::class)->flushNgnRate();
        $u = User::factory()->create();
        $account = $this->account($u);
        PayoutCorridor::updateOrCreate(['country' => 'NG', 'currency' => 'NGN', 'provider' => 'paystack', 'method' => 'bank'], ['enabled' => true, 'min_usd' => null, 'max_usd' => null, 'fixed_fee_usd_est' => 1.00]);
        $this->assertSame(20.0, PayoutCorridor::where('country', 'NG')->where('provider', 'paystack')->first()->economicMinUsd(), '$1 fee at the default 5% ceiling = $20 minimum');

        try {
            app(PayoutQuoter::class)->quote(10.0, 'NGN', $account);
            $this->fail('should be refused');
        } catch (PayoutException $e) {
            $this->assertSame('The minimum for this payout method is $20.00.', $e->getMessage());
        }
        $this->assertGreaterThan(0, app(PayoutQuoter::class)->quote(25.0, 'NGN', $account)['local_amount']);

        Setting::setValue(PayoutSettings::MAX_FEE_RATIO, 0);
        $this->assertNull(PayoutCorridor::where('country', 'NG')->where('provider', 'paystack')->first()->economicMinUsd(), 'ratio 0 = guard off');
        $this->assertArrayNotHasKey('fixed_fee_usd_est', PayoutCorridor::where('country', 'NG')->where('provider', 'paystack')->first()->toArray(), 'admin-only, never serialised');
    }

    // ── G-17 reference registry ──

    public function test_provider_references_are_derived_clean_unique_and_what_the_gateway_sends(): void
    {
        Queue::fake();
        $a = $this->request();
        $b = $this->request();

        $this->assertMatchesRegularExpression('/^ns[psd][a-z0-9]+-[a-z0-9]+$/', $a->provider_reference);
        $this->assertLessThanOrEqual(32, strlen($a->provider_reference));
        $this->assertNotSame($a->provider_reference, $b->provider_reference);
        $this->assertStringNotContainsString(':', $a->provider_reference);
        $this->assertSame($a->provider_reference, PayoutReference::ensure($a), 'stable once stamped');

        Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'pending', 'transfer_code' => 'TRF_1']], 200)]);
        $a->account->forceFill(['provider_recipient_ref' => 'RCP_1'])->save();
        app(PayoutService::class)->send($a->fresh());

        Http::assertSent(fn ($req) => str_contains($req->url(), '/transfer') && $req['reference'] === $a->provider_reference);
    }

    public function test_a_legacy_request_without_a_provider_reference_still_uses_its_internal_one(): void
    {
        Queue::fake();
        $r = $this->request();
        $r->forceFill(['provider_reference' => null])->save();

        $this->assertSame($r->reference, $r->fresh()->wireReference());
    }

    public function test_a_webhook_naming_the_provider_reference_finds_the_request(): void
    {
        Queue::fake();
        $r = $this->request();
        $r->forceFill(['status' => PayoutRequest::PROCESSING])->save();

        app(PayoutService::class)->applyWebhook(new \App\Services\Payouts\PayoutEvent('paystack', $r->provider_reference, 'paid', 'TRF'));

        $this->assertSame(PayoutRequest::PAID, $r->fresh()->status);
    }

    // ── cancel window ──

    public function test_the_payee_can_cancel_until_a_provider_call_exists_and_the_hold_comes_back_once(): void
    {
        Queue::fake();
        Event::fake([PayoutReversed::class]);
        $r = $this->request();
        $svc = app(PayoutService::class);

        $this->assertTrue($svc->cancelByUser($r, $r->user));
        $this->assertFalse($svc->cancelByUser($r->fresh(), $r->user), 'idempotent');
        $this->assertSame(PayoutRequest::REVERSED, $r->fresh()->status);
        Event::assertDispatchedTimes(PayoutReversed::class, 1);

        $sent = $this->request();
        PayoutProviderCall::create(['payout_request_id' => $sent->id, 'provider' => 'paystack', 'idempotency_key' => $sent->reference, 'state' => 'submitted', 'started_at' => now()]);
        $sent->forceFill(['status' => PayoutRequest::APPROVED])->save();
        $this->assertFalse($svc->cancelByUser($sent, $sent->user), 'a request that reached the provider can never be cancelled here');
        $this->assertSame(PayoutRequest::APPROVED, $sent->fresh()->status);
    }

    public function test_only_the_owner_can_cancel(): void
    {
        Queue::fake();
        $r = $this->request();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PayoutService::class)->cancelByUser($r, User::factory()->create());
    }

    public function test_a_new_destination_waits_the_configured_delay_before_sending_and_a_known_one_does_not(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::SEND_DELAY_LOCAL, 10);
        $u = User::factory()->create();
        $first = $this->request($u, 'paystack', $account = $this->account($u));

        app(PayoutService::class)->approve($first, $this->admin());
        Queue::assertPushed(SendPayoutJob::class, fn ($j) => $j->delay !== null);

        $first->forceFill(['status' => PayoutRequest::PAID])->save();   // the destination now has a delivered payout
        $second = $this->request($u, 'paystack', $account);
        Queue::fake();
        app(PayoutService::class)->approve($second, $this->admin());
        Queue::assertPushed(SendPayoutJob::class, fn ($j) => $j->delay === null);
    }

    // ── freeze / this wasn't me ──

    public function test_this_wasnt_me_freezes_payouts_cancels_pending_ones_and_blocks_new_requests(): void
    {
        Queue::fake();
        $r = $this->request();
        $user = $r->user;

        $url = URL::temporarySignedRoute('payouts.not-me', now()->addDay(), ['user' => $user->id]);
        $this->get($url)->assertOk()->assertSee('Payouts paused');

        $this->assertTrue(PayoutFreeze::isFrozen($user->id));
        $this->assertSame(PayoutRequest::REVERSED, $r->fresh()->status);
        try {
            $this->request($user);
            $this->fail('a frozen payee cannot create a request');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('paused', $e->getMessage());
        }
        $this->assertCount(1, Queue::pushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'payout_user_frozen'));
    }

    public function test_the_not_me_link_must_be_signed_and_release_is_super_admin_only(): void
    {
        $user = User::factory()->create();
        $this->get('/payouts/not-me/'.$user->id)->assertForbidden();
        $this->assertFalse(PayoutFreeze::isFrozen($user->id));

        app(PayoutFreeze::class)->freeze($user);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PayoutFreeze::class)->release($user, $this->admin(), 'cleared');
    }

    public function test_requesting_a_withdrawal_emails_the_payee_with_a_not_me_link(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $account = $this->account($u);
        $ref = 'wd:'.uniqid();
        app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $account, $ref);

        Notification::assertSentTo($u, PayoutRequestedNotice::class);
        $mail = (new PayoutRequestedNotice(1))->toMail($u);
        $this->assertSame("This wasn't me", $mail->actionText);
        $this->assertStringContainsString('signature=', $mail->actionUrl);

        Setting::setValue(PayoutSettings::NOTIFY_ON_REQUEST, false);
        Notification::fake();
        app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $account, 'wd:other'.uniqid());
        Notification::assertNothingSent();
    }

    // ── step-up ──

    public function test_step_up_is_off_by_default_and_when_on_blocks_account_changes_until_a_code_is_verified(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $stepUp = app(StepUpAuth::class);
        $this->assertFalse($stepUp->required());
        $stepUp->assertFresh($u); // no throw while OFF

        Setting::setValue(PayoutSettings::STEP_UP_REQUIRED, true);
        try {
            app(PayoutAccountService::class)->addPaypalAccount($u, 'me@example.com');
            $this->fail('step-up must block');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('verify', $e->getMessage());
        }

        $this->assertSame('email', $stepUp->sendCode($u));
        $code = null;
        Notification::assertSentTo($u, PayoutStepUpCode::class, function ($n) use (&$code) {
            $code = (fn () => $this->code)->call($n);

            return true;
        });
        $this->assertFalse($stepUp->verify($u, '000000'));
        $this->assertTrue($stepUp->verify($u, $code));
        $this->assertTrue($stepUp->fresh($u));
        $this->assertSame('me@example.com', app(PayoutAccountService::class)->addPaypalAccount($u, 'me@example.com')->account_number);
    }

    public function test_step_up_locks_after_five_wrong_codes_and_limits_how_many_codes_can_be_sent(): void
    {
        Notification::fake();
        Setting::setValue(PayoutSettings::STEP_UP_REQUIRED, true);
        $u = User::factory()->create();
        $stepUp = app(StepUpAuth::class);
        $stepUp->sendCode($u);

        foreach (range(1, 5) as $_) {
            $this->assertFalse($stepUp->verify($u, '111111'));
        }
        $this->expectException(PayoutException::class);
        try {
            $stepUp->verify($u, '111111');
        } finally {
            $stepUp->sendCode($u);
            $stepUp->sendCode($u);
            $this->expectException(PayoutException::class);
            $stepUp->sendCode($u); // the 4th send inside the window
        }
    }

    public function test_a_global_rail_withdrawal_needs_step_up_but_a_local_one_does_not(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::STEP_UP_REQUIRED, true);
        $u = User::factory()->create();

        $local = app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $this->account($u), 'wd:local'.uniqid());
        $this->assertSame(PayoutRequest::PENDING, $local->status);

        $global = $this->account($u, 'payoneer', '999000111');
        $this->expectException(PayoutException::class);
        app(PayoutService::class)->createRequest($u, 50, 'USD', 'referral_credits', $global, 'wd:global'.uniqid());
    }

    // ── rate limits ──

    public function test_adding_accounts_and_requesting_withdrawals_are_rate_limited_and_configurable(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $svc = app(PayoutAccountService::class);
        Setting::setValue(PayoutSettings::ADD_ACCOUNT_PER_HOUR, 2);

        $svc->addPaypalAccount($u, 'a@example.com');
        $svc->addPaypalAccount($u, 'b@example.com');
        $this->expectException(PayoutException::class);
        try {
            $svc->addPaypalAccount($u, 'c@example.com');
        } finally {
            RateLimiter::clear('payout-add-account:'.$u->id);
            Setting::setValue(PayoutSettings::ADD_ACCOUNT_PER_HOUR, 0);
            $svc->addPaypalAccount($u, 'd@example.com');   // 0 = off
            $svc->addPaypalAccount($u, 'e@example.com');
        }
    }

    public function test_the_per_minute_withdraw_brake_trips_and_leaves_a_guardian_signal(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::WITHDRAW_PER_MINUTE, 2);
        Setting::setValue(PayoutSettings::MAX_OPEN, 50);
        $u = User::factory()->create();
        $account = $this->account($u);
        $svc = app(PayoutService::class);
        $svc->createRequest($u, 5000, 'NGN', 'referral_credits', $account, 'wd:1'.uniqid());
        $svc->createRequest($u, 5000, 'NGN', 'referral_credits', $account, 'wd:2'.uniqid());

        try {
            $svc->createRequest($u, 5000, 'NGN', 'referral_credits', $account, 'wd:3'.uniqid());
            $this->fail('third request in a minute must be refused');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('little fast', $e->getMessage());
        }
        $this->assertNotNull(Cache::get('payouts.throttle_hit.'.$u->id));
    }

    // ── erasure ──

    public function test_erasure_waits_for_in_flight_payouts_then_masks_the_destination(): void
    {
        Queue::fake();
        $r = $this->request();
        $user = $r->user;
        $user->forceFill(['deletion_requested_at' => now()])->save();
        $sa = User::factory()->create();
        $sa->assignRole('super_admin');

        try {
            app(AccountService::class)->approveDeletion($user->fresh(), $sa);
            $this->fail('erasure must wait');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertStringContainsString('in progress', $e->getMessage());
        }
        $this->assertNull($user->fresh()->deletion_approved_at, 'the request stays pending');

        // Settled and paid out: now it can go, and the destination is reduced to masked data.
        $r->forceFill(['status' => PayoutRequest::PAID])->save();
        CreditLedger::create(['user_id' => $user->id, 'type' => 'earn', 'source' => 'x', 'withdrawable' => true, 'amount' => 0, 'balance_after' => 0, 'reference' => 'z'.uniqid()]);
        app(AccountService::class)->approveDeletion($user->fresh(), $sa);

        $acc = PayoutAccount::where('user_id', $user->id)->first();
        $this->assertSame('******6789', $acc->account_number);
        $this->assertNotEmpty($acc->lookup_hash, 'fingerprint stays for duplicate-destination protection');
        $this->assertNull($acc->details);
        $snap = app(\App\Services\Payouts\Hardening\DestinationSnapshot::class)->read($r->fresh());
        $this->assertTrue($snap['erased']);
        $this->assertArrayNotHasKey('account_name', $snap);
    }

    // ── manual_external ──

    public function test_the_manual_rail_makes_no_provider_call_and_settles_only_with_proof(): void
    {
        Queue::fake();
        Http::fake();
        Setting::setValue(PayoutSettings::MANUAL_EXTERNAL, true);
        $u = User::factory()->create();
        $r = $this->request($u, 'manual_external', $this->account($u, 'manual_external'));
        $svc = app(PayoutService::class);

        $svc->send($r);

        Http::assertNothingSent();
        $this->assertSame(PayoutRequest::PROCESSING, $r->fresh()->status);

        $admin = $this->admin();
        foreach ([['', 'note'], ['REF-1', '']] as [$proof, $note]) {
            try {
                $svc->recordManualPayment($r->fresh(), $admin, $proof, $note);
                $this->fail('proof and note are both required');
            } catch (PayoutException) {
            }
        }
        $paid = $svc->recordManualPayment($r->fresh(), $admin, 'BANK-TRF-8841', 'Paid from GTB, receipt uploaded');
        $this->assertSame(PayoutRequest::PAID, $paid->status);
        $this->assertSame('manual:BANK-TRF-8841', $paid->provider_ref);

        $other = $this->request();
        $other->forceFill(['status' => PayoutRequest::PROCESSING])->save();
        $this->expectException(PayoutException::class);
        $svc->recordManualPayment($other, $admin, 'X', 'y');
    }

    public function test_the_manual_rail_is_off_until_the_owner_switches_it_on_and_counts_as_a_global_rail(): void
    {
        $gw = app(ManualExternalPayoutGateway::class);
        $this->assertFalse($gw->available());
        Setting::setValue(PayoutSettings::MANUAL_EXTERNAL, true);
        $this->assertTrue($gw->available());
        $this->assertTrue(\App\Services\Payouts\Rail\RailEnrollmentService::isGlobal('manual_external'));
    }

    // ── gateway completeness (C7/C8) ──

    public function test_every_registered_gateway_declares_its_capabilities_honestly(): void
    {
        $svc = (fn () => $this->gateways)->call(app(PayoutService::class));
        $this->assertNotEmpty($svc);

        foreach ($svc as $gw) {
            $this->assertInstanceOf(DeclaresCapabilities::class, $gw, $gw->name().' must declare capabilities');
            $c = $gw->capabilities();
            $this->assertSame(['confirms_synchronously', 'webhook', 'lookup', 'cancel'], array_keys($c), $gw->name());
            $this->assertSame($c['lookup'], $gw instanceof SupportsStatusLookup, $gw->name().': lookup capability must match the interface');
            $this->assertInstanceOf(PayoutGatewayInterface::class, $gw);
            if (! $c['webhook']) {
                $this->assertFalse($gw->verifyWebhook(\Illuminate\Http\Request::create('/x', 'POST')), $gw->name().' claims no webhook, so it must never verify one');
            }
        }

        $byName = collect($svc)->keyBy(fn ($g) => $g->name());
        $this->assertTrue($byName['stripe']->capabilities()['confirms_synchronously'], 'a Stripe Transfer is final on success');
        $this->assertFalse($byName['paystack']->capabilities()['confirms_synchronously']);
        $this->assertFalse($byName['manual_external']->capabilities()['webhook']);
    }

    // ── Paystack facts confirmed from its documentation ──

    public function test_our_provider_reference_meets_paystacks_published_rules_and_is_reused_on_retry(): void
    {
        Queue::fake();
        for ($i = 0; $i < 25; $i++) {
            $r = $this->request();
            $ref = $r->provider_reference;
            // Paystack: lowercase alphanumeric plus '-' and '_' only; >= 16 chars (or a UUID); <= 100.
            $this->assertMatchesRegularExpression('/^[a-z0-9_-]+$/', $ref);
            $this->assertGreaterThanOrEqual(16, strlen($ref));
            $this->assertLessThanOrEqual(100, strlen($ref));
            // Paystack: re-submitting the SAME reference is a safe retry; a new one is a new transfer.
            $this->assertSame($ref, PayoutReference::ensure($r->fresh()));
        }
    }

    public function test_a_paystack_transfer_waiting_for_an_otp_is_neither_refunded_nor_ignored(): void
    {
        Queue::fake();
        $r = $this->request();
        $r->account->forceFill(['provider_recipient_ref' => 'RCP_1'])->save();
        Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'otp', 'transfer_code' => 'TRF_9']], 200)]);

        app(PayoutService::class)->send($r->fresh());

        $this->assertSame(PayoutRequest::PROCESSING, $r->fresh()->status, 'not failed: someone could still finalize it');
        $this->assertCount(1, Queue::pushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'paystack_transfer_needs_otp'));
    }

    public function test_paystack_rejected_and_blocked_transfers_are_definitive_failures(): void
    {
        foreach (['rejected', 'blocked'] as $status) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Queue::fake();
            $r = $this->request();
            $r->account->forceFill(['provider_recipient_ref' => 'RCP_1'])->save();
            Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => $status, 'transfer_code' => 'T']], 200)]);

            app(PayoutService::class)->send($r->fresh());

            $this->assertSame(PayoutRequest::FAILED, $r->fresh()->status, $status);
        }
    }

    public function test_the_paystack_lookup_maps_every_documented_status(): void
    {
        $r = $this->request();
        $gw = app(\App\Services\Payouts\PaystackPayoutGateway::class);
        $expect = ['success' => 'paid', 'failed' => 'failed', 'abandoned' => 'failed', 'reversed' => 'failed', 'rejected' => 'failed', 'blocked' => 'failed',
            'pending' => 'processing', 'otp' => 'processing', 'received' => 'processing'];
        foreach ($expect as $paystack => $ours) {
            Http::swap(new \Illuminate\Http\Client\Factory);   // a fresh fake each time: the first stub would win otherwise
            Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => $paystack, 'transfer_code' => 'T']], 200)]);
            $res = $gw->lookupTransfer($r);
            $this->assertSame($ours, $res->status, $paystack);
        }
        Http::assertSent(fn ($req) => str_contains($req->url(), '/transfer/verify/'.$r->provider_reference));
    }

    public function test_the_paystack_balance_is_read_as_subunits(): void
    {
        config(['services.paystack.secret_key' => 'sk_test', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['*' => Http::response(['status' => true, 'data' => [['currency' => 'NGN', 'balance' => 5000000], ['currency' => 'KES', 'balance' => 250000]]], 200)]);

        $this->assertSame(['NGN' => 50000.0, 'KES' => 2500.0], app(\App\Services\Payouts\PaystackPayoutGateway::class)->balances());
    }

    // ── Stripe's documented reach ──

    public function test_stripe_corridors_outside_stripes_cross_border_regions_are_unavailable_until_the_owner_confirms_global_payouts(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_x']);
        foreach (['NG' => false, 'GH' => false, 'KE' => false, 'ZA' => false, 'US' => true, 'GB' => true, 'DE' => true, 'CA' => true, 'CH' => true, 'NO' => true] as $cc => $ok) {
            PayoutCorridor::updateOrCreate(['country' => $cc, 'currency' => 'USD', 'provider' => 'stripe', 'method' => 'stripe_connect'], ['enabled' => true]);
            $this->assertSame($ok, app(\App\Services\Payouts\Rail\StripeEligibility::class)->check($cc)['eligible'], $cc);
        }
        $this->assertNull(app(\App\Services\Payouts\CorridorRouter::class)->pick('NG', 'USD'), 'the router must not offer Stripe for Nigeria');

        Setting::setValue(PayoutSettings::STRIPE_GLOBAL, true);   // the owner confirmed Global Payouts access
        $this->assertTrue(app(\App\Services\Payouts\Rail\StripeEligibility::class)->check('NG')['eligible']);
    }
}
