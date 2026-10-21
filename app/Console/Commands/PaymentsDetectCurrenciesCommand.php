<?php

namespace App\Console\Commands;

use App\Support\GatewayCapabilities;
use Illuminate\Console\Command;

/** Asks each active payment gateway's own API which currencies the account can take, and stores the answer for the wallet. */
class PaymentsDetectCurrenciesCommand extends Command
{
    protected $signature = 'payments:detect-currencies';

    protected $description = 'Read the currencies each payment gateway account can really accept, from the gateway API';

    public function handle(): int
    {
        $rows = [];
        foreach (GatewayCapabilities::refreshAll() as $gw => $snap) {
            $rows[] = [$gw, implode(', ', $snap['currencies']) ?: '-', $snap['error'] ?? 'ok'];
        }
        $rows === [] ? $this->warn('No detectable gateway has keys yet.') : $this->table(['Gateway', 'Accepts', 'Check'], $rows);

        return self::SUCCESS;
    }
}
