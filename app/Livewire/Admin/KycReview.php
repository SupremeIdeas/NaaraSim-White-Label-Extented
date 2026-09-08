<?php

namespace App\Livewire\Admin;

use App\Models\KycVerification;
use App\Models\Setting;
use App\Services\Kyc\KycService;
use App\Support\Auditor;
use App\Support\KycSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Identity (ROADMAP §Layer 0.3). Choose the active KYC provider and, in
 * manual mode (or for anything a provider left pending), approve or reject each
 * identity check. Approving marks the user verified at that level, unlocking the
 * gates (withdraw at L2, merchant at L3).
 */
#[Layout('components.layouts.admin')]
class KycReview extends Component
{
    public string $provider = 'manual';

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->provider = KycSettings::provider();
    }

    public function saveProvider(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['provider' => 'required|in:manual,smileid,dojah']);

        Setting::setValue(KycSettings::PROVIDER, $this->provider, 'kyc');
        Auditor::log('kyc.provider_updated', null, null, ['provider' => $this->provider]);

        $this->saved = 'Identity provider updated.';
        $this->dispatch('nx-toast', type: 'success', message: 'Identity provider updated.');
    }

    public function approve(int $id, KycService $kyc): void
    {
        $kyc->approve(KycVerification::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Verification approved.');
    }

    public function reject(int $id, KycService $kyc): void
    {
        $kyc->reject(KycVerification::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Verification rejected.');
    }

    public function render()
    {
        $pending = KycVerification::with('user:id,name,email')
            ->where('status', KycVerification::PENDING)->latest()->get();
        $recent = KycVerification::with('user:id,name,email')
            ->whereIn('status', [KycVerification::APPROVED, KycVerification::REJECTED, KycVerification::FAILED])
            ->latest()->limit(25)->get();

        return view('livewire.admin.kyc-review', compact('pending', 'recent'));
    }
}
