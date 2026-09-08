<?php

namespace App\Livewire;

use App\Models\Partner;
use App\Models\PartnerEarning;
use App\Models\PayoutRequest;
use App\Services\Partners\PartnerEarningsService;
use App\Services\Partners\PartnerPayoutService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Partner earnings (customer app). Shows a partner their profit-share balance in
 * DOLLAR FIGURES ONLY — the profit-share percentage is admin-confidential and is
 * never computed or rendered here. Read-only: the scheduled run pays them on
 * their cadence; this view surfaces balance, history, payout status and the next
 * payout date. 404s for a non-partner.
 */
#[Layout('components.layouts.customer')]
class PartnerEarnings extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->partnerAccount !== null, 404);
    }

    public function render()
    {
        /** @var Partner $partner */
        $partner = Auth::user()->partnerAccount;

        $balance = app(PartnerEarningsService::class)->balance($partner);

        // Ledger: accruals (a period's earnings) + holds/releases (payouts).
        $ledger = PartnerEarning::where('partner_id', $partner->id)->latest('id')->limit(20)->get();

        $lifetime = (float) PartnerEarning::where('partner_id', $partner->id)
            ->where('type', PartnerEarning::ACCRUAL)->sum('amount');

        $payouts = PayoutRequest::where('user_id', $partner->owner_user_id)
            ->where('source_bucket', 'partner_earnings')->latest('id')->limit(8)->get();

        $next = app(PartnerPayoutService::class)->nextDuePeriod($partner);
        // Next payout runs just after the next period closes.
        $nextDate = $partner->last_period_end
            ? ($partner->payout_cadence === Partner::CADENCE_WEEKLY
                ? $partner->last_period_end->copy()->addWeek()
                : $partner->last_period_end->copy()->addMonthNoOverflow())
            : null;

        return view('livewire.partner-earnings', [
            'partner' => $partner,
            'balance' => $balance,
            'lifetime' => round($lifetime, 2),
            'ledger' => $ledger,
            'payouts' => $payouts,
            'nextDate' => $nextDate,
            'suspended' => ! $partner->isActive(),
        ]);
    }
}
