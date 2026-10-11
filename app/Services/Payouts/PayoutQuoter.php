<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Services\Pricing\CurrencyService;
use App\Support\PayoutMoney;
use App\Support\PayoutSettings;

/**
 * Prices a withdrawal ONCE, at request time, and hands the result to
 * PayoutService::createRequest() so the rate is locked on the row
 * (`usd_amount`, `fx_rate`, `platform_fee_usd`, `corridor_id`,
 * `quote_expires_at`, `fx_locked_at`).
 *
 * Money rules: the user is debited the full `$usd`; the platform fee (if the
 * corridor has one) is withheld from what is SENT, never added on top; provider
 * cost never appears here (it lives on the corridor, admin-only). USD and NGN keep
 * working with no corridor row (legacy behaviour); any other currency is paid
 * ONLY through an enabled corridor and only with a live FX rate.
 */
class PayoutQuoter
{
    /** Currencies the engine has always paid without needing a corridor row. */
    private const LEGACY = ['USD', 'NGN'];

    public function __construct(private CurrencyService $fx, private CorridorRouter $router) {}

    /**
     * @return array{local_amount: float, quote: array<string, mixed>}
     *
     * @throws PayoutException
     */
    public function quote(float $usd, string $currency, PayoutAccount $account): array
    {
        $currency = strtoupper($currency);
        $corridor = $this->router->corridorForAccount($account);

        if ($corridor === null && ! in_array($currency, self::LEGACY, true)) {
            throw new PayoutException("Withdrawals to {$currency} aren't available yet.");
        }
        if ($corridor !== null && ($econ = $corridor->economicMinUsd()) !== null && $usd < $econ) {
            // Plain words: the provider's fixed fee would eat too much of a payout this small.
            throw new PayoutException('The minimum for this payout method is $'.number_format($econ, 2).'.');
        }
        if ($corridor !== null && ! $corridor->allows($usd)) {
            throw new PayoutException('That amount is outside the limits for this payout method.');
        }

        $fee = $corridor?->feeUsd($usd) ?? 0.0;
        $net = round($usd - $fee, 4);
        if ($net <= 0) {
            throw new PayoutException('That amount is too small to cover the payout fee.');
        }

        // throws PayoutException when there is no trustworthy rate, or the rate jumped past the sanity band
        $rate = app(Hardening\FxGuard::class)->vet($currency, $this->fx->usdTo($currency));
        $now = now();

        return [
            'local_amount' => PayoutMoney::round($net * $rate, $currency), // the currency's own minor units, half-even
            'quote' => [
                'usd_amount' => round($usd, 4),
                'fx_rate' => $rate,
                'platform_fee_usd' => $fee,
                'corridor_id' => $corridor?->id,
                'fx_locked_at' => $now,
                'quote_expires_at' => $now->copy()->addHours(PayoutSettings::quoteLockHours()),
            ],
        ];
    }
}
