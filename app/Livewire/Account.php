<?php

namespace App\Livewire;

use App\Services\Account\AccountService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer → Account & privacy (blueprint Section 26). Self-service pause/
 * resume, personal-data export (download when ready), and a deletion request
 * that a super-admin must approve. No cost/third-party data is ever surfaced.
 */
#[Layout('components.layouts.customer')]
class Account extends Component
{
    public ?string $status = null;

    public function deactivate(AccountService $service): void
    {
        $service->deactivate(Auth::user());
        $this->status = 'Your account is paused. You can reactivate any time.';
    }

    public function reactivate(AccountService $service): void
    {
        $service->reactivate(Auth::user());
        $this->status = 'Welcome back — your account is active again.';
    }

    public function requestExport(AccountService $service): void
    {
        $service->requestExport(Auth::user());
        $this->status = 'We’re preparing your data export. This page will show a download link when it’s ready.';
    }

    public function requestDeletion(AccountService $service): void
    {
        $service->requestDeletion(Auth::user());
        $this->status = 'Deletion requested. Our team will review and approve it — you can cancel until then.';
    }

    public function cancelDeletion(AccountService $service): void
    {
        $service->cancelDeletionRequest(Auth::user());
        $this->status = 'Your deletion request has been withdrawn.';
    }

    public function render()
    {
        return view('livewire.account', ['user' => Auth::user()->fresh()]);
    }
}
