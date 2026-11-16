<?php

namespace App\Jobs\Topup;

use App\Services\Topup\TopupCatalogue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Refresh one country's supplier prices OFF the request cycle. The Top-Up page used to ask every supplier live while the customer waited (tens of seconds
 * on a cold cache). Now the page shows the stored catalogue at once and this job brings it up to date in the background. One refresh per country at a time.
 */
class RefreshTopupCountryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(public string $country) {}

    public function uniqueId(): string
    {
        return 'topup-refresh-'.strtoupper($this->country);
    }

    public function handle(TopupCatalogue $catalogue): void
    {
        try {
            $catalogue->refresh($this->country, true);
        } catch (Throwable) {
            // a supplier fault is already logged and stored per supplier by refresh(); the page keeps showing the stored catalogue
        }
    }
}
