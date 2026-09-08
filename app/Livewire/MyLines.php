<?php

namespace App\Livewire;

use App\Services\Analytics\ConnectivityAnalyticsService;
use App\Support\ConnectivityHub;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * My Lines (numbers overhaul follow-up) — the dedicated management home for
 * everything a user owns: active eSIMs (with QR/LPA setup + data meter), active
 * numbers grouped by Model (Naara Line / Rent / Verify) with per-line Call /
 * Message actions, and an Archive holding expired eSIMs and cancelled numbers as
 * a record. Route is numbers.lines so it inherits the Numbers section chrome
 * (§2 nav + wallet header). Reads the shared ConnectivityHub — no logic of its
 * own — so it can never disagree with the dashboard's summary.
 */
#[Layout('components.layouts.customer')]
class MyLines extends Component
{
    public function render(ConnectivityAnalyticsService $analytics)
    {
        $hub = ConnectivityHub::for(auth()->user());

        // Connectivity Analytics blueprint Part A §2.6 — per-eSIM burn rate +
        // 30-day usage timeline for the expandable "Usage" panel. Computed
        // only for the (already-limited) active eSIMs on this page, never
        // eagerly for the whole account.
        $usageByEsim = $hub['esimsActive']->mapWithKeys(fn ($esim) => [
            $esim->id => [
                'burn' => $analytics->usageBurnRate($esim),
                'timeline' => $analytics->usageTimeline($esim, '30d'),
            ],
        ]);

        $user = auth()->user();

        return view('livewire.my-lines', [
            'esimsActive' => $hub['esimsActive'],
            'esimsArchived' => $hub['esimsArchived'],
            'numberGroups' => $hub['numberGroups'],
            'numbersArchived' => $hub['numbersArchived'],
            'hasAny' => $hub['hasAny'],
            'usageByEsim' => $usageByEsim,
        ] + $this->analyticsPanel($user, $hub['hasAny'], $analytics));
    }

    /**
     * "My Analytics" panel (Connectivity Analytics blueprint Part A §2.4/2.7):
     * the 4 aggregate views not already covered by the per-eSIM usage panel
     * above — plan mix, purchase cadence, spend by public Model, and deposit
     * history. Collapsed by default in the Blade view so Chart.js is only
     * ever fetched once a user actually opens it.
     */
    private function analyticsPanel($user, bool $hasAny, ConnectivityAnalyticsService $analytics): array
    {
        if (! $hasAny) {
            return [];
        }

        return [
            'planMix' => $analytics->planMixBreakdown($user, '90d'),
            'purchaseCadence' => $analytics->purchaseCadence($user, '90d'),
            'spendBreakdown' => $analytics->walletSpendBreakdown($user, '30d'),
            'topUpHistory' => $analytics->topUpHistory($user, '90d'),
        ];
    }
}
