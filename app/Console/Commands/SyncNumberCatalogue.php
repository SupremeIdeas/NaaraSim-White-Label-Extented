<?php

namespace App\Console\Commands;

use App\Support\NumberCatalogue;
use Illuminate\Console\Command;

/**
 * Pull the FULL live country + service catalogue from the number providers and
 * union it over the static base, so the storefront lists every country and
 * service the providers actually support — never a curated handful (blueprint
 * Section 12). Safe to run anytime: a provider that isn't configured or errors
 * is skipped, and the stored catalogue is only ever extended, never shrunk.
 *
 * Primary source is 5sim (global, 180+ countries, slug-based — matching the buy
 * flow). HeroSMS/VirtSMS (owner audit, 2026-09-15) additionally contribute their
 * live-discovered country ID map (HeroSmsService::syncCatalogue(), name-matched
 * against this same catalogue — see NumberCatalogue::providerCountryMap()),
 * which is what actually lets those two providers route correctly; this was the
 * "claims to sync from HeroSMS" half that was never wired up. Scheduled weekly;
 * run on demand after adding keys.
 */
class SyncNumberCatalogue extends Command
{
    protected $signature = 'numbers:catalogue-sync';

    protected $description = 'Sync the full number country + service catalogue from the providers.';

    public function handle(): int
    {
        $countries = [];
        $services = [];

        if (filled(config('services.fivesim.api_key'))) {
            try {
                /** @var \App\Services\SMS\FiveSimService $five */
                $five = app('number.fivesim');
                $countries = $five->catalogueCountries();

                $base = NumberCatalogue::baseServices();
                foreach ($five->catalogueServices() as $slug) {
                    // Keep the nicer base labels; only ADD services we don't name.
                    if (! isset($base[$slug])) {
                        $services[$slug] = ucwords(str_replace(['_', '-'], ' ', $slug));
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('5sim catalogue sync failed: '.$e->getMessage());
            }
        } else {
            $this->line('5sim not configured — using the static catalogue base.');
        }

        NumberCatalogue::storeSynced($countries, $services);

        foreach (['herosms' => 'number.herosms', 'virtsms' => 'number.virtsms'] as $provider => $binding) {
            if (empty(config("services.{$provider}.api_key"))) {
                $this->line("{$provider} not configured — skipping its country map sync.");

                continue;
            }
            try {
                /** @var \App\Services\SMS\HeroSmsService $svc */
                $svc = app($binding);
                $result = $svc->syncCatalogue();
                NumberCatalogue::storeSynced($result['countries'], []);
                NumberCatalogue::storeProviderCountryMap($provider, $result['country_map']);
                $countries = array_merge($countries, $result['countries']);
                $this->info(sprintf(
                    '%s: matched %d countries to its live id table (+%d new to the catalogue).',
                    ucfirst($provider), count($result['country_map']), count($result['countries']),
                ));
            } catch (\Throwable $e) {
                $this->warn("{$provider} catalogue sync failed: ".$e->getMessage());
            }
        }

        $this->info(sprintf(
            'Catalogue synced: +%d countries, +%d extra services (total %d countries, %d services).',
            count($countries), count($services),
            count(NumberCatalogue::countries()), count(NumberCatalogue::services()),
        ));

        return self::SUCCESS;
    }
}
