<?php

namespace App\Services\Payouts\Rail;

use App\Models\User;
use App\Models\WalletTransaction;

/**
 * How a user has actually paid us (Rail Guide G2). Every top-up credit carries the
 * reference `topup:{gateway}:{reference}` (CreditWalletJob), which joins reliably to
 * `payment_charges.reference` — so the gateway is read straight from the user's own
 * wallet ledger; no schema change to payment_charges is needed.
 */
class RailHistory
{
    /** Funding gateway → payout rail it maps to. */
    private const GATEWAY_RAIL = [
        'paystack' => 'paystack', 'flutterwave' => 'flutterwave', 'stripe' => 'stripe_connect', 'paypal' => 'paypal',
        'cryptomus' => 'cryptomus', 'nowpayments' => 'cryptomus', 'coinpayments' => 'cryptomus', 'binance' => 'cryptomus',
    ];

    /** @return array<string, int> payout rail => number of paid top-ups in the window */
    public function counts(User $user, int $days = 180): array
    {
        $refs = WalletTransaction::query()->where('user_id', $user->id)->where('type', 'credit')
            ->where('reference', 'like', 'topup:%')->where('created_at', '>=', now()->subDays($days))->pluck('reference');

        $out = [];
        foreach ($refs as $ref) {
            $gateway = explode(':', (string) $ref)[1] ?? null;
            $rail = self::GATEWAY_RAIL[$gateway] ?? null;
            $rail && $out[$rail] = ($out[$rail] ?? 0) + 1;
        }
        arsort($out);

        return $out;
    }
}
