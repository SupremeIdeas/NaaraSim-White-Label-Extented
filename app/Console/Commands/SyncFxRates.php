<?php

namespace App\Console\Commands;

use App\Services\Pricing\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Refresh the live USD→currency rates (owner request — localized pricing) from
 * the free open.er-api.com feed and warm the cache, so displayed local prices
 * stay current even between visits. Display-only: never touches money movement.
 */
class SyncFxRates extends Command
{
    protected $signature = 'fx:sync';

    protected $description = 'Refresh live currency-display exchange rates';

    public function handle(CurrencyService $currency): int
    {
        Cache::forget('fx.rates.usd');
        Cache::forget('fx.rates.usd.strict');
        $currency->flushNgnRate();
        \App\Models\FxRateSnapshot::where('fetched_at', '<', now()->subDays(30))->delete();
        $rates = $currency->liveRates();

        $age = app(\App\Services\Pricing\Fx\FxRates::class)->ageMinutes();
        if ($age === null || $age > 90) {
            $this->warn('Both exchange-rate feeds are failing; the last good table is '.($age === null ? 'missing' : $age.' minutes old').'.');
        }
        $this->info('FX rates refreshed: '.collect($rates)->map(fn ($r, $c) => "$c=$r")->implode('  '));

        return self::SUCCESS;
    }
}
