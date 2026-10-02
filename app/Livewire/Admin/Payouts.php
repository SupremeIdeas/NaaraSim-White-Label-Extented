<?php

namespace App\Livewire\Admin;

use App\Models\PayoutDecision;
use App\Models\PayoutRequest;
use App\Models\PayoutTrustProfile;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\FloatService;
use App\Services\Payouts\Guardian\GuardianMetrics;
use App\Services\Payouts\PayoutService;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Payouts. The operator switches the money-out feature on/off and works the
 * Payout Guardian's output: a REVIEW QUEUE (each item shows the Guardian's opinion
 * and evidence; approve/decline need a note), the DECISION LOG, the Guardian's
 * SETTINGS (auto-approval stays OFF/shadow until graduated; one red pause button)
 * and per-user TRUST overrides. Declining reverses the hold. Off by default.
 */
#[Layout('components.layouts.admin')]
class Payouts extends Component
{
    public const PROVIDERS = ['paystack', 'flutterwave', 'paypal', 'stripe', 'cryptomus'];

    public string $tab = 'queue';

    public bool $enabled = false;

    public string $mode = 'manual';

    public $minWithdrawal = 5;

    public ?string $saved = null;

    /** @var array<int, string> required note per request id */
    public array $notes = [];

    // Guardian settings
    public bool $autoApproval = false;

    public bool $shadow = true;

    /** @var array<string, bool> */
    public array $providerAuto = [];

    public $tierNew = 100;

    public $tierTrusted = 500;

    public $tierVip = 2000;

    public $dailyCap = 5000;

    public $qaPct = 3;

    public $coolingOff = 48;

    public $fxTolerance = 3;

    public $maxOpen = 3;

    public string $deniedCountries = '';

    // Float (treasury) forms
    public string $floatProvider = 'paystack';

    public string $floatCurrency = 'NGN';

    public $floatAmount = '';

    public string $floatNote = '';

    public $floatThreshold = 0;

    // Decision log filter + trust override form
    public string $decisionFilter = '';

    public string $trustEmail = '';

    public string $trustTier = 'trusted';

    public string $trustReason = '';

    public function mount(): void
    {
        abort_unless($this->mayReview() || $this->mayFinance(), 403);
        $this->enabled = PayoutSettings::enabled();
        $this->mode = PayoutSettings::mode();
        $this->minWithdrawal = PayoutSettings::minWithdrawal();

        $this->autoApproval = PayoutSettings::autoApprovalEnabled();
        $this->shadow = PayoutSettings::shadowMode();
        foreach (self::PROVIDERS as $p) {
            $this->providerAuto[$p] = PayoutSettings::providerAutoApprove($p);
        }
        $this->tierNew = PayoutSettings::tierLimitUsd('new');
        $this->tierTrusted = PayoutSettings::tierLimitUsd('trusted');
        $this->tierVip = PayoutSettings::tierLimitUsd('vip');
        $this->dailyCap = PayoutSettings::dailyAutoCapUsd();
        $this->qaPct = PayoutSettings::qaSamplePct();
        $this->coolingOff = PayoutSettings::coolingOffHours();
        $this->fxTolerance = PayoutSettings::fxTolerancePct();
        $this->maxOpen = PayoutSettings::maxOpenRequests();
        $this->deniedCountries = implode(', ', PayoutSettings::deniedCountries());
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

    /** Decide requests (approve / decline / ask for KYC / pause auto-approvals). */
    private function authorizeReview(): void
    {
        abort_unless($this->mayReview(), 403);
    }

    /** Float, radar, reconciliation, exports. */
    private function authorizeFinance(): void
    {
        abort_unless($this->mayFinance(), 403);
    }

    /** Settings stay with admins; scoped staff (reviewer / finance) can never change them. */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    /** Turning automation ON or loosening its limits is a super-admin decision. */
    private function authorizeSuper(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'mode' => 'required|in:manual,auto,autopilot',
            'minWithdrawal' => 'required|numeric|min:0|max:100000',
        ]);
        $mode = $this->mode === 'manual' ? 'manual' : 'auto';

        // Moving to `auto` lets the Guardian approve (once its own switches are on): super admin only.
        if ($mode === 'auto' && PayoutSettings::mode() !== 'auto') {
            $this->authorizeSuper();
        }

        Setting::setValue(PayoutSettings::FLAG, $this->enabled, 'payouts');
        Setting::setValue(PayoutSettings::MODE, $mode, 'payouts');
        Setting::setValue(PayoutSettings::MIN, (float) $this->minWithdrawal, 'payouts');

        Auditor::log('payouts.settings_updated', null, null, [
            'enabled' => $this->enabled, 'mode' => $mode, 'min' => $this->minWithdrawal,
        ]);

        $this->saved = 'Payout settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Payout settings saved.');
    }

    public function saveGuardian(): void
    {
        $this->authorizeSuper();

        $this->validate([
            'tierNew' => 'required|numeric|min:0|max:100000', 'tierTrusted' => 'required|numeric|min:0|max:100000',
            'tierVip' => 'required|numeric|min:0|max:1000000', 'dailyCap' => 'required|numeric|min:0|max:10000000',
            'qaPct' => 'required|numeric|min:0|max:100', 'coolingOff' => 'required|integer|min:0|max:720',
            'fxTolerance' => 'required|numeric|min:0|max:50', 'maxOpen' => 'required|integer|min:1|max:50',
            'deniedCountries' => 'nullable|string|max:500',
        ]);

        $before = ['auto' => PayoutSettings::autoApprovalEnabled(), 'shadow' => PayoutSettings::shadowMode()];
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, $this->autoApproval, 'payouts');
        Setting::setValue(PayoutSettings::AUTO_SHADOW, $this->shadow, 'payouts');
        foreach (self::PROVIDERS as $p) {
            Setting::setValue(PayoutSettings::PROVIDER_AUTO_PREFIX.$p.'.auto_approve', (bool) ($this->providerAuto[$p] ?? false), 'payouts');
        }
        Setting::setValue(PayoutSettings::TIER_LIMIT_PREFIX.'new', (float) $this->tierNew, 'payouts');
        Setting::setValue(PayoutSettings::TIER_LIMIT_PREFIX.'trusted', (float) $this->tierTrusted, 'payouts');
        Setting::setValue(PayoutSettings::TIER_LIMIT_PREFIX.'vip', (float) $this->tierVip, 'payouts');
        Setting::setValue(PayoutSettings::DAILY_CAP_USD, (float) $this->dailyCap, 'payouts');
        Setting::setValue(PayoutSettings::QA_SAMPLE_PCT, (float) $this->qaPct, 'payouts');
        Setting::setValue(PayoutSettings::COOLING_OFF_HOURS, (int) $this->coolingOff, 'payouts');
        Setting::setValue(PayoutSettings::FX_TOLERANCE_PCT, (float) $this->fxTolerance, 'payouts');
        Setting::setValue(PayoutSettings::MAX_OPEN, (int) $this->maxOpen, 'payouts');
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, strtoupper(preg_replace('/[^A-Za-z,]/', '', $this->deniedCountries)), 'payouts');

        Auditor::log('payouts.guardian_settings_updated', null, null, [
            'before' => $before, 'auto' => $this->autoApproval, 'shadow' => $this->shadow, 'providers' => $this->providerAuto,
            'tiers' => [$this->tierNew, $this->tierTrusted, $this->tierVip], 'daily_cap' => $this->dailyCap,
        ]);
        $this->dispatch('nx-toast', type: 'success', message: 'Guardian settings saved.');
    }

    /** The red button. ANY admin may pause; takes effect on the very next evaluation. Approved requests still send. */
    public function pauseAutoApprovals(): void
    {
        $this->authorizeReview();
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, false, 'payouts');
        $this->autoApproval = false;
        Auditor::log('payouts.auto_approvals_paused');
        $this->dispatch('nx-toast', type: 'success', message: 'All auto-approvals paused.');
    }

    public function approve(int $id, PayoutService $payouts): void
    {
        $this->authorizeReview();
        if (! $this->requireNote($id)) {
            return;
        }
        $payouts->approve(PayoutRequest::findOrFail($id), Auth::user(), trim($this->notes[$id]));
        unset($this->notes[$id]);
        $this->dispatch('nx-toast', type: 'success', message: 'Payout approved and queued.');
    }

    public function reject(int $id, PayoutService $payouts): void
    {
        $this->authorizeReview();
        if (! $this->requireNote($id)) {
            return;
        }
        $payouts->reject(PayoutRequest::findOrFail($id), Auth::user(), 'Declined by admin', trim($this->notes[$id]));
        unset($this->notes[$id]);
        $this->dispatch('nx-toast', type: 'success', message: 'Payout declined — funds returned.');
    }

    /** Keep it with a human and tell the user an extra check is needed (they see "Extra check in progress"). */
    public function requestKyc(int $id): void
    {
        $this->authorizeReview();
        if (! $this->requireNote($id)) {
            return;
        }
        $request = PayoutRequest::findOrFail($id);
        if ($request->status !== PayoutRequest::PENDING) {
            return;
        }
        $request->forceFill(['review_state' => PayoutRequest::REVIEW_MANUAL, 'hold_reason' => 'kyc_requested'])->save();
        PayoutDecision::create([
            'payout_request_id' => $id, 'attempt' => (int) PayoutDecision::where('payout_request_id', $id)->max('attempt') + 1,
            'decision' => 'hold', 'reason' => 'kyc_requested', 'score' => (int) $request->risk_score,
            'rules' => [['id' => 'admin_decision', 'result' => 'warn', 'weight' => 0, 'evidence' => ['note' => trim($this->notes[$id]), 'admin_id' => Auth::id()]]],
            'engine_version' => 'admin', 'shadow' => false, 'decided_by' => 'admin:'.Auth::id(), 'decided_at' => now(),
        ]);
        $request->user?->notify(new \App\Notifications\PayoutStatusNotification($id));
        unset($this->notes[$id]);
        $this->dispatch('nx-toast', type: 'success', message: 'Held for verification; the user was notified.');
    }

    private function requireNote(int $id): bool
    {
        if (mb_strlen(trim((string) ($this->notes[$id] ?? ''))) < 3) {
            $this->addError("notes.$id", 'A note is required for every manual decision.');

            return false;
        }
        $this->resetErrorBag("notes.$id");

        return true;
    }

    /** Start tracking float for a rail (optionally with an opening balance and a low-balance alert level). */
    public function trackRail(FloatService $float): void
    {
        $this->authorizeFinance();
        $this->validate([
            'floatProvider' => 'required|in:'.implode(',', self::PROVIDERS), 'floatCurrency' => 'required|alpha|size:3',
            'floatAmount' => 'nullable|numeric|min:0', 'floatThreshold' => 'nullable|numeric|min:0',
        ]);
        $float->track($this->floatProvider, $this->floatCurrency, (float) ($this->floatAmount ?: 0), (float) ($this->floatThreshold ?: 0), Auth::user());
        Auditor::log('payout.float_tracked', null, null, ['provider' => $this->floatProvider, 'currency' => strtoupper($this->floatCurrency)]);
        $this->reset(['floatAmount']);
        $this->dispatch('nx-toast', type: 'success', message: 'Float tracking started.');
    }

    /** Record money put into the provider account; waiting payouts resume, oldest first. */
    public function topUp(FloatService $float): void
    {
        $this->authorizeFinance();
        $this->validate([
            'floatProvider' => 'required|in:'.implode(',', self::PROVIDERS), 'floatCurrency' => 'required|alpha|size:3',
            'floatAmount' => 'required|numeric|gt:0', 'floatNote' => 'required|string|min:3|max:200',
        ]);
        try {
            $float->recordTopUp($this->floatProvider, $this->floatCurrency, (float) $this->floatAmount, Auth::user(), $this->floatNote);
        } catch (\App\Services\Payouts\PayoutException $e) {
            $this->addError('floatAmount', $e->getMessage());

            return;
        }
        $this->reset(['floatAmount', 'floatNote']);
        $this->dispatch('nx-toast', type: 'success', message: 'Top-up recorded.');
    }

    /** A signed correction against the ledger — super admin, and the note is the audit trail. */
    public function adjustFloat(FloatService $float): void
    {
        $this->authorizeSuper();
        $this->validate(['floatAmount' => 'required|numeric', 'floatNote' => 'required|string|min:5|max:200']);
        try {
            $float->adjust($this->floatProvider, $this->floatCurrency, (float) $this->floatAmount, Auth::user(), $this->floatNote);
        } catch (\App\Services\Payouts\PayoutException $e) {
            $this->addError('floatAmount', $e->getMessage());

            return;
        }
        $this->reset(['floatAmount', 'floatNote']);
        $this->dispatch('nx-toast', type: 'success', message: 'Float adjusted.');
    }

    public function setTrust(): void
    {
        $this->authorizeSuper();
        $this->validate([
            'trustEmail' => 'required|email', 'trustTier' => 'required|in:new,trusted,vip', 'trustReason' => 'required|string|min:5|max:200',
        ]);
        $user = User::where('email', $this->trustEmail)->first();
        if ($user === null) {
            $this->addError('trustEmail', 'No user with that email.');

            return;
        }
        PayoutTrustProfile::updateOrCreate(['user_id' => $user->id], [
            'tier' => $this->trustTier, 'override_by' => Auth::id(), 'override_reason' => $this->trustReason, 'updated_at' => now(),
        ]);
        Auditor::log('payouts.trust_override', User::class, $user->id, ['tier' => $this->trustTier, 'reason' => $this->trustReason]);
        $this->reset(['trustEmail', 'trustReason']);
        $this->dispatch('nx-toast', type: 'success', message: 'Trust tier set.');
    }

    public function exportLog(): StreamedResponse
    {
        $this->authorizeFinance();
        Auditor::log('payouts.decision_log_exported');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['decided_at', 'payout_id', 'attempt', 'decision', 'reason', 'score', 'shadow', 'decided_by', 'qa_sampled']);
            $this->logQuery()->limit(5000)->get()->each(fn ($d) => fputcsv($out, [
                $d->decided_at, $d->payout_request_id, $d->attempt, $d->decision, $d->reason, $d->score, $d->shadow ? 'yes' : 'no', $d->decided_by, $d->qa_sampled ? 'yes' : 'no',
            ]));
            fclose($out);
        }, 'payout-decisions-'.now()->format('Ymd-His').'.csv');
    }

    private function logQuery()
    {
        return PayoutDecision::query()
            ->when($this->decisionFilter !== '', fn ($q) => $q->where('decision', $this->decisionFilter))
            ->latest('decided_at')->latest('id');
    }

    public function render()
    {
        $pending = PayoutRequest::with('user:id,name,email', 'account:id,bank_name,account_name,account_number')
            ->where('status', PayoutRequest::PENDING)->latest()->get();
        $opinions = PayoutDecision::whereIn('payout_request_id', $pending->pluck('id'))
            ->orderBy('attempt')->get()->groupBy('payout_request_id')->map->last();
        $recent = PayoutRequest::with('user:id,name,email')
            ->whereIn('status', [PayoutRequest::PROCESSING, PayoutRequest::PAID, PayoutRequest::FAILED, PayoutRequest::REVERSED])
            ->latest()->limit(25)->get();

        return view('livewire.admin.payouts', [
            'pending' => $pending,
            'opinions' => $opinions,
            'recent' => $recent,
            'pendingTotal' => (float) $pending->sum('amount'),
            'log' => $this->tab === 'log' ? $this->logQuery()->limit(100)->get() : collect(),
            'qa' => $this->tab === 'log' ? PayoutDecision::where('qa_sampled', true)->where('decided_at', '>=', now()->subDays(14))->latest('decided_at')->limit(20)->get() : collect(),
            'metrics' => app(GuardianMetrics::class)->latest(),
            'floats' => $this->tab === 'float' ? \App\Models\PayoutFloatBalance::orderBy('provider')->get() : collect(),
            'movements' => $this->tab === 'float' ? \App\Models\PayoutFloatMovement::latest('id')->limit(30)->get() : collect(),
            'waiting' => $this->tab === 'float' ? PayoutRequest::with('user:id,email')->where('status', PayoutRequest::AWAITING_FUNDS)->orderBy('id')->get() : collect(),
            'isSuper' => Auth::user()->hasRole('super_admin'),
        ]);
    }
}
