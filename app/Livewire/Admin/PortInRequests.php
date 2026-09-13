<?php

namespace App\Livewire\Admin;

use App\Models\PortInRequest;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Port-in requests (Prompt 11). The ops workflow for a customer's
 * US/Canada port-in: review the losing-carrier details, advance the status as
 * the carrier processes it, or reject with a reason. Admin-only surface, so
 * the account number / PIN are shown here (like cost elsewhere) — but they are
 * PURGED the moment a request closes (completed or rejected), since a spent
 * transfer PIN is sensitive and no longer needed.
 *
 * Turning a completed port-in into a live, billed Naara Line is a deliberate
 * later step (it needs hosted-number provisioning + a billing-start decision,
 * a money-path change), so "completed" here records the carrier outcome only.
 */
#[Layout('components.layouts.admin')]
class PortInRequests extends Component
{
    use WithPagination;

    public string $filter = '';

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public ?int $editingId = null;

    public string $adminNotesValue = '';

    public string $providerValue = '';

    public ?string $flash = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /** Advance a request's status; closing it purges the losing-carrier secrets. */
    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, PortInRequest::STATUSES, true) || $status === PortInRequest::STATUS_REJECTED) {
            return; // rejection goes through reject() so a reason is always captured
        }

        $request = PortInRequest::find($id);
        if (! $request) {
            return;
        }

        $attrs = ['status' => $status, 'reviewed_by' => Auth::id()];
        if ($status === PortInRequest::STATUS_COMPLETED) {
            $attrs['account_number'] = null;
            $attrs['pin'] = null;
        }
        $request->update($attrs);

        Auditor::log('port_in.status_changed', 'PortInRequest', $id, ['status' => $status]);
        $this->flash = 'Marked '.$request->phone_number.' as '.PortInRequest::statusLabel($status).'.';
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function reject(int $id): void
    {
        $this->validate(['rejectReason' => 'required|string|max:255']);

        $request = PortInRequest::find($id);
        if (! $request) {
            return;
        }
        // A rejected request's PIN/account are spent — purge them.
        $request->update([
            'status' => PortInRequest::STATUS_REJECTED,
            'rejection_reason' => $this->rejectReason,
            'reviewed_by' => Auth::id(),
            'account_number' => null,
            'pin' => null,
        ]);

        Auditor::log('port_in.rejected', 'PortInRequest', $id, ['reason' => $this->rejectReason]);
        $this->rejectingId = null;
        $this->rejectReason = '';
        $this->flash = 'Rejected '.$request->phone_number.'.';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function editDetails(int $id): void
    {
        $request = PortInRequest::find($id);
        $this->editingId = $id;
        $this->adminNotesValue = (string) ($request?->admin_notes ?? '');
        $this->providerValue = (string) ($request?->provider ?? '');
    }

    public function saveDetails(int $id): void
    {
        $this->validate([
            'adminNotesValue' => 'nullable|string|max:500',
            'providerValue' => 'nullable|string|max:60',
        ]);

        $request = PortInRequest::find($id);
        if (! $request) {
            return;
        }
        $request->update([
            'admin_notes' => trim($this->adminNotesValue) ?: null,
            'provider' => trim($this->providerValue) ?: null,
        ]);
        Auditor::log('port_in.details_saved', 'PortInRequest', $id);
        $this->editingId = null;
        $this->adminNotesValue = '';
        $this->providerValue = '';
        $this->flash = 'Saved.';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->adminNotesValue = '';
        $this->providerValue = '';
    }

    public function render()
    {
        $requests = PortInRequest::query()
            ->when($this->filter !== '', fn ($q) => $q->where('status', $this->filter))
            ->with(['user', 'reviewer'])
            ->latest()
            ->paginate(15);

        return view('livewire.admin.port-in-requests', [
            'requests' => $requests,
            'statuses' => PortInRequest::STATUSES,
        ]);
    }
}
