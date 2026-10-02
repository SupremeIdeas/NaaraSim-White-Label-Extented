<?php

namespace App\Livewire\Admin;

use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\PayoutExposureSnapshot;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutFloatMovement;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Models\PayoutWithdrawalStatHourly;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Services\Payouts\FloatService;
use App\Services\Payouts\PayoutEligibility;
use App\Services\Payouts\Rail\FundingRadar;
use App\Services\Payouts\Rail\RadarSnapshotter;
use App\Services\Payouts\Rail\RailEnrollmentService;
use App\Services\Payouts\Rail\UnservedDemandReport;
use App\Services\Payouts\Rail\WithdrawableBalanceResolver;
use App\Support\Auditor;
use App\Support\Money4;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Global Payout Rail (Funding Radar, Addendum A §5). Who chose the global rail,
 * what they could withdraw, what is already committed, and how much to fund NEXT.
 * Exact numbers are labelled "Exact"; forecasts "Estimate". Reads the cached radar
 * (refreshed by the scheduler and by payout events), never the money tables on poll.
 */
#[Layout('components.layouts.admin')]
class GlobalPayoutRail extends Component
{
    private const ROW_CAP = 5000;

    public string $tab = 'overview';

    public string $provider = 'payoneer';

    public string $country = '';

    public string $currency = '';

    public string $status = '';

    public string $search = '';

    public string $sort = 'total';

    public bool $desc = true;

    public int $page = 1;

    public bool $sweepOnly = false;

    public bool $kycBlockedOnly = false;

    public $minBalance = '';

    public $maxBalance = '';

    // planner
    public string $topupCurrency = 'USD';

    public $topupAmount = '';

    public string $topupNote = '';

    public $whatIfPct = 50;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $providers = app(RadarSnapshotter::class)->providers();
        $this->provider = in_array($this->provider, $providers, true) ? $this->provider : ($providers[0] ?? 'payoneer');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function updated($name): void
    {
        if (! in_array($name, ['tab', 'page', 'topupCurrency', 'topupAmount', 'topupNote', 'whatIfPct'], true)) {
            $this->page = 1;
        }
    }

    public function sortBy(string $col): void
    {
        $this->desc = $this->sort === $col ? ! $this->desc : true;
        $this->sort = $col;
    }

    public function pause(int $id): void
    {
        $this->authorizeAdmin();
        app(RailEnrollmentService::class)->pause(PayoutRailEnrollment::findOrFail($id), Auth::user(), 'paused from the Funding Radar');
        $this->dispatch('nx-toast', type: 'success', message: 'Enrollment paused.');
    }

    public function resume(int $id): void
    {
        $this->authorizeAdmin();
        app(RailEnrollmentService::class)->resume(PayoutRailEnrollment::findOrFail($id), Auth::user(), 'resumed from the Funding Radar');
        $this->dispatch('nx-toast', type: 'success', message: 'Enrollment resumed.');
    }

    /** Record money put into the provider account (audited), then re-run the radar. */
    public function recordTopUp(FloatService $float, FundingRadar $radar): void
    {
        $this->authorizeAdmin();
        $this->validate(['topupCurrency' => 'required|alpha|size:3', 'topupAmount' => 'required|numeric|gt:0', 'topupNote' => 'required|string|min:3|max:200']);
        try {
            $float->recordTopUp($this->provider, $this->topupCurrency, (float) $this->topupAmount, Auth::user(), $this->topupNote);
        } catch (\Throwable $e) {
            $this->addError('topupAmount', $e->getMessage());

            return;
        }
        $radar->refreshCache($this->provider);
        $this->reset(['topupAmount', 'topupNote']);
        $this->dispatch('nx-toast', type: 'success', message: 'Top-up recorded.');
    }

    public function export(): StreamedResponse
    {
        $this->authorizeAdmin();
        Auditor::log('payout.rail_export', null, null, ['provider' => $this->provider, 'filters' => $this->only(['country', 'currency', 'status', 'search'])]);
        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['user_id', 'name', 'country', 'currency', 'provider', 'status', 'stripe_ineligible_reason', 'credits_usd', 'referral_usd', 'merchant_usd', 'partner_usd', 'staff_usd', 'total_usd', 'committed_usd', 'lifetime_paid_usd', 'destination']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['user_id'], $r['name'], $r['country'], $r['currency'], $r['provider'], $r['status'], $r['reason'],
                    $r['b']['credits'], $r['b']['referral'], $r['b']['merchant'], $r['b']['partner'], $r['b']['staff'], $r['total'], $r['committed'], $r['lifetime_paid'], $r['masked']]);
            }
            fclose($out);
        }, 'global-rail-users-'.now()->format('Ymd-His').'.csv');
    }

    /**
     * The filtered, sorted user list with per-user figures (one batch of queries, never per row).
     *
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $q = PayoutRailEnrollment::query()->with('user:id,name,email,country_code', 'account:id,account_number,lookup_hash')->where('provider', $this->provider)
            ->when($this->country !== '', fn ($q) => $q->where('country', strtoupper($this->country)))
            ->when($this->currency !== '', fn ($q) => $q->where('currency', strtoupper($this->currency)))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')));
        $enrollments = $q->limit(self::ROW_CAP)->get();

        $ids = $enrollments->pluck('user_id');
        $balances = app(WithdrawableBalanceResolver::class)->forUsers($ids);
        $committed = PayoutRequest::whereIn('user_id', $ids)->where('provider', $this->provider)->whereIn('status', [PayoutRequest::PENDING, PayoutRequest::APPROVED, PayoutRequest::AWAITING_FUNDS, PayoutRequest::PROCESSING])
            ->selectRaw('user_id, SUM(usd_amount) as s')->groupBy('user_id')->pluck('s', 'user_id');
        $paid = PayoutRequest::whereIn('user_id', $ids)->where('provider', $this->provider)->where('status', PayoutRequest::PAID)
            ->selectRaw('user_id, SUM(usd_amount) as s, MAX(settled_at) as last')->groupBy('user_id')->get()->keyBy('user_id');
        $kyc = KycVerification::whereIn('user_id', $ids)->where('status', KycVerification::APPROVED)->selectRaw('user_id, MAX(level) as l')->groupBy('user_id')->pluck('l', 'user_id');
        $cooling = \App\Models\AuditLog::whereIn('user_id', $ids)->whereIn('action', ['account.password_changed', 'account.2fa_enabled', 'account.2fa_disabled', 'payout.account_added'])
            ->where('created_at', '>=', now()->subHours(\App\Support\PayoutSettings::coolingOffHours()))->distinct()->pluck('user_id')->flip();
        $eligibility = app(PayoutEligibility::class);

        $rows = [];
        foreach ($enrollments as $e) {
            $b = $balances[$e->user_id] ?? ['credits' => '0.0000', 'referral' => '0.0000', 'merchant' => '0.0000', 'partner' => '0.0000', 'staff' => '0.0000', 'total' => '0.0000'];
            $total = Money4::units($b['total']);
            $sweep = $e->user ? $eligibility->nextSweep($e->user, Money4::float($total), $e) : ['eligible' => false, 'reason' => 'no_user'];

            $rows[] = [
                'enrollment_id' => $e->id, 'user_id' => $e->user_id, 'name' => $e->user?->name ?? '—', 'email' => $e->user?->email,
                'country' => $e->country, 'currency' => $e->currency ?: '—', 'provider' => $e->provider, 'status' => $e->status,
                'reason' => $e->ineligible_reason, 'b' => $b, 'total' => $b['total'], 'units' => $total,
                'committed' => Money4::str(Money4::units($committed[$e->user_id] ?? 0)),
                'lifetime_paid' => Money4::str(Money4::units($paid[$e->user_id]->s ?? 0)),
                'last_withdrawal' => $paid[$e->user_id]->last ?? null,
                'kyc' => (int) ($kyc[$e->user_id] ?? 1), 'cooling' => isset($cooling[$e->user_id]),
                'will_sweep' => $sweep['eligible'], 'kyc_blocked' => $sweep['reason'] === 'kyc_required',
                'masked' => $e->account?->masked_number ?? '—',
            ];
        }

        $min = $this->minBalance !== '' ? Money4::units($this->minBalance) : null;
        $max = $this->maxBalance !== '' ? Money4::units($this->maxBalance) : null;
        $rows = array_values(array_filter($rows, fn ($r) => ($min === null || $r['units'] >= $min) && ($max === null || $r['units'] <= $max)
            && (! $this->sweepOnly || $r['will_sweep']) && (! $this->kycBlockedOnly || $r['kyc_blocked'])));

        $key = ['total' => 'units', 'committed' => 'committed', 'lifetime_paid' => 'lifetime_paid', 'name' => 'name', 'country' => 'country', 'status' => 'status', 'kyc' => 'kyc'][$this->sort] ?? 'units';
        usort($rows, fn ($a, $b) => ($this->desc ? -1 : 1) * (is_numeric($a[$key]) || is_numeric($b[$key]) ? (float) $a[$key] <=> (float) $b[$key] : strcmp((string) $a[$key], (string) $b[$key])));

        return $rows;
    }

    private function totals(array $rows): array
    {
        $t = ['users' => count($rows), 'credits' => 0, 'referral' => 0, 'merchant' => 0, 'partner' => 0, 'staff' => 0, 'total' => 0, 'committed' => 0];
        foreach ($rows as $r) {
            foreach (['credits', 'referral', 'merchant', 'partner', 'staff'] as $k) {
                $t[$k] += Money4::units($r['b'][$k]);
            }
            $t['total'] += $r['units'];
            $t['committed'] += Money4::units($r['committed']);
        }

        $out = [];
        foreach ($t as $k => $v) {
            $out[$k] = $k === 'users' ? $v : Money4::str($v);
        }

        return $out;
    }

    public function render()
    {
        $this->authorizeAdmin();
        $radar = app(FundingRadar::class);
        $providers = app(RadarSnapshotter::class)->providers();
        $data = ['providers' => $providers, 'tab' => $this->tab, 'overview' => $radar->cached($this->provider), 'lastSnapshot' => \Illuminate\Support\Facades\Cache::get('payout:radar:last_snapshot')];

        if ($this->tab === 'users') {
            $rows = $this->rows();
            $data += ['rows' => array_slice($rows, ($this->page - 1) * 25, 25), 'pages' => max(1, (int) ceil(count($rows) / 25)), 'totals' => $this->totals($rows)];
        }
        if ($this->tab === 'live') {
            $data += $this->live();
        }
        if ($this->tab === 'planner') {
            $data += $this->planner();
        }
        if ($this->tab === 'unserved') {
            $data['unserved'] = app(UnservedDemandReport::class)->get();
        }

        return view('livewire.admin.global-payout-rail', $data);
    }

    /** @return array<string, mixed> */
    private function live(): array
    {
        $since = now()->subDay();
        $stats = PayoutWithdrawalStatHourly::where('provider', $this->provider)->where('hour_start', '>=', $since)->get();
        $byHour = $stats->groupBy(fn ($s) => $s->hour_start->format('H:00'))->map(fn ($g) => ['requested' => (float) $g->sum('requested_usd'), 'paid' => (float) $g->sum('paid_usd')]);
        $recent = PayoutRequest::with('user:id,name,email')->where('provider', $this->provider)->latest('id')->limit(25)->get();
        $accts = PayoutAccount::whereIn('id', $recent->pluck('payout_account_id'))->pluck('country', 'id');
        $paidN = $stats->sum('paid_count');
        $failN = $stats->sum('failed_count');
        $amounts = PayoutRequest::where('provider', $this->provider)->where('created_at', '>=', now()->subDays(30))->pluck('usd_amount')->filter()->map(fn ($v) => (float) $v)->sort()->values();
        $median = $amounts->isEmpty() ? 0 : $amounts[intdiv($amounts->count(), 2)];
        $enrollments = PayoutRailEnrollment::where('provider', $this->provider)->get();
        $exposure = $this->radarTop($enrollments);
        $h1 = PayoutRequest::where('provider', $this->provider)->where('created_at', '>=', now()->subHour())->count();
        $d1 = PayoutRequest::where('provider', $this->provider)->where('created_at', '>=', now()->subDay())->count();
        $avg7 = PayoutRequest::where('provider', $this->provider)->whereBetween('created_at', [now()->subDays(7), now()])->count() / 7;

        return [
            'feed' => $recent->map(fn ($r) => ['at' => $r->created_at, 'user' => $r->user?->email, 'country' => $accts[$r->payout_account_id] ?? '—', 'usd' => $r->usd_amount, 'local' => $r->amount.' '.$r->currency, 'status' => $r->status]),
            'byHour' => $byHour,
            'byCountry' => $stats->groupBy('country')->map(fn ($g) => (float) $g->sum('requested_usd'))->sortDesc()->take(10),
            'successRate' => ($paidN + $failN) > 0 ? round($paidN / ($paidN + $failN) * 100, 1) : null,
            'avgTimeToPaid' => (int) round((float) $stats->whereNotNull('avg_time_to_paid_sec')->avg('avg_time_to_paid_sec')),
            'failureReasons' => PayoutRequest::where('provider', $this->provider)->whereIn('status', [PayoutRequest::FAILED, PayoutRequest::REVERSED])->whereNotNull('failure_reason')->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('failure_reason, COUNT(*) as n')->groupBy('failure_reason')->orderByDesc('n')->limit(5)->pluck('n', 'failure_reason'),
            'avgSize' => $amounts->isEmpty() ? 0 : round($amounts->avg(), 2), 'medianSize' => round($median, 2),
            'newEnrollments' => $enrollments->filter(fn ($e) => $e->selected_at?->gte(now()->subDays(14)))->groupBy(fn ($e) => $e->selected_at->format('M j'))->map->count(),
            'top' => $exposure, 'velocity' => ['h1' => $h1, 'd1' => $d1, 'avg7' => round($avg7, 1), 'flag' => $h1 > max(3, 2 * $avg7 / 24 * 24)],
        ];
    }

    /** Top 10 users by balance and their share of max exposure. */
    private function radarTop($enrollments): array
    {
        $balances = app(WithdrawableBalanceResolver::class)->forUsers($enrollments->pluck('user_id'));
        $max = array_sum(array_map(fn ($b) => Money4::units($b['total']), $balances));
        $names = User::whereIn('id', array_keys($balances))->pluck('name', 'id');

        return collect($balances)->map(fn ($b, $id) => ['name' => $names[$id] ?? "#{$id}", 'usd' => $b['total'], 'share' => $max > 0 ? round(Money4::units($b['total']) / $max * 100, 1) : 0.0])
            ->sortByDesc('share')->take(10)->values()->all();
    }

    /** @return array<string, mixed> */
    private function planner(): array
    {
        $o = app(FundingRadar::class)->cached($this->provider);
        $floats = PayoutFloatBalance::where('provider', $this->provider)->get();
        $paid14 = (float) PayoutWithdrawalStatHourly::where('provider', $this->provider)->where('hour_start', '>=', now()->subDays(14))->sum('paid_usd');
        $perDay = $paid14 / 14;
        $history = PayoutExposureSnapshot::where('provider', $this->provider)->whereNull('country')->where('captured_at', '>=', now()->subDays(14))->orderBy('captured_at')->get(['captured_at', 'recommended_topup_p50_usd', 'float_available_usd'])
            ->groupBy(fn ($s) => $s->captured_at->format('M j'))->map(fn ($g) => ['recommended' => (float) $g->last()->recommended_topup_p50_usd, 'float' => $g->last()->float_available_usd]);

        return [
            'planner' => [
                'by_currency' => $o['by_currency'], 'float_rows' => $floats->map(fn ($f) => [
                    'currency' => $f->currency, 'balance' => (string) $f->balance,
                    'last_topup' => PayoutFloatMovement::where('provider', $f->provider)->where('currency', $f->currency)->where('type', 'topup')->latest('id')->first(),
                ]),
                'coverage_days' => ($o['float_available'] !== null && $perDay > 0) ? round((float) $o['float_available'] / $perDay, 1) : null,
                'what_if' => Money4::str((int) round(Money4::units($o['max_exposure']) * max(0, min(100, (float) $this->whatIfPct)) / 100)),
                'history' => $history,
            ],
        ];
    }
}
