<?php

namespace App\Services\Payouts;

use App\Models\KycVerification;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Support\PayoutSettings;

/**
 * The AUTHORITATIVE admission check for a new payout request (Addendum C fix B).
 * It runs INSIDE PayoutService::createRequest's transaction, after the payee's user
 * row is locked, so two parallel clicks cannot both pass: the second sees the first
 * as an in-flight (committed) request. The friendly pre-checks in the withdrawal
 * services stay for readable errors; this is the one that cannot be raced.
 *
 * Scope: only the user-initiated buckets. Staff are exempt by policy, partners are
 * paid by a scheduled run, and platform withdrawals create two linked requests in
 * one action, so each keeps its own existing gate.
 */
class PayoutAdmission
{
    /** Buckets whose requests count against the free-payout limit and the open cap. */
    public const USER_BUCKETS = ['referral_credits', 'referral_earnings', 'merchant_earnings'];

    public function __construct(private readonly PayoutThreshold $threshold, private readonly KycService $kyc) {}

    /** @throws PayoutException */
    public function check(User $payee, string $sourceBucket, ?string $provider = null): void
    {
        // A frozen payee ("This wasn't me" / admin) can create nothing, whatever the bucket.
        if (\App\Services\Payouts\Hardening\PayoutFreeze::isFrozen($payee->id)) {
            throw new PayoutException('Payouts on this account are paused for your protection. Please contact support.');
        }
        // Global-rail withdrawals need a fresh step-up check when the owner has switched it on.
        if ($provider !== null && \App\Services\Payouts\Rail\RailEnrollmentService::isGlobal($provider)) {
            app(\App\Services\Payouts\Hardening\StepUpAuth::class)->assertFresh($payee);
        }

        if (! in_array($sourceBucket, self::USER_BUCKETS, true)) {
            return;
        }

        $this->throttle($payee);

        if (app(\App\Services\Payouts\Hardening\PayeeTax::class)->blocks($payee)) {
            throw new PayoutException('We need some tax details from you before this payout can be sent. Please contact support.');
        }

        $open = PayoutRequest::where('user_id', $payee->id)->whereIn('status', PayoutRequest::OPEN)->count();
        if ($open >= PayoutSettings::maxOpenRequests()) {
            throw new PayoutException('You already have '.$open.' withdrawals in progress. Please wait for one to finish before requesting another.');
        }

        // In-flight requests count (committedCount), so a slow rail can't be used to
        // file many requests before any is `paid` and slip past the free limit.
        if ($this->threshold->requiresKyc($payee) && ! $this->kyc->hasLevel($payee, KycVerification::L2)) {
            throw new PayoutException("You've reached your free payout limit — verify your identity to keep withdrawing.");
        }
    }

    /** Per-user brakes on request volume (Addendum D-3.21). A hit is also a soft signal for the Guardian. */
    private function throttle(User $payee): void
    {
        foreach ([[PayoutSettings::withdrawPerMinute(), 60, 'm'], [PayoutSettings::withdrawPerDay(), 86400, 'd']] as [$max, $ttl, $tag]) {
            if ($max <= 0) {
                continue;
            }
            $key = "payout-withdraw:{$tag}:{$payee->id}";
            if (! \Illuminate\Support\Facades\RateLimiter::attempt($key, $max, fn () => true, $ttl)) {
                \Illuminate\Support\Facades\Cache::put('payouts.throttle_hit.'.$payee->id, now()->timestamp, 86400);
                throw new PayoutException($tag === 'm'
                    ? 'You\'re going a little fast — please wait a minute and try again.'
                    : 'You\'ve reached today\'s withdrawal request limit. Please try again tomorrow.');
            }
        }
    }
}
