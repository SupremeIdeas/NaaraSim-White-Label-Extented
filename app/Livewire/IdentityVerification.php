<?php

namespace App\Livewire;

use App\Services\Kyc\KycService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer identity verification (ROADMAP §Layer 0.3). Level 2 (ID + liveness)
 * is required before a user can withdraw. The active provider decides
 * synchronously, awaits a callback, or (default) queues the attempt for admin
 * review. No raw ID number is stored — the service passes it to the provider and
 * keeps only the structured result.
 */
#[Layout('components.layouts.customer')]
class IdentityVerification extends Component
{
    public string $country = 'NG';

    public string $idType = 'BVN';

    public string $idNumber = '';

    public function submit(KycService $kyc): void
    {
        $this->validate([
            'country' => 'required|string|size:2',
            'idType' => 'required|string|max:40',
            'idNumber' => 'required|string|max:64',
        ]);

        $kyc->submit(Auth::user(), \App\Models\KycVerification::L2, [
            'country' => strtoupper($this->country),
            'id_type' => $this->idType,
            'id_number' => $this->idNumber,
        ]);

        $this->reset('idNumber');
        $this->dispatch('nx-toast', type: 'success', message: 'Identity submitted for verification.');
    }

    public function render(KycService $kyc)
    {
        $user = Auth::user();

        return view('livewire.identity-verification', [
            'level' => $kyc->currentLevel($user),
            'attempt' => $kyc->latest($user, \App\Models\KycVerification::L2),
            'verified' => $kyc->hasLevel($user, \App\Models\KycVerification::L2),
        ]);
    }
}
