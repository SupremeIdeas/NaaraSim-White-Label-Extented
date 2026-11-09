<?php

namespace App\Services\Pricing\Fx;

use App\Models\FxRateSnapshot;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * USD-based exchange rates from two independent keyless feeds, with the last good table kept in the database (blueprint 10.6).
 *
 *   Feed A  open.er-api.com   (the original feed, unchanged)
 *   Feed B  currency-api on jsDelivr (a different operator and a different data pipeline)
 *
 * Every table is sanity-checked (positive, the currencies we trade in present) and a sudden large jump is held back unless both feeds agree on it.
 * MONEY callers get a fresh table or the last one within `fx.money_max_age_minutes`, else null: there is never a constant to fall back on.
 * DISPLAY callers may use any age of snapshot (the page shows an age badge) before the static table.
 */
class FxRates
{
    /** The currencies a table must carry to be trusted at all. */
    public const REQUIRED = ['NGN', 'GHS', 'KES', 'ZAR', 'GBP', 'EUR'];

    public const FEEDS = ['open_er_api', 'currency_api'];

    /** @return array{source:string,rates:array<string,float>}|null a fresh, accepted table (saved as a snapshot), or null when both feeds fail or are held back */
    public function refresh(): ?array
    {
        $tables = [];
        foreach (self::FEEDS as $feed) {
            $rates = $this->sane($this->fetch($feed));
            if ($rates) {
                $tables[$feed] = $rates;
            }
        }

        foreach ($tables as $feed => $rates) {
            if ($this->deviates($rates) && ! $this->otherFeedAgrees($feed, $rates, $tables)) {
                Log::warning('fx: table held back, it jumped from the last known good rates and the other feed does not confirm it', ['source' => $feed]);

                continue;
            }
            FxRateSnapshot::create(['source' => $feed, 'base' => 'USD', 'rates_json' => $rates, 'fetched_at' => now()]);

            return ['source' => $feed, 'rates' => $rates];
        }

        return null;
    }

    /** Money mode: a fresh table, else the last good one that is young enough. Never a constant. */
    public function money(): ?array
    {
        $fresh = $this->refresh();
        if ($fresh) {
            return $fresh['rates'];
        }
        $snap = $this->lastGood();
        $max = (int) Setting::getValue('fx.money_max_age_minutes', (int) env('FX_MONEY_MAX_AGE_MINUTES', 360));
        if ($snap && $snap->fetched_at->diffInMinutes(now()) <= $max) {
            Log::warning('fx: money rates served from the last known good snapshot', ['age_minutes' => $snap->fetched_at->diffInMinutes(now())]);

            return $snap->rates_json;
        }

        return null;
    }

    /** Display mode: a fresh table, else the last good one of any age; null means "use the static table and mark it approximate". */
    public function display(): ?array
    {
        return $this->refresh()['rates'] ?? $this->lastGood()?->rates_json;
    }

    public function lastGood(): ?FxRateSnapshot
    {
        return FxRateSnapshot::query()->orderByDesc('fetched_at')->orderByDesc('id')->first();
    }

    /** Minutes since the newest accepted table, or null when none exists. */
    public function ageMinutes(): ?int
    {
        return $this->lastGood()?->fetched_at->diffInMinutes(now());
    }

    /** @return array<string,float>|null */
    private function fetch(string $feed): ?array
    {
        try {
            if ($feed === 'open_er_api') {
                $res = Http::timeout(10)->get('https://open.er-api.com/v6/latest/USD');

                return ($res->json('result') === 'success' && is_array($res->json('rates'))) ? array_map('floatval', $res->json('rates')) : null;
            }
            $res = Http::timeout(10)->get('https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json');
            $usd = $res->json('usd');

            return is_array($usd) ? collect($usd)->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => (float) $v])->all() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,float>|null the table when it carries every required currency at a positive rate */
    private function sane(?array $rates): ?array
    {
        if (! $rates) {
            return null;
        }
        foreach (self::REQUIRED as $c) {
            if (! isset($rates[$c]) || (float) $rates[$c] <= 0) {
                return null;
            }
        }

        return $rates;
    }

    private function deviates(array $rates): bool
    {
        $pct = (float) Setting::getValue('fx.deviation_pct', (float) env('FX_DEVIATION_PCT', 20));
        $last = $this->lastGood()?->rates_json;
        if ($pct <= 0 || ! $last) {
            return false;
        }
        foreach (self::REQUIRED as $c) {
            if (isset($last[$c]) && $last[$c] > 0 && abs($rates[$c] - $last[$c]) / $last[$c] * 100 > $pct) {
                return true;
            }
        }

        return false;
    }

    private function otherFeedAgrees(string $feed, array $rates, array $tables): bool
    {
        foreach ($tables as $other => $o) {
            if ($other === $feed) {
                continue;
            }
            $close = true;
            foreach (self::REQUIRED as $c) {
                $close = $close && abs($rates[$c] - $o[$c]) / $o[$c] <= 0.03;
            }
            if ($close) {
                return true;
            }
        }

        return false;
    }
}
