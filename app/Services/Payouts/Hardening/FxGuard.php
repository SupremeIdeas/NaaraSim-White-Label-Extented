<?php

namespace App\Services\Payouts\Hardening;

use App\Jobs\AlertAdminJob;
use App\Services\Payouts\PayoutException;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Sanity band on FX rates used to price a payout (Addendum D-3.13). A single-source rate that jumps
 * further than `payouts.fx_sanity_band_pct` from the last accepted rate is more likely a feed fault
 * than a market move: the quote is refused ("rates updating") and an admin is told once an hour.
 * Accepted rates become the new reference.
 */
class FxGuard
{
    public function vet(string $currency, float $rate): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'USD' || $currency === 'USDT') {
            return $rate;
        }

        $key = 'payouts.fx.last_good.'.$currency;
        $last = Cache::get($key);
        $band = PayoutSettings::fxSanityBandPct();

        if ($band > 0 && is_array($last) && ($last['rate'] ?? 0) > 0) {
            $moved = abs($rate / (float) $last['rate'] - 1) * 100;
            if ($moved > $band) {
                if (Cache::add('payouts.fx.alert.'.$currency, 1, 3600)) {
                    AlertAdminJob::dispatch(
                        code: 'fx_anomaly',
                        message: "The {$currency} rate moved {$moved}% (".round((float) $last['rate'], 4).' → '.round($rate, 4).") — beyond the {$band}% sanity band. Payout quotes in {$currency} are paused until the rate settles or an admin accepts it.",
                        context: ['currency' => $currency, 'old' => $last['rate'], 'new' => $rate],
                    );
                }
                throw new PayoutException('Exchange rates are updating — please try again in a little while.');
            }
        }

        Cache::forever($key, ['rate' => $rate, 'at' => now()->toIso8601String()]);

        return $rate;
    }

    /** An admin has looked at the move and accepts the new rate as the reference. */
    public function accept(string $currency, float $rate): void
    {
        Cache::forever('payouts.fx.last_good.'.strtoupper($currency), ['rate' => $rate, 'at' => now()->toIso8601String()]);
        Cache::forget('payouts.fx.alert.'.strtoupper($currency));
    }
}
