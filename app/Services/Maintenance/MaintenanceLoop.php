<?php

namespace App\Services\Maintenance;

use App\Models\ErrorLog;
use App\Models\MaintenanceProposal;
use App\Models\User;
use App\Services\Maintenance\Contracts\CodeHostClient;
use App\Services\Maintenance\Contracts\FixProposer;
use App\Support\Auditor;
use App\Support\Maintenance\SecretGuard;

/**
 * Claude-assisted maintenance loop (blueprint Section 29):
 *
 *   logged error -> propose a fix (Claude) -> secret-safety check -> human
 *   review -> approval opens a CI-gated PR (never straight to prod) -> one-click
 *   rollback.
 *
 * Every transition is written to the immutable audit log. Approvals, PRs and
 * rollbacks are super-admin only.
 */
class MaintenanceLoop
{
    public function __construct(
        protected FixProposer $proposer,
        protected CodeHostClient $host,
        protected SecretGuard $guard,
    ) {
    }

    /** Propose a fix for a logged error and store it as pending review. */
    public function analyze(ErrorLog $error): MaintenanceProposal
    {
        abort_unless($this->proposer->available(), 422, 'The fix proposer (Claude) is not configured.');

        $fix = $this->proposer->propose($error);

        // Hard stop: a proposal that touches secrets is never stored/actioned.
        $this->guard->assertSafe($fix);

        $proposal = MaintenanceProposal::create([
            'error_log_id' => $error->id,
            'title' => $fix->title,
            'summary' => $fix->summary,
            'diff' => $fix->diff,
            'changes' => $fix->changes,
            'status' => MaintenanceProposal::STATUS_PENDING,
        ]);

        Auditor::log('maintenance.proposed', 'MaintenanceProposal', $proposal->id, [
            'files' => $fix->targetFiles(),
        ]);

        return $proposal;
    }

    /**
     * Approve a pending proposal: open a CI-gated PR with the changes. Only a
     * super_admin may approve — the fix is re-checked for secrets first.
     */
    public function approve(MaintenanceProposal $proposal, User $actor): MaintenanceProposal
    {
        $this->assertSuperAdmin($actor);
        abort_unless($proposal->isPending(), 422, 'This proposal is not awaiting review.');
        abort_unless($this->host->available(), 422, 'The code host (GitHub) is not configured.');

        $fix = new ProposedFix($proposal->title, (string) $proposal->summary, $proposal->changes ?? [], (string) $proposal->diff);
        $this->guard->assertSafe($fix);

        $result = $this->host->openPullRequest($fix);

        $proposal->update([
            'status' => MaintenanceProposal::STATUS_PR_OPENED,
            'branch' => $result['branch'],
            'pr_url' => $result['pr_url'],
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        Auditor::log('maintenance.approved', 'MaintenanceProposal', $proposal->id, ['pr_url' => $result['pr_url']]);

        return $proposal;
    }

    public function reject(MaintenanceProposal $proposal, User $actor): void
    {
        $this->assertSuperAdmin($actor);
        abort_unless($proposal->isPending(), 422, 'This proposal is not awaiting review.');

        $proposal->update([
            'status' => MaintenanceProposal::STATUS_REJECTED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        Auditor::log('maintenance.rejected', 'MaintenanceProposal', $proposal->id);
    }

    /** One-click rollback of an opened PR (close if unmerged, else a revert PR). */
    public function rollback(MaintenanceProposal $proposal, User $actor): void
    {
        $this->assertSuperAdmin($actor);
        abort_unless($proposal->status === MaintenanceProposal::STATUS_PR_OPENED, 422, 'Nothing to roll back.');

        $this->host->rollback((string) $proposal->branch, (string) $proposal->pr_url);

        $proposal->update(['status' => MaintenanceProposal::STATUS_ROLLED_BACK]);
        Auditor::log('maintenance.rolled_back', 'MaintenanceProposal', $proposal->id);
    }

    private function assertSuperAdmin(User $actor): void
    {
        abort_unless($actor->hasRole('super_admin'), 403, 'Only a super admin may action a maintenance proposal.');
    }
}
