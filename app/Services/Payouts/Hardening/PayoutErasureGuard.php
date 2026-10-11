<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Referrals\ReferralEarningsService;

/**
 * Account erasure and payouts (Addendum D-3.10). An account with a payout in flight, or money still owed
 * to it, cannot be anonymized — the money would have nowhere to go and the audit trail would break. Once it
 * can be erased, payout destination details are reduced to the masked identifier + blind-index fingerprint
 * (kept to stop the same destination being reused during the retention window).
 */
class PayoutErasureGuard
{
    /** @return list<string> human reasons erasure must wait (empty = clear) */
    public static function blockers(User $user): array
    {
        $out = [];
        $open = PayoutRequest::query()->where('user_id', $user->id)->whereIn('status', PayoutRequest::OPEN)->count();
        if ($open > 0) {
            $out[] = "{$open} payout request(s) are still in progress";
        }

        try {
            $credits = (float) app(CreditService::class)->withdrawableBalance($user);
            if ($credits > 0) {
                $out[] = number_format($credits, 0).' withdrawable credits remain';
            }
            $referral = (float) app(ReferralEarningsService::class)->balance($user);
            if ($referral > 0) {
                $out[] = '$'.number_format($referral, 2).' of referral earnings remain';
            }
        } catch (\Throwable) {
            // A bucket that cannot be read must not silently allow erasure.
            $out[] = 'earnings balances could not be verified';
        }

        return $out;
    }

    /** Reduce stored destinations (and the snapshots on requests) to masked data. */
    public static function purgeDestinations(User $user): void
    {
        $snapshots = app(DestinationSnapshot::class);

        PayoutAccount::query()->where('user_id', $user->id)->get()->each(function (PayoutAccount $a) {
            $masked = DestinationSnapshot::mask((string) $a->account_number);
            // lookup_hash is deliberately untouched (duplicate-destination protection until purge).
            $a->forceFill([
                'account_number' => $masked, 'details' => null, 'provider_recipient_ref' => null,
                'account_name' => 'Deleted', 'payee_kyc_name' => null, 'is_verified' => false, 'is_default' => false,
            ])->saveQuietly();
        });

        PayoutRequest::query()->where('user_id', $user->id)->whereNotNull('destination_snapshot')->get()->each(function (PayoutRequest $r) use ($snapshots) {
            $snap = $snapshots->read($r);
            if ($snap === null) {
                return;
            }
            $keep = array_intersect_key($snap, array_flip(['provider', 'method', 'type', 'country', 'currency', 'identifier_masked', 'fingerprint', 'payout_account_id', 'captured_at']));
            $r->forceFill(['destination_snapshot' => encrypt(json_encode($keep + ['erased' => true]))])->saveQuietly();
        });
    }
}
