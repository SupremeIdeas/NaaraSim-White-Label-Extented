<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Account\AccountService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Account deletions (blueprint Section 26.3). Lists accounts with a
 * pending deletion request. Only a super_admin may APPROVE (and thereby erase)
 * an account — staff can see the queue but the approve action is refused for
 * anyone without the super_admin role (enforced in AccountService).
 */
#[Layout('components.layouts.admin')]
class AccountDeletions extends Component
{
    public ?string $status = null;

    public function approve(int $userId, AccountService $service): void
    {
        $user = User::whereKey($userId)->firstOrFail();
        $service->approveDeletion($user, Auth::user());
        $this->status = 'Account #'.$userId.' was approved and permanently erased.';
    }

    public function render()
    {
        $pending = User::whereNotNull('deletion_requested_at')
            ->whereNull('deletion_approved_at')
            ->orderBy('deletion_requested_at')
            ->get();

        return view('livewire.admin.account-deletions', [
            'pending' => $pending,
            'canApprove' => Auth::user()->hasRole('super_admin'),
        ]);
    }
}
