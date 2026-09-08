<?php

namespace App\Livewire\Admin;

use App\Models\ErrorLog;
use App\Models\MaintenanceProposal;
use App\Services\Maintenance\Contracts\CodeHostClient;
use App\Services\Maintenance\Contracts\FixProposer;
use App\Services\Maintenance\MaintenanceLoop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Maintenance (blueprint Section 29). Super-admin only. Turn a logged
 * error into a Claude-proposed fix, review the diff, and approve (opens a
 * CI-gated PR), reject, or roll back. The proposer/host are gated on config —
 * the page shows a clear "not configured" state until keys are set.
 */
#[Layout('components.layouts.admin')]
class Maintenance extends Component
{
    public ?string $status = null;

    public ?string $error = null;

    /** Currently expanded proposal diff. */
    public ?int $viewing = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
    }

    public function propose(int $errorId, MaintenanceLoop $loop): void
    {
        $this->reset('error', 'status');
        try {
            $proposal = $loop->analyze(ErrorLog::findOrFail($errorId));
            $this->status = 'Fix proposed — review the diff below before approving.';
            $this->viewing = $proposal->id;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function approve(int $id, MaintenanceLoop $loop): void
    {
        $this->guarded(fn () => $loop->approve(MaintenanceProposal::findOrFail($id), Auth::user()),
            'Approved — a CI-gated pull request has been opened.');
    }

    public function reject(int $id, MaintenanceLoop $loop): void
    {
        $this->guarded(fn () => $loop->reject(MaintenanceProposal::findOrFail($id), Auth::user()),
            'Proposal rejected.');
    }

    public function rollback(int $id, MaintenanceLoop $loop): void
    {
        $this->guarded(fn () => $loop->rollback(MaintenanceProposal::findOrFail($id), Auth::user()),
            'Rolled back — the pull request was closed.');
    }

    private function guarded(callable $action, string $ok): void
    {
        $this->reset('error', 'status');
        try {
            $action();
            $this->status = $ok;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.maintenance', [
            'proposerReady' => app(FixProposer::class)->available(),
            'hostReady' => app(CodeHostClient::class)->available(),
            'errors' => ErrorLog::latest()->limit(10)->get(),
            'proposals' => MaintenanceProposal::latest()->limit(20)->get(),
        ]);
    }
}
