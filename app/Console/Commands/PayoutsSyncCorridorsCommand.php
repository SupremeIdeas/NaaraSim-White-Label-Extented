<?php

namespace App\Console\Commands;

use App\Models\PayoutCorridor;
use Illuminate\Console\Command;

/**
 * Creates payout corridors for an updater-delivered rail from the PROVIDER'S OWN list — never invented. Every row is
 * created DISABLED; an admin enables a corridor only after a sandbox payout proves it.
 *
 *   php artisan payouts:sync-corridors grey                    reads Grey's live /v1/payout-methods
 *
 * Grey: a country is added with its OWN currency only (ICU country->currency), and only when Grey lists both for the
 * method. Wider pairs (e.g. USD to any country) are an explicit admin decision in Payout settings.
 */
class PayoutsSyncCorridorsCommand extends Command
{
    protected $signature = 'payouts:sync-corridors {provider : grey}';

    protected $description = 'Create DISABLED payout corridors for a rail from the provider\'s own method list';

    public function handle(): int
    {
        return match ($this->argument('provider')) {
            'grey' => $this->grey(),
            default => $this->fail('Unknown provider. Use grey.'),
        };
    }

    private function grey(): int
    {
        if (! class_exists(\App\PayoutRails\Grey\GreyClient::class)) {
            return $this->fail('The Grey rail is not installed.');
        }
        $client = app(\App\PayoutRails\Grey\GreyClient::class);
        if (! $client->configured()) {
            return $this->fail('Set the Grey API key first (Admin, Provider Keys).');
        }
        $methods = $client->payoutMethods(fresh: true);
        if ($methods === []) {
            return $this->fail('Grey returned no payout methods (key, base URL or network). Nothing was created.');
        }

        $created = 0;
        $skipped = 0;
        foreach ($methods as $m) {
            // Only the rails we can collect details for (interac / pix / upi / wallet_address publish no field list).
            if (! in_array($m['name'] ?? '', ['bank_account', 'mobile_money'], true) || empty($m['required_fields'])) {
                continue;
            }
            $method = $m['name'] === 'mobile_money' ? 'mobile_money' : 'bank';
            $currencies = array_map('strtoupper', (array) ($m['currencies'] ?? []));
            foreach ((array) ($m['countries'] ?? []) as $country) {
                $country = strtoupper((string) $country);
                $currency = $this->nativeCurrency($country);
                if ($currency === null || ! in_array($currency, $currencies, true)) {
                    $skipped++;

                    continue;
                }
                $created += $this->make($country, $currency, 'grey', $method, (string) ($m['average_processing_time'] ?? ''), 'Synced from Grey payout-methods. Enable after a sandbox payout.');
            }
        }
        $this->info("Grey: {$created} new corridor(s) created (disabled); {$skipped} country/currency pair(s) skipped (not the country's own currency).");

        return self::SUCCESS;
    }


    private function make(string $country, string $currency, string $provider, string $method, string $eta, string $notes): int
    {
        $row = PayoutCorridor::query()->where(compact('country', 'currency', 'provider', 'method'))->first();
        if ($row !== null) {
            return 0;
        }
        PayoutCorridor::create(['country' => $country, 'currency' => $currency, 'provider' => $provider, 'method' => $method, 'enabled' => false, 'priority' => 100, 'eta_text' => $eta ?: null, 'notes' => $notes]);

        return 1;
    }

    private function nativeCurrency(string $country): ?string
    {
        if (! class_exists(\NumberFormatter::class) || ! preg_match('/^[A-Z]{2}$/', $country)) {
            return null;
        }
        $code = (new \NumberFormatter('en_'.$country, \NumberFormatter::CURRENCY))->getTextAttribute(\NumberFormatter::CURRENCY_CODE);

        return is_string($code) && preg_match('/^[A-Z]{3}$/', $code) && $code !== 'XXX' ? $code : null;
    }
}
