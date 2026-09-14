<?php

namespace App\Services\Account;

use App\Jobs\ExportUserDataJob;
use App\Models\User;
use App\Notifications\AccountLifecycleNotification;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Account lifecycle & data rights (blueprint Section 26 / GDPR). Single owner of
 * pause/resume, data export, and the deletion workflow. Every transition is
 * written to the immutable audit log.
 *
 * Deletion is a two-step, super-admin-only workflow: a user REQUESTS deletion,
 * and only a super_admin may APPROVE it (staff never can). Approval performs
 * the erasure and leaves a tombstone in the audit log so the same erasure can
 * be re-applied if a backup is ever restored (Section 26.4).
 */
class AccountService
{
    /** Self-service pause. The user can still log in — only to reactivate. */
    public function deactivate(User $user): void
    {
        $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
        Auditor::log('account.deactivated', 'User', $user->id);
        $user->notify(new AccountLifecycleNotification(AccountLifecycleNotification::DEACTIVATED));
    }

    public function reactivate(User $user): void
    {
        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
        Auditor::log('account.reactivated', 'User', $user->id);
        $user->notify(new AccountLifecycleNotification(AccountLifecycleNotification::REACTIVATED));
    }

    /** Queue the personal-data export (Section 26.2). */
    public function requestExport(User $user): void
    {
        ExportUserDataJob::dispatch($user->id);
        Auditor::log('account.export_requested', 'User', $user->id);
    }

    /** User asks for deletion; a super_admin must approve before anything is erased. */
    public function requestDeletion(User $user): void
    {
        if ($user->hasPendingDeletion()) {
            return;
        }

        $user->forceFill(['deletion_requested_at' => now()])->save();
        Auditor::log('account.deletion_requested', 'User', $user->id);
        $user->notify(new AccountLifecycleNotification(AccountLifecycleNotification::DELETION_REQUESTED));
    }

    /** Withdraw a not-yet-approved deletion request. */
    public function cancelDeletionRequest(User $user): void
    {
        $user->forceFill(['deletion_requested_at' => null])->save();
        Auditor::log('account.deletion_cancelled', 'User', $user->id);
        $user->notify(new AccountLifecycleNotification(AccountLifecycleNotification::DELETION_CANCELLED));
    }

    /**
     * Approve a pending deletion and erase the account. ONLY a super_admin may
     * do this — staff are refused (blueprint Sections 26.3 & 27).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 for non-super-admins
     */
    public function approveDeletion(User $user, User $approver): void
    {
        abort_unless($approver->hasRole('super_admin'), 403, 'Only a super admin may approve account deletion.');

        if (! $user->hasPendingDeletion()) {
            abort(422, 'This account has no pending deletion request.');
        }

        $user->forceFill([
            'deletion_approved_at' => now(),
            'deletion_approved_by' => $approver->id,
        ])->save();

        Auditor::log('account.deletion_approved', 'User', $user->id, ['approved_by' => $approver->id]);

        $this->erase($user);
    }

    /**
     * Permanently erase the user and their personal data. Writes an audit
     * tombstone (with the id + a one-way email hash) BEFORE deletion so the
     * erasure survives the user row and can be re-applied to a restored backup.
     */
    public function erase(User $user): void
    {
        Auditor::log('account.erased', 'User', $user->id, [
            'email_sha256' => hash('sha256', (string) $user->email),
            'note' => 'GDPR erasure tombstone — re-apply on any backup restore.',
        ]);

        // Sent synchronously (never queued) and before the row is deleted — a
        // queued notification serializes the model by id and would fail to
        // resolve it once this transaction removes the row.
        $user->notifyNow(new AccountLifecycleNotification(AccountLifecycleNotification::ERASED));

        DB::transaction(function () use ($user) {
            $user->walletTransactions()->delete();
            $user->esimOrders()->delete();
            $user->smsOrders()->delete();
            $user->virtualNumbers()->delete();
            $user->referralsMade()->delete();
            $user->referral()->delete();
            $user->wallet()->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
