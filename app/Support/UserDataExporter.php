<?php

namespace App\Support;

use App\Models\User;

/**
 * Assembles a user's personal-data export (blueprint Section 26.2 / GDPR
 * portability). Whitelists only the user's OWN data with safe columns —
 * internal cost/profit fields are never included, and third-party PII (the
 * people this user referred) is masked so an export can't leak another user's
 * identity.
 */
class UserDataExporter
{
    public static function for(User $user): array
    {
        $user->loadMissing([
            'wallet',
            'walletTransactions',
            'esimOrders',
            'smsOrders',
            'virtualNumbers',
            'referralsMade.referred',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'notice' => 'This file contains the personal data NaaraSim holds about your account. '
                .'People you referred are shown anonymised to protect their privacy.',

            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country_code' => $user->country_code,
                'referral_code' => $user->referral_code,
                'kyc_status' => $user->kyc_status,
                'is_active' => $user->is_active,
                'registered_at' => optional($user->created_at)->toIso8601String(),
            ],

            'wallet' => $user->wallet ? [
                'ngn_balance' => $user->wallet->ngn_balance,
                'usd_balance' => $user->wallet->usd_balance,
                'total_deposits' => $user->wallet->total_deposits,
                'total_spent' => $user->wallet->total_spent,
            ] : null,

            'wallet_transactions' => $user->walletTransactions->map(fn ($t) => [
                'type' => $t->type,
                'amount' => $t->amount,
                'currency' => $t->currency,
                'balance_before' => $t->balance_before,
                'balance_after' => $t->balance_after,
                'reference' => $t->reference,
                'description' => $t->description,
                'status' => $t->status,
                'created_at' => optional($t->created_at)->toIso8601String(),
            ])->all(),

            'esim_orders' => $user->esimOrders->map(fn ($o) => [
                'provider' => $o->provider,
                'reference' => $o->provider_order_ref,
                'iccid' => $o->iccid,
                'status' => $o->status,
                'data_remaining_mb' => $o->data_remaining_mb,
                'price_paid' => $o->price_charged,
                'currency' => $o->currency,
                'activated_at' => optional($o->activated_at)->toIso8601String(),
                'expires_at' => optional($o->expires_at)->toIso8601String(),
                'created_at' => optional($o->created_at)->toIso8601String(),
            ])->all(),

            'sms_orders' => $user->smsOrders->map(fn ($o) => [
                'provider' => $o->provider,
                'service' => $o->service_name,
                'phone_number' => $o->phone_number,
                'otp_code' => $o->otp_code,
                'status' => $o->status,
                'price_paid' => $o->charged_to_user,
                'ordered_at' => optional($o->ordered_at)->toIso8601String(),
                'completed_at' => optional($o->completed_at)->toIso8601String(),
            ])->all(),

            'virtual_numbers' => $user->virtualNumbers->map(fn ($n) => [
                'provider' => $n->provider,
                'phone_number' => $n->phone_number,
                'capabilities' => $n->capabilities,
                'monthly_price' => $n->monthly_retail,
                'status' => $n->status,
                'active' => $n->active,
                'next_billing_date' => optional($n->next_billing_date)->toIso8601String(),
                'provisioned_at' => optional($n->provisioned_at)->toIso8601String(),
            ])->all(),

            // Third-party PII filter: never expose a referred user's name, email
            // or phone — only the reward relationship and an anonymised marker.
            'referrals_made' => $user->referralsMade->map(fn ($r) => [
                'referred' => self::maskReferred($r->referred),
                'reward_pct' => $r->reward_pct,
                'rewarded' => $r->rewarded,
                'rewarded_at' => optional($r->rewarded_at)->toIso8601String(),
                'created_at' => optional($r->created_at)->toIso8601String(),
            ])->all(),
        ];
    }

    /** JSON-encoded export, ready to write to disk. */
    public static function toJson(User $user): string
    {
        return json_encode(self::for($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** A non-identifying marker for a referred (third-party) account. */
    private static function maskReferred(?User $referred): ?string
    {
        if ($referred === null) {
            return null;
        }

        $initial = mb_substr((string) $referred->name, 0, 1);

        return ($initial !== '' ? mb_strtoupper($initial) : '?').'••• (user #'.$referred->id.')';
    }
}
