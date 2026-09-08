<?php

namespace App\Livewire\Admin;

use App\Support\FinancialReconciliation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Finance → Reconciliation (BUILD-5 §4). One period view of money in
 * (by gateway) vs. wallet credits vs. paid out vs. provider costs, built on the
 * wallet_transactions ledger — so an operator can catch an unaccounted-for gap
 * (e.g. a silently-failed top-up webhook) early. Super-admin / admin only;
 * cost figures are allowed here (admin-only, never user-facing).
 */
#[Layout('components.layouts.admin')]
class Reconciliation extends Component
{
    /** Look-back window in days (7 / 30 / 90). */
    public int $days = 30;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    public function render(FinancialReconciliation $recon)
    {
        $report = $recon->report(now()->subDays($this->days)->startOfDay(), now());

        return view('livewire.admin.reconciliation', ['report' => $report]);
    }
}
