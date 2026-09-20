<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Account\AccountService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Erasure fix Phase A: a heavily-audited, read-only lookup of an anonymized
 * account's retained financial/order trail (wallet transactions, eSIM/SMS
 * orders, virtual numbers) — for a legal hold, chargeback dispute, or AML/
 * regulator request. Super-admin only. Every lookup requires a case reference
 * and reason and is written to the immutable audit log by
 * AccountService::viewRetainedRecordsForLegalHold().
 *
 * This never un-anonymizes a name/email — that's irreversible by design. It
 * only surfaces the retained records under the account's id.
 */
#[Layout('components.layouts.admin')]
class LegalHoldRecords extends Component
{
    public string $userId = '';

    public string $caseReference = '';

    public string $reason = '';

    public ?array $result = null;

    public ?string $error = null;

    public function lookup(AccountService $service): void
    {
        $this->result = null;
        $this->error = null;

        $user = User::find($this->userId);

        if (! $user) {
            $this->error = 'No account found with that id.';

            return;
        }

        try {
            $this->result = $service->viewRetainedRecordsForLegalHold(
                $user,
                Auth::user(),
                $this->caseReference,
                $this->reason,
            );
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.legal-hold-records', [
            'canView' => Auth::user()->hasRole('super_admin'),
        ]);
    }
}
