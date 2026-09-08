<?php

namespace App\Livewire;

use App\Models\MerchantEarning;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Merchant earnings analytics (BUILD-7 §3). A pure reporting layer over the
 * existing MerchantEarning ledger — a time-series, a breakdown by product line
 * (source_type) and a top-customers list. No new data model, no change to how
 * earnings accrue.
 */
#[Layout('components.layouts.customer')]
class MerchantEarnings extends Component
{
    /** Window in days: 30 | 90 | 365. */
    public int $period = 30;

    private \App\Models\Merchant $merchant;

    public function mount(): void
    {
        $merchant = Auth::user()?->merchantAccount;
        abort_unless($merchant && $merchant->isActive(), 404);
        $this->merchant = $merchant;
    }

    public function setPeriod(int $days): void
    {
        $this->period = in_array($days, [30, 90, 365], true) ? $days : 30;
    }

    public function render()
    {
        $since = Carbon::now()->subDays($this->period)->startOfDay();
        $rows = MerchantEarning::where('merchant_id', $this->merchant->id)
            ->where('type', MerchantEarning::ACCRUAL)
            ->where('created_at', '>=', $since)
            ->get(['amount', 'source_type', 'source_user_id', 'created_at']);

        // Daily time-series (fill gaps so the chart is continuous).
        $byDay = $rows->groupBy(fn ($r) => $r->created_at->toDateString())
            ->map(fn ($g) => round((float) $g->sum('amount'), 2));
        $series = [];
        for ($d = 0; $d < $this->period; $d++) {
            $day = Carbon::now()->subDays($this->period - 1 - $d)->toDateString();
            $series[$day] = $byDay[$day] ?? 0.0;
        }

        // Breakdown by product line.
        $bySource = $rows->groupBy('source_type')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc();

        // Top customers by margin generated.
        $topIds = $rows->whereNotNull('source_user_id')->groupBy('source_user_id')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))->sortDesc()->take(5);
        $names = User::whereIn('id', $topIds->keys())->pluck('name', 'id');
        $top = $topIds->map(fn ($amt, $id) => ['name' => $names[$id] ?? 'Customer #'.$id, 'amount' => $amt]);

        return view('livewire.merchant-earnings', [
            'total' => round((float) $rows->sum('amount'), 2),
            'series' => $series,
            'bySource' => $bySource,
            'top' => $top,
        ]);
    }
}
