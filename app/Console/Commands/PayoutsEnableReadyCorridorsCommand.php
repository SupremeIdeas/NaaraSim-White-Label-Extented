<?php

namespace App\Console\Commands;

use App\Models\PayoutCorridor;
use App\Services\Payouts\Hardening\PayoutEnvGuard;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Support\ProviderStatus;
use Illuminate\Console\Command;

/**
 * Corridors ship switched off, so a fresh install with working live keys still showed "coming soon" everywhere. This turns ON every corridor whose
 * gateway has keys, whose keys match the payout environment, and whose country the provider really covers (the rail registry: API or documentation).
 *
 * It never fights an admin: a corridor someone has saved since it was created (switched off, re-priced, edited) is left exactly as they set it.
 */
class PayoutsEnableReadyCorridorsCommand extends Command
{
    protected $signature = 'payouts:enable-ready-corridors {--dry : only list what would be switched on}';

    protected $description = 'Switch on payout corridors whose gateway is configured and whose country the provider covers (never touches ones an admin edited)';

    public function handle(PayoutRailRegistry $registry): int
    {
        $on = 0;
        $skipped = [];
        foreach (PayoutCorridor::query()->where('enabled', false)->get() as $c) {
            if ($c->updated_at && $c->created_at && $c->updated_at->gt($c->created_at->copy()->addSeconds(5))) {
                continue; // an admin has edited it since it was created
            }
            $rail = PayoutRailRegistry::railForProvider($c->provider);
            if ($rail === null || ! ProviderStatus::isActive($c->provider) || ! PayoutEnvGuard::allows($c->provider)) {
                $skipped[$c->provider] = true;

                continue;
            }
            if (! $registry->providerCovers($c->country, $rail)) {
                continue;
            }
            $this->line(($this->option('dry') ? 'would enable ' : 'enabled ')."{$c->provider} {$c->country} {$c->currency}");
            if (! $this->option('dry')) {
                $c->forceFill(['enabled' => true])->saveQuietly();
            }
            $on++;
        }
        if (! $this->option('dry')) {
            $registry->flush();
        }
        $this->info("{$on} corridor(s) ".($this->option('dry') ? 'ready' : 'switched on').($skipped ? '; waiting for keys or a matching environment: '.implode(', ', array_keys($skipped)) : ''));

        return self::SUCCESS;
    }
}
