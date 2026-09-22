<?php

namespace App\Livewire\Admin;

use App\Models\ApiOrder;
use App\Models\EsimOrder;
use App\Models\JobHeartbeat;
use App\Models\SmsOrder;
use App\Models\User;
use App\Services\Analytics\PlatformAnalyticsService;
use App\Services\NCI\NciScorer;
use App\Services\Routing\CandidateOrdering;
use App\Support\PaymentSandbox;
use App\Support\ProviderModels;
use App\Support\ProviderStatus;
use App\Support\StaffScopes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin overview (blueprint Sections 13.3, 17 & 27). super_admin/admin see the
 * provider health, product status, and a 30-day profit snapshot (cost figures
 * are admin-only). Staff see a scoped welcome — their granted scopes only, no
 * cost/profit — so the panel entry never leaks business figures to staff.
 */
#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public function render(PlatformAnalyticsService $analytics)
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return view('livewire.admin.dashboard', [
                'privileged' => false,
                'sandboxGateways' => PaymentSandbox::testGateways(),
                'myScopes' => array_values(array_intersect(
                    StaffScopes::all(),
                    $user->getPermissionNames()->all()
                )),
                'scopeLabels' => StaffScopes::labels(),
            ]);
        }

        // Analytics blueprint §7.2: revenue/profit is now PlatformAnalyticsService's
        // job, not inline queries here — every admin surface that needs these
        // numbers calls the same methods and can never disagree.
        $since = now()->subDays(30);

        // Hero revenue + the split donut: ALL FOUR product lanes, Naara Gift
        // included for the first time (§7.1 gap #1) — pure revenue, no cost
        // ambiguity, so folding it in here is unambiguously correct.
        $trend = $analytics->revenueTrend('30d');
        $revenue = $trend['current'];
        $revenueDelta = $trend['delta_pct'];
        $revenueBars = $analytics->dailyRevenueBars(7);

        $breakdown = $analytics->revenueBreakdown('30d');
        $split = $breakdown['segments'];
        $splitTotal = $breakdown['total'];

        // conic-gradient stops for the donut (computed here so the view stays dumb).
        $stops = [];
        $acc = 0.0;
        foreach ($split as $seg) {
            $pct = $splitTotal > 0 ? $seg['value'] / $splitTotal * 100 : 0;
            $stops[] = "{$seg['color']} {$acc}% ".($acc + $pct).'%';
            $acc += $pct;
        }
        $splitGradient = 'conic-gradient('.implode(', ', $stops).')';

        // Cost/profit/margin tiles: deliberately scoped to the two lanes with a
        // real, persisted per-order cost (see PlatformAnalyticsService::
        // profitWindow()'s doc block for why Naara Gift is excluded here).
        $coreRevenue = $analytics->coreRevenueWindow($since);
        $cost = $analytics->costWindow($since);
        $profit = $analytics->profitWindow($since);
        $margin = $coreRevenue > 0 ? round($profit / $coreRevenue * 100, 1) : 0.0;

        // ── Oversight metrics (owner request) ────────────────────────────────
        $weekStart = now()->subDays(7);
        $monthStart = now()->startOfMonth();

        // Users: total registered + new this week.
        $totalUsers = User::count();
        $newUsersWeek = User::where('created_at', '>=', $weekStart)->count();

        // Weekly / monthly profit (revenue − provider cost) — same core-lane scope as above.
        $profitWeek = $analytics->profitWindow($weekStart);
        $profitMonth = $analytics->profitWindow($monthStart);

        // Most-bought numbers by country (top 6, last 30 days).
        $topCountries = SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])
            ->whereNotNull('country')
            ->selectRaw('country, count(*) as n')
            ->groupBy('country')->orderByDesc('n')->limit(6)->get()
            ->map(fn ($r) => ['country' => $r->country, 'count' => (int) $r->n])->all();

        // Most-used NaaraSim models (by orders in the last 30 days).
        $modelCounts = [];
        foreach (EsimOrder::where('created_at', '>=', $since)->count() ? ['naara_data' => EsimOrder::where('created_at', '>=', $since)->count()] : [] as $k => $v) {
            $modelCounts[$k] = $v;
        }
        SmsOrder::where('created_at', '>=', $since)->whereNotIn('status', ['timeout', 'cancelled'])
            ->get(['type', 'provider'])
            ->each(function ($o) use (&$modelCounts) {
                $model = ProviderModels::forNumberType($o->type)
                    ?? ProviderModels::forProvider((string) $o->provider)
                    ?? ProviderModels::find('naara_verify');
                $key = $model['key'] ?? 'naara_verify';
                $modelCounts[$key] = ($modelCounts[$key] ?? 0) + 1;
            });
        arsort($modelCounts);
        $topModels = collect($modelCounts)->take(4)
            ->map(fn ($n, $key) => ['label' => ProviderModels::find($key)['name'] ?? $key, 'count' => $n])
            ->values()->all();

        // Developer API activity this week.
        $apiOrdersWeek = ApiOrder::where('created_at', '>=', $weekStart)->count();
        $apiRevenueWeek = round((float) ApiOrder::where('created_at', '>=', $weekStart)->sum('price_usd'), 2);

        $providerCards = $this->providerCards($analytics, $health = Cache::get('providers:health', []));

        // ── Tier 5 #15 Phase B: "supreme overview" additions ─────────────────
        // Only the tiles buildable in THIS fork without inventing data: item 1
        // (heartbeats) reuses infrastructure that already exists; item 3 (KYC
        // automation) is buildable now that Tier 5 #11 Phase C landed real
        // automated KYB. Item 4 (Hot Menu attention tile) is excluded here —
        // this fork has no Hot Menu (Tier 3 #9 Phase G, master-only). Items
        // 2/5/6 need a dependency that hasn't landed yet (a tracked
        // provider-wallet-topup ledger, recurring white-label billing, a
        // general fraud-signal service) — deferred rather than built against
        // invented/fake data.
        $hourAgo = now()->subHour();
        $jobsThisHour = JobHeartbeat::where('created_at', '>=', $hourAgo)->get(['outcome']);
        $jobsFailedThisHour = $jobsThisHour->where('outcome', 'failed')->count();

        $kycAutomation = $analytics->kycAutomationResolutionRate('30d');

        return view('livewire.admin.dashboard', [
            'privileged' => true,
            'sandboxGateways' => PaymentSandbox::testGateways(),
            'totalUsers' => $totalUsers,
            'newUsersWeek' => $newUsersWeek,
            'profitWeek' => $profitWeek,
            'profitMonth' => $profitMonth,
            'topCountries' => $topCountries,
            'topModels' => $topModels,
            'apiOrdersWeek' => $apiOrdersWeek,
            'apiRevenueWeek' => $apiRevenueWeek,
            'health' => $health,
            'statuses' => ProviderStatus::all(),
            'providerCards' => $providerCards,
            'jobsThisHourTotal' => $jobsThisHour->count(),
            'jobsFailedThisHour' => $jobsFailedThisHour,
            'kycAutomation' => $kycAutomation,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $margin,
            'revenueDelta' => $revenueDelta,
            'revenueBars' => $revenueBars,
            'barPeak' => max(array_column($revenueBars, 'value')) ?: 1,
            'split' => $split,
            'splitTotal' => $splitTotal,
            'splitGradient' => $splitGradient,
        ]);
    }

    /**
     * Tier 5 #15 Phase A — one card per currently-configured provider
     * (eSIM + number stacks alike). Reuses, never re-derives:
     * `ProviderModels::providerConfigured()` for the isConfigured() filter
     * (Tier 4 #10), `CandidateOrdering::order()` for display order (the
     * SAME live-ranked order real purchases route through — never a second
     * priority scheme), `NciScorer::dailySuccessRateTrend()` for the
     * sparkline, and `PlatformAnalyticsService::providerOrderVolume()` for
     * real order counts. An unconfigured provider never appears at all.
     *
     * @return list<array{provider: string, label: string, stack: string, trend: array, health: ?array, order_volume: int}>
     */
    private function providerCards(PlatformAnalyticsService $analytics, array $health): array
    {
        $ordering = app(CandidateOrdering::class);
        $nci = app(NciScorer::class);

        $lanes = [
            'esim' => array_values(array_unique([
                ...ProviderModels::MODELS['naara_data']['lane'],
                ...ProviderModels::MODELS['naara_connect']['lane'],
            ])),
            'sms' => ProviderModels::MODELS['naara_verify']['lane'],
            'permanent' => ProviderModels::MODELS['naara_line']['lane'],
        ];

        $cards = [];
        foreach ($lanes as $stack => $lane) {
            $configured = array_values(array_filter($lane, fn ($p) => ProviderModels::providerConfigured($p)));
            foreach ($ordering->order($configured, $stack) as $provider) {
                $cards[] = [
                    'provider' => $provider,
                    'label' => ProviderModels::providerLabel($provider),
                    'stack' => $stack,
                    'trend' => $nci->dailySuccessRateTrend($provider, 7),
                    'health' => $health[$provider] ?? null,
                    'order_volume' => $analytics->providerOrderVolume($stack, $provider, '30d'),
                ];
            }
        }

        return $cards;
    }
}
