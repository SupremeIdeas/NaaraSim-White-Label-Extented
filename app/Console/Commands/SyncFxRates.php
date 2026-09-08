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
        $rates = $currency->liveRates();

        $this->info('FX rates refreshed: '.collect($rates)->map(fn ($r, $c) => "$c=$r")->implode('  '));

        return self::SUCCESS;
    }
}
