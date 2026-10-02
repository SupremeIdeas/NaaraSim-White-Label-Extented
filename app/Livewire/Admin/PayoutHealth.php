<?php

namespace App\Livewire\Admin;

use App\Models\PayoutInvariantRun;
use App\Models\PayoutReconciliationItem;
use App\Models\PayoutReconciliationRun;
use App\Models\PayoutRequest;
use App\Models\PayoutUserFreeze;
use App\Models\PayoutWebhookEvent;
use App\Services\Payouts\Hardening\FxGuard;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\Hardening\PayoutInvariants;
use App\Services\Payouts\Hardening\SettlementReconciler;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutReconciler;
use App\Services\Payouts\PayoutService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Payouts → Health & operations. Where the Addendum D safety nets become things a human can see and do:
 * the nightly money-invariants result, requests that need a decision (changed destination, unknown outcome,
 * manual payouts to settle), "mark returned", frozen payees, settlement reconciliation, the accounting export and
 * the FX rate acceptance. Every action needs a note and is audited; money is never changed without one.
 */
#[Layout('components.layouts.admin')]
class PayoutHealth extends Component
{
    use WithFileUploads;

    /** @var array<int, string> request id => note */
    public array $notes = [];

    /** @var array<int, string> request id => proof reference (manual payouts) */
    public array $proofs = [];

    public string $returnedId = '';

    public string $returnedEvidence = '';

    public string $reconProvider = 'paystack';

    public string $reconFrom = '';

    public string $reconTo = '';

    public $statement = null;

    /** @var array<int, string> reconciliation item id => note */
    public array $itemNotes = [];

    public string $exportMonth = '';

    public string $fxCurrency = '';

    public string $fxRate = '';

    public string $releaseNote = '';

    public string $clawQuery = '';

    public string $clawReason = '';

    /** @var list<array<string, mixed>> */
    public array $clawResults = [];

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless($this->mayReview() || $this->mayFinance(), 403);
        $this->reconFrom = now()->subDays(7)->toDateString();
        $this->reconTo = now()->toDateString();
        $this->exportMonth = now()->format('Y-m');
    }

    private function mayReview(): bool
    {
        $u = Auth::user();

        return (bool) ($u?->hasAnyRole(['super_admin', 'admin']) || $u?->can('payouts.review'));
    }

    private function mayFinance(): bool
    {
        $u = Auth::user();

        return (bool) ($u?->hasAnyRole(['super_admin', 'admin']) || $u?->can('payouts.finance'));
    }

    private function review(): void
    {
        abort_unless($this->mayReview(), 403);
        $this->message = $this->error = null;
    }

    private function finance(): void
    {
        abort_unless($this->mayFinance(), 403);
        $this->message = $this->error = null;
    }

    private function note(int $id): ?string
    {
        $n = trim($this->notes[$id] ?? '');
        if ($n === '') {
            $this->error = 'Please add a note first — it becomes the audit trail.';

            return null;
        }

        return $n;
    }

    private function run(callable $fn, string $ok): void
    {
        try {
            $out = $fn();
            $this->message = is_string($out) && $out !== '' ? $out : $ok;
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function runInvariants(): void
    {
        $this->review();
        $run = app(PayoutInvariants::class)->run('admin:'.Auth::id());
        $this->message = $run->status === 'ok' ? 'All checks passed.' : $run->violation_count.' problem(s) found — details below.';
    }

    public function acceptDestination(int $id): void
    {
        $this->review();
        if (($n = $this->note($id)) === null) {
            return;
        }
        $this->run(fn () => app(PayoutService::class)->acceptDestinationChange(PayoutRequest::findOrFail($id), Auth::user(), $n), 'Destination accepted — the request is back in the review queue.');
    }

    public function rejectHeld(int $id): void
    {
        $this->review();
        if (($n = $this->note($id)) === null) {
            return;
        }
        $this->run(fn () => app(PayoutService::class)->reject(PayoutRequest::findOrFail($id), Auth::user(), 'Declined: destination changed', $n), 'Request declined and the funds returned.');
    }

    public function resolveUnknown(int $id, string $outcome): void
    {
        $this->review();
        if (($n = $this->note($id)) === null) {
            return;
        }
        $this->run(fn () => app(PayoutReconciler::class)->adminResolve(PayoutRequest::findOrFail($id), Auth::user(), $outcome, $n), 'Resolved as '.$outcome.'.');
    }

    public function recordManual(int $id): void
    {
        $this->review();
        if (($n = $this->note($id)) === null) {
            return;
        }
        $this->run(fn () => app(PayoutService::class)->recordManualPayment(PayoutRequest::findOrFail($id), Auth::user(), (string) ($this->proofs[$id] ?? ''), $n), 'Payment recorded and the payout marked delivered.');
    }

    public function markReturned(): void
    {
        $this->review();
        $r = PayoutRequest::find((int) $this->returnedId);
        if ($r === null) {
            $this->error = 'No payout with that number.';

            return;
        }
        $this->run(function () use ($r) {
            $out = app(PayoutService::class)->markReturned($r, $this->returnedEvidence, Auth::user());
            if ($out->status !== PayoutRequest::RETURNED) {
                throw new PayoutException('Only a payout that is marked delivered (paid) can be marked returned.');
            }
        }, 'Marked returned: the money went back to the user and their payout account was flagged.');
        if ($this->error === null) {
            $this->reset('returnedId', 'returnedEvidence');
        }
    }

    public function releaseFreeze(int $userId): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
        $this->message = $this->error = null;
        if (trim($this->releaseNote) === '') {
            $this->error = 'Add a note explaining why this payee is cleared.';

            return;
        }
        app(PayoutFreeze::class)->release(\App\Models\User::findOrFail($userId), Auth::user(), $this->releaseNote);
        $this->releaseNote = '';
        $this->message = 'Payouts re-enabled for that payee.';
    }

    public function reconcile(): void
    {
        $this->finance();
        $this->validate(['statement' => 'required|file|max:5120|mimes:csv,txt', 'reconProvider' => 'required|string|max:40', 'reconFrom' => 'required|date', 'reconTo' => 'required|date|after_or_equal:reconFrom']);
        $this->run(function () {
            $run = app(SettlementReconciler::class)->reconcileCsv($this->reconProvider, (string) file_get_contents($this->statement->getRealPath()), Carbon::parse($this->reconFrom), Carbon::parse($this->reconTo), Auth::user());
            return "Reconciled: {$run->matched} matched, {$run->flagged} to review. Nothing was changed automatically.";
        }, 'Reconciled.');
        $this->reset('statement');
    }

    public function resolveItem(int $id): void
    {
        $this->finance();
        $this->run(fn () => app(SettlementReconciler::class)->resolve(PayoutReconciliationItem::findOrFail($id), Auth::user(), (string) ($this->itemNotes[$id] ?? '')), 'Item resolved.');
    }

    public function exportAccounting(): StreamedResponse
    {
        $this->finance();
        $this->validate(['exportMonth' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        \App\Support\Auditor::log('payout.accounting_exported', 'PayoutAccountingEntry', 0, ['month' => $this->exportMonth, 'by' => Auth::id()]);
        $month = $this->exportMonth;

        return response()->streamDownload(function () use ($month) {
            \Illuminate\Support\Facades\Artisan::call('payouts:accounting-export', ['month' => $month, '--out' => 'php://output']);
        }, "payout-accounting-{$month}.csv", ['Content-Type' => 'text/csv']);
    }

    public function findEarnings(): void
    {
        $this->finance();
        $this->clawResults = app(\App\Services\Payouts\Hardening\EarningsClawback::class)->candidates($this->clawQuery);
        if ($this->clawResults === []) {
            $this->error = 'No earnings found for that buyer email or order reference.';
        }
    }

    public function reverseEarnings(string $kind, int $id): void
    {
        $this->finance();
        $this->run(function () use ($kind, $id) {
            $amt = app(\App\Services\Payouts\Hardening\EarningsClawback::class)->reverse($kind, $id, Auth::user(), $this->clawReason);
            $this->clawResults = app(\App\Services\Payouts\Hardening\EarningsClawback::class)->candidates($this->clawQuery);

            return 'Reversed $'.number_format($amt, 2).'. If the earner has already withdrawn it, their balance now shows an adjustment that future earnings repay.';
        }, 'Reversed.');
    }

    public function acceptFx(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
        $this->message = $this->error = null;
        $this->validate(['fxCurrency' => 'required|alpha|size:3', 'fxRate' => 'required|numeric|gt:0']);
        app(FxGuard::class)->accept($this->fxCurrency, (float) $this->fxRate);
        \App\Support\Auditor::log('payout.fx_rate_accepted', 'Setting', 0, ['currency' => strtoupper($this->fxCurrency), 'rate' => $this->fxRate, 'by' => Auth::id()]);
        $this->message = strtoupper($this->fxCurrency).' rate accepted as the new reference.';
        $this->reset('fxCurrency', 'fxRate');
    }

    public function render()
    {
        $held = PayoutRequest::query()->whereIn('hold_reason', ['account_changed_after_request', 'account_deleted_after_request'])->whereIn('status', PayoutRequest::OPEN)->with('user:id,name,email')->latest('id')->limit(50)->get();
        $unknown = PayoutRequest::query()->where('hold_reason', 'unknown_outcome')->whereIn('status', PayoutRequest::OPEN)->with('user:id,name,email')->latest('id')->limit(50)->get();
        $manual = PayoutRequest::query()->where('provider', 'manual_external')->where('status', PayoutRequest::PROCESSING)->with('user:id,name,email')->oldest('id')->limit(100)->get();

        return view('livewire.admin.payout-health', [
            'rails' => \App\Services\Payouts\Extensions\PayoutRailExtensions::catalogue(),
            'railProblems' => \App\Services\Payouts\Extensions\PayoutRailExtensions::problems(),
            'automation' => app(\App\Services\Payouts\Hardening\AutomationStatus::class)->report(),
            'run' => PayoutInvariantRun::latest('id')->first(),
            'held' => $held, 'unknown' => $unknown, 'manual' => $manual,
            'freezes' => PayoutUserFreeze::whereNull('released_at')->latest('frozen_at')->limit(50)->get(),
            'runs' => PayoutReconciliationRun::latest('id')->limit(5)->get(),
            'items' => PayoutReconciliationItem::whereNull('resolved_at')->where('kind', '!=', PayoutReconciliationItem::MATCHED)->latest('id')->limit(50)->get(),
            'events' => PayoutWebhookEvent::latest('id')->limit(15)->get(['id', 'provider', 'event_type', 'request_reference', 'outcome', 'received_at']),
            'isSuper' => (bool) Auth::user()?->hasRole('super_admin'),
            'canReview' => $this->mayReview(),
            'canFinance' => $this->mayFinance(),
        ]);
    }
}
