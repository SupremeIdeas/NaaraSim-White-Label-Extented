<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;
use App\Services\Payouts\Guardian\NameMatcher;
use App\Support\PayoutSettings;

/**
 * G7 — the name on the destination matches the person. Skipped (and recorded) where
 * the provider itself resolved the account holder's name (Paystack/Flutterwave) or
 * does its own KYC (Stripe Connect); where there is no name to compare (a PayPal
 * email, a crypto address) it is a warning, not a pass.
 */
class NameMatchGate implements Gate
{
    private const PROVIDER_RESOLVED = ['paystack', 'flutterwave'];

    public function id(): string
    {
        return 'G7_name_match';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $account = $ctx->account;
        $user = $ctx->user;
        if ($account === null || $user === null) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'name_unverifiable');
        }

        if (blank($account->payee_kyc_name) && in_array($account->provider, self::PROVIDER_RESOLVED, true)) {
            return GateResult::pass($this->id(), ['skipped' => 'skipped_provider_resolved']);
        }
        if ($account->provider === 'stripe') {
            return GateResult::pass($this->id(), ['skipped' => 'provider_kyc']);
        }

        $theirs = $account->payee_kyc_name ?: $account->account_name;
        if (! NameMatcher::looksLikeName((string) $theirs)) {
            return GateResult::warn($this->id(), ['result' => 'no_comparable_name', 'provider' => $account->provider], 10);
        }

        $score = NameMatcher::score((string) $user->name, (string) $theirs);
        $threshold = PayoutSettings::nameMatchThreshold();

        return $score >= $threshold
            ? GateResult::pass($this->id(), ['score' => round($score, 2), 'threshold' => $threshold])
            : GateResult::fail($this->id(), GateResult::HOLD, 'name_mismatch', [
                'score' => round($score, 2), 'threshold' => $threshold, 'profile_name' => $user->name, 'destination_name' => $theirs,
            ]);
    }
}
