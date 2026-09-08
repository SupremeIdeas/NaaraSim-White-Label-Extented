<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Currency display (blueprint Section 13.4). All internal pricing is USD; NGN
 * is layered on top for display only. The rate is cached 1h and sourced either
 * manually (admin) or automatically from Airalo's exchange rates — with a safe
 * fallback so a provider hiccup never breaks price rendering.
 *
 * This only ever formats RETAIL prices. Cost is never passed here.
 */
class CurrencyService
{
    private const FALLBACK_RATE = 1500.0;

    /**
     * Currencies we can DISPLAY prices in (USD is always the settlement/default).
     * code => [symbol, name, decimals]. USDT is a USD-pegged stablecoin (1:1).
     * Fallback rates (USD→code) are only used when the live API is unreachable.
     *
     * @var array<string, array{0:string,1:string,2:int,3:float}>
     */
    public const SUPPORTED = [
        'USD' => ['$', 'US Dollar', 2, 1.0],
        'USDT' => ['₮', 'Tether (USDT)', 2, 1.0],
        'NGN' => ['₦', 'Nigerian Naira', 0, 1500.0],
        'GHS' => ['GH₵', 'Ghanaian Cedi', 2, 15.0],
        'KES' => ['KSh', 'Kenyan Shilling', 0, 130.0],
        'ZAR' => ['R', 'South African Rand', 2, 18.0],
        'GBP' => ['£', 'British Pound', 2, 0.79],
        'EUR' => ['€', 'Euro', 2, 0.92],
        'CAD' => ['C$', 'Canadian Dollar', 2, 1.36],
        'INR' => ['₹', 'Indian Rupee', 0, 83.0],
    ];

    /**
     * The single authoritative USD→NGN rate, used for BOTH display and payouts
     * (they must never disagree — see rate()). The base is either a manual rate
     * or Airalo's live mid-rate (the OFFICIAL/interbank rate). Nigerian customers
     * transact at the higher parallel ("black-market") rate, and no free, reliable,
     * ToS-clean parallel-rate feed exists — so an admin-set markup (%) lifts the
     * official base to track that parallel premium. Manual mode + 0% markup = the
     * exact rate the admin types. Cached 1h; busted on any pricing.* / manual save.
     */
    public function getUsdToNgn(): float
    {
        return Cache::remember('usd_ngn_rate', 3600, function () {
            $base = $this->baseUsdToNgn();
            $markup = (float) Setting::getValue('pricing.ngn_rate_markup_pct', 0);
            $markup = max(0.0, min(50.0, $markup)); // clamp 0–50%

            return round($base * (1 + $markup / 100), 2);
        });
    }

    /** The base rate before the parallel-market markup: manual, or Airalo's live official mid-rate. */
    private function baseUsdToNgn(): float
    {
        if (Setting::getValue('pricing.ngn_rate_source', 'auto') === 'manual') {
            return (float) Setting::getValue('pricing.manual_ngn_rate', self::FALLBACK_RATE);
        }

        try {
            $rates = app('esim.airalo')->getExchangeRates();
            $ngn = collect($rates['rates'] ?? [])->firstWhere('to', 'NGN')['mid'] ?? null;

            return $ngn ? (float) $ngn : self::FALLBACK_RATE;
        } catch (\Throwable $e) {
            return (float) Setting::getValue('pricing.manual_ngn_rate', self::FALLBACK_RATE);
        }
    }

    /** Bust the cached NGN rate so an admin rate/markup change takes effect at once. */
    public function flushNgnRate(): void
    {
        Cache::forget('usd_ngn_rate');
    }

    /**
     * Format a USD retail amount for display as both USD and NGN.
     *
     * @return array{usd: string, ngn: string, usd_amount: float, ngn_amount: float}
     */
    public function displayPrice(float $usd): array
    {
        $ngnAmount = round($usd * $this->getUsdToNgn());

        return [
            'usd' => '$'.number_format($usd, 2),
            'ngn' => 'NGN '.number_format($ngnAmount, 0),
            'usd_amount' => round($usd, 2),
            'ngn_amount' => $ngnAmount,
        ];
    }

    /** Is a currency one we can display prices in? */
    public function supports(string $currency): bool
    {
        return isset(self::SUPPORTED[strtoupper($currency)]);
    }

    /**
     * Live USD→currency rate for DISPLAY only. USD/USDT are 1:1; NGN reuses the
     * authoritative payout rate (getUsdToNgn) so displayed NGN never disagrees
     * with what a withdrawal actually pays; every other currency comes from the
     * free open.er-api.com feed (no key), cached 1h, with a safe fallback so a
     * provider hiccup never breaks price rendering. Never touches cost.
     */
    public function rate(string $currency): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'USD' || $currency === 'USDT') {
            return 1.0;
        }
        if ($currency === 'NGN') {
            return $this->getUsdToNgn();
        }
        if (! isset(self::SUPPORTED[$currency])) {
            return 1.0; // unknown → treat as USD
        }

        $fallback = self::SUPPORTED[$currency][3];
        $rates = $this->liveRates();

        return (float) ($rates[$currency] ?? $fallback);
    }

    /** All USD→code rates for the supported set (for a rate table / switcher). */
    public function liveRates(): array
    {
        return Cache::remember('fx.rates.usd', 3600, function () {
            try {
                $res = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get('https://open.er-api.com/v6/latest/USD');
                $rates = $res->json('rates');
                if (is_array($rates) && ($res->json('result') === 'success')) {
                    // Keep only the currencies we display.
                    return collect(self::SUPPORTED)
                        ->keys()
                        ->mapWithKeys(fn ($c) => [$c => (float) ($rates[$c] ?? self::SUPPORTED[$c][3])])
                        ->all();
                }
            } catch (\Throwable $e) {
                // fall through to the fallback table
            }

            return collect(self::SUPPORTED)->mapWithKeys(fn ($v, $c) => [$c => $v[3]])->all();
        });
    }

    /** Convert a USD amount to a display currency (rounded to its decimals). */
    public function convert(float $usd, string $currency): float
    {
        $decimals = self::SUPPORTED[strtoupper($currency)][2] ?? 2;

        return round($usd * $this->rate($currency), $decimals);
    }

    /**
     * Convert a LOCAL-currency amount back to USD (the wallet's settlement
     * currency), rounded to cents. Used to lock the USD credit at top-up time.
     */
    public function toUsd(float $localAmount, string $currency): float
    {
        $rate = $this->rate($currency);

        return $rate > 0 ? round($localAmount / $rate, 2) : round($localAmount, 2);
    }

    /** Format a USD amount in a display currency, e.g. "£15.80" or "₦18,000". */
    public function format(float $usd, string $currency): string
    {
        $currency = strtoupper($currency);
        [$symbol, , $decimals] = self::SUPPORTED[$currency] ?? ['$', 'US Dollar', 2, 1.0];

        return $symbol.number_format($this->convert($usd, $currency), $decimals);
    }

    /**
     * A USD amount shown as the default USD plus the user's local equivalent.
     * When the local currency IS USD, `local` is null (no redundant line).
     *
     * @return array{usd: string, usd_amount: float, local: ?string, local_code: string}
     */
    public function localPrice(float $usd, string $localCurrency): array
    {
        $localCurrency = strtoupper($localCurrency);

        return [
            'usd' => '$'.number_format($usd, 2),
            'usd_amount' => round($usd, 2),
            'local' => $localCurrency === 'USD' ? null : $this->format($usd, $localCurrency),
            'local_code' => $localCurrency,
        ];
    }
}
