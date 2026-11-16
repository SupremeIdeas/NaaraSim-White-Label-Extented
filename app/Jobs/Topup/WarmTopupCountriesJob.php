<?php

namespace App\Jobs\Topup;

use App\Services\Topup\ProviderRouter;
use App\Services\Topup\TopupProviders;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Asks each eligible supplier which countries it serves (in the background) and caches the answer for the Top-Up country list. */
class WarmTopupCountriesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(ProviderRouter $router): void
    {
        foreach (TopupProviders::codes() as $code) {
            if (! $router->eligible($code)) {
                continue;
            }
            try {
                Cache::put('topup.supplier.countries.'.$code, array_values((array) TopupProviders::get($code)->countries()), now()->addHours(6));
            } catch (Throwable) {
            }
        }
        Cache::put('topup.supplier.countries.warm', 1, now()->addHours(6));
        Cache::forget('topup.country.list');
    }
}
