<?php

namespace App\Livewire;

use App\Jobs\AlertAdminJob;
use App\Models\VirtualNumber;
use App\Services\Analytics\ConnectivityAnalyticsService;
use App\Support\Auditor;
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
    /**
     * Prompt 10 — the consumer auto-renewal opt-out toggle. Owner-scoped
     * (a number id arriving from the client must never let a user flip
     * another user's line), and idempotent to call twice with the same
     * value. Never touches the wallet or billing state directly — it only
     * sets the flag `RenewVirtualNumbersCommand` reads on its next run.
     */
    public function toggleAutoRenew(int $virtualNumberId): void
    {
        $line = VirtualNumber::where('user_id', auth()->id())
            ->whereIn('status', ['active', 'past_due'])
            ->find($virtualNumberId);

        if ($line === null) {
            return;
        }

        $line->update(['auto_renew' => ! $line->auto_renew]);
        Auditor::log(
            $line->auto_renew ? 'line.auto_renew_enabled' : 'line.auto_renew_disabled',
            'VirtualNumber', $line->id,
        );

        $this->dispatch('nx-toast', type: 'success', message: $line->auto_renew
            ? 'Auto-renew is back on for '.$line->phone_number.'.'
            : 'Auto-renew is off — '.$line->phone_number.' will end on its next billing date unless you turn it back on.');
    }

    /**
     * Prompt 11 — port-out / right-to-leave. A customer can take their US/Canada
     * Naara Line to another carrier; we record the request, alert ops to send
     * the details their new carrier needs, and never obstruct the transfer.
     * Owner-scoped and idempotent. Only +1 lines are portable via our providers
     * (the audit's honest finding) — a non-+1 line is refused up front rather
     * than promising a port we can't facilitate.
     */
    public function requestPortOut(int $virtualNumberId): void
    {
        $line = VirtualNumber::where('user_id', auth()->id())
            ->whereIn('status', ['active', 'past_due'])
            ->find($virtualNumberId);

        if ($line === null) {
            return;
        }

        if (! $line->isUsCanada()) {
            $this->dispatch('nx-toast', type: 'error',
                message: 'Only US & Canada numbers can be ported to another carrier.');

            return;
        }

        if ($line->port_out_requested_at !== null) {
            $this->dispatch('nx-toast', type: 'info',
                message: 'We already have your port-out request for '.$line->phone_number.' — check your email.');

            return;
        }

        $line->update(['port_out_requested_at' => now()]);
        Auditor::log('line.port_out_requested', 'VirtualNumber', $line->id);
        AlertAdminJob::dispatch(
            code: 'line_port_out_requested',
            message: 'A customer requested a port-out for a Naara Line.',
            context: ['user_id' => auth()->id(), 'virtual_number_id' => $line->id, 'phone_number' => $line->phone_number],
            severity: 'info',
        );

        $this->dispatch('nx-toast', type: 'success',
            message: 'Port-out requested — we\'ll email you everything your new carrier needs. We won\'t block the transfer.');
    }

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
