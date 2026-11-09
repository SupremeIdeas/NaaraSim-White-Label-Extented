<?php

namespace App\Jobs\Topup;

use App\Models\Setting;
use App\Services\Topup\ProviderRouter;
use App\Services\Topup\TopupCatalogue;
use App\Services\Topup\TopupLiveRates;
use App\Services\Topup\TopupOps;
use App\Services\Topup\TopupProviders;
use App\Support\TopupSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The top-up layer on autopilot. Scheduled every ten minutes (`topup:run autopilot`), it makes a FRESH install come up green without anyone running a command:
 * suppliers, default routes and the shipped catalogue snapshot are created; the first live catalogue sync runs as soon as a supplier has keys (not at 03:40 the
 * next night); and once a day every sellable country is checked live against every supplier (cost, our price, margin, par, cheapest) with the summary stored for
 * the admin and a single alert when a margin guard or a supplier fault shows up. Cheap no-op when no supplier is configured. Never throws into the scheduler.
 */
class TopupAutopilotJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function handle(TopupOps $ops, TopupCatalogue $catalogue, ProviderRouter $router, TopupLiveRates $live): void
    {
        try {
            $ops->seed();
        } catch (Throwable) {
            return;
        }
        $eligible = array_values(array_filter(TopupProviders::codes(), fn ($c) => $router->eligible($c)));
        if ($eligible === []) {
            return;                                           // nothing to sync or compare until a supplier is switched on with keys
        }

        if ($this->stale('topup.last_sync_at', 26)) {
            try {
                $catalogue->syncAll();
            } catch (Throwable) {
            }
            Setting::setValue('topup.last_sync_at', now()->toIso8601String());
        }

        if ($this->stale('topup.live_rates_at', 24)) {
            $this->liveCheck($catalogue, $live);
        }
    }

    private function liveCheck(TopupCatalogue $catalogue, TopupLiveRates $live): void
    {
        $summary = ['at' => now()->toIso8601String(), 'countries' => []];
        $bad = [];
        foreach ($this->priorityCountries($catalogue) as $iso) {
            foreach (['airtime', 'data'] as $type) {
                try {
                    $s = $live->run($iso, $type, $type === 'data' ? [] : ($iso === 'NG' ? [500, 1000, 2000, 5000] : [5, 10, 20, 50]), force: true);
                } catch (Throwable) {
                    continue;
                }
                $row = &$summary['countries'][$iso][$type];
                $row = ['providers' => array_map(fn ($p) => $p['state'], $s['providers']), 'rows' => count($s['rows']), 'alerts' => count($s['alerts'])];
                foreach ($s['alerts'] as $a) {
                    if ($a['level'] === 'bad') {
                        $bad[] = "$iso $type: ".$a['text'];
                    }
                }
                unset($row);
            }
        }
        $summary['bad'] = array_slice($bad, 0, 20);
        Setting::setValue('topup.live_rates_last', $summary);
        Setting::setValue('topup.live_rates_at', now()->toIso8601String());

        // One alert per distinct set of problems, never a repeat every day for the same thing.
        $sig = md5(json_encode($summary['bad']));
        if ($bad !== [] && $sig !== Setting::getValue('topup.live_rates_alert_sig')) {
            \App\Jobs\AlertAdminJob::dispatch(code: 'topup_margin_guard', message: count($bad).' top-up price(s) are under the minimum profit. First: '.$bad[0], context: ['count' => count($bad)]);
        }
        Setting::setValue('topup.live_rates_alert_sig', $sig);
    }

    /** The 15 countries that matter first: where customers actually buy (last 30 days), then the markets we lead with, then the rest alphabetically. */
    private function priorityCountries(TopupCatalogue $catalogue): array
    {
        $all = $catalogue->countries();
        $bought = \App\Models\TopupOrder::query()->where('created_at', '>=', now()->subDays(30))->selectRaw('country_iso, count(*) c')->groupBy('country_iso')->orderByDesc('c')->pluck('country_iso')->map('strtoupper')->all();
        $lead = ['NG', 'GH', 'KE', 'ZA', 'UG', 'TZ', 'CM', 'SN', 'CI', 'US', 'GB'];
        $rank = array_flip(array_merge($bought, $lead));

        return $all->sortBy(fn ($c) => [$rank[$c] ?? 9999, $c])->values()->take(15)->all();
    }

    private function stale(string $key, int $hours): bool
    {
        $at = Setting::getValue($key);

        return ! $at || \Illuminate\Support\Carbon::parse((string) $at)->lte(now()->subHours($hours));
    }
}
