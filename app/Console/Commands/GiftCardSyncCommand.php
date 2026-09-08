<?php

namespace App\Console\Commands;

use App\Services\GiftCards\GiftCardCatalogueSyncService;
use Illuminate\Console\Command;

/**
 * Sync the Naara Gift catalogue from every registered provider (Reloadly
 * primary, Zendit/Bitrefill/Tillo filling gaps in priority order).
 * `giftcards:sync` does all of them; `giftcards:sync reloadly` does one.
 */
class GiftCardSyncCommand extends Command
{
    protected $signature = 'giftcards:sync {provider? : one registered provider key (default: all)}';

    protected $description = 'Sync gift-card products from every registered provider into the Naara Gift catalogue';

    public function handle(GiftCardCatalogueSyncService $sync): int
    {
        $providers = $this->argument('provider') ? [$this->argument('provider')] : $sync->providerKeys();

        foreach ($providers as $provider) {
            $n = $sync->sync($provider);
            $this->info("{$provider}: synced {$n} product(s).");
        }

        return self::SUCCESS;
    }
}
