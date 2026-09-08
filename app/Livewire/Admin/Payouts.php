<?php

namespace App\Livewire\Admin;

use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Services\Payouts\PayoutService;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Payouts (ROADMAP §Layer 0 controls). The operator switches the whole
 * money-out feature on/off, picks manual vs autopilot settlement + the minimum
 * withdrawal, and — in manual mode — approves or declines each pending request.
 * Approving queues the PSP transfer through the engine; declining reverses the
 * hold so the payee's funds are returned. Off by default.
 */
#[Layout('components.layouts.admin')]
class Payouts extends Component
{
    public bool $enabled = false;

    public string $mode = 'manual';

    public $minWithdrawal = 5;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->enabled = PayoutSettings::enabled();
        $this->mode = PayoutSettings::mode();
        $this->minWithdrawal = PayoutSettings::minWithdrawal();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'mode' => 'required|in:manual,autopilot',
            'minWithdrawal' => 'required|numeric|min:0|max:100000',
        ]);

        Setting::setValue(PayoutSettings::FLAG, $this->enabled, 'payouts');
        Setting::setValue(PayoutSettings::MODE, $this->mode, 'payouts');
        Setting::setValue(PayoutSettings::MIN, (float) $this->minWithdrawal, 'payouts');

        Auditor::log('payouts.settings_updated', null, null, [
            'enabled' => $this->enabled, 'mode' => $this->mode, 'min' => $this->minWithdrawal,
        ]);

        $this->saved = 'Payout settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Payout settings saved.');
    }

    public function approve(int $id, PayoutService $payouts): void
    {
        $request = PayoutRequest::findOrFail($id);
        $payouts->approve($request, Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Payout approved and queued.');
    }

    public function reject(int $id, PayoutService $payouts): void
    {
        $request = PayoutRequest::findOrFail($id);
        $payouts->reject($request, Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Payout declined — funds returned.');
    }

    public function render()
    {
        $pending = PayoutRequest::with('user:id,name,email', 'account:id,bank_name,account_name,account_number')
            ->where('status', PayoutRequest::PENDING)->latest()->get();
        $recent = PayoutRequest::with('user:id,name,email')
            ->whereIn('status', [PayoutRequest::PROCESSING, PayoutRequest::PAID, PayoutRequest::FAILED, PayoutRequest::REVERSED])
            ->latest()->limit(25)->get();

        return view('livewire.admin.payouts', [
            'pending' => $pending,
            'recent' => $recent,
            'pendingTotal' => (float) $pending->sum('amount'),
        ]);
    }
}
