<?php

namespace App\Console\Commands;

use App\Support\CookieConsent\CookieConsent;
use Illuminate\Console\Command;

/** Deletes cookie-consent proof older than the retention period set in Admin > Cookies (default 36 months). */
class PruneCookieConsentLogs extends Command
{
    protected $signature = 'cookies:prune';

    protected $description = 'Delete cookie consent log rows older than the retention period';

    public function handle(): int
    {
        $this->info('Removed '.CookieConsent::prune().' old consent record(s).');

        return self::SUCCESS;
    }
}
