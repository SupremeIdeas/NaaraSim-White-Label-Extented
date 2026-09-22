<?php

namespace App\Services\Account;

use App\Jobs\ExportUserDataJob;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountLifecycleNotification;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Account lifecycle & data rights (blueprint Section 26 / GDPR). Single owner of
 * pause/resume, data export, and the deletion workflow. Every transition is
 * written to the immutable audit log.
 *
 * Deletion is a two-step, super-admin-only workflow: a user REQUESTS deletion,
 * and only a super_admin may APPROVE it (staff never can). Approval erases the
 * account — see erase()'s docblock for what "erase" means here: anonymize the
 * PII immediately, retain the financial/order trail under the same user id
 * until an admin-configured retention window elapses, then purge() performs
 * the real, final, irreversible deletion.
 */
class AccountService
{
    /** Fallback retention window if account_erasure.retention_years is unset. */
    private const DEFAULT_RETENTION_YEARS = 6;
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
     * Erase = anonymize-and-retain, NOT an instant hard-delete. Most financial
     * regulators require transaction-record retention for years regardless of
     * a user's deletion request, so a synchronous hard-delete on approval was
     * a genuine compliance exposure (blueprint erasure-fix, Phase A). The
     * correct lifecycle:
     *
     *   1. Here, now: null out every genuinely PII field on the User row
     *      (name/email/phone/address/DOB/avatar/etc — see
     *      User::ANONYMIZED_FIELDS), set is_active=false and anonymized_at,
     *      and revoke API tokens — the account becomes unreachable/unusable
     *      to anyone, including its original owner, exactly as a deletion
     *      should look from the user's own point of view.
     *   2. WalletTransaction / EsimOrder / SmsOrder / VirtualNumber / the
     *      wallet itself / referral rows are left fully intact under this
     *      same user id — a regulator, chargeback dispute, or AML audit can
     *      still be answered.
     *   3. retention_purge_due_at is set from the admin-configurable
     *      account_erasure.retention_years setting; purge() (run by the
     *      scheduled account:purge-erased command once that date passes)
     *      performs the real, final, irreversible deletion this method used
     *      to do immediately.
     */
    public function erase(User $user): void
    {
        $retentionYears = max(1, (int) Setting::getValue('account_erasure.retention_years', self::DEFAULT_RETENTION_YEARS));
        $purgeDueAt = now()->addYears($retentionYears);
        $emailHash = hash('sha256', (string) $user->email);

        Auditor::log('account.anonymized', 'User', $user->id, [
            'email_sha256' => $emailHash,
            'retention_years' => $retentionYears,
            'retention_purge_due_at' => $purgeDueAt->toIso8601String(),
            'note' => 'GDPR erasure: PII anonymized; financial/order records retained under this user id until the retention window elapses.',
        ]);

        // Sent synchronously and BEFORE the PII fields below are overwritten —
        // a queued send could run after this method returns and would render
        // with the anonymized placeholder name/email instead of the real one.
        $user->notifyNow(new AccountLifecycleNotification(AccountLifecycleNotification::ERASED));

        DB::transaction(function () use ($user, $emailHash, $purgeDueAt) {
            $user->forceFill(array_merge(
                array_fill_keys(User::ANONYMIZED_FIELDS, null),
                [
                    'name' => 'Deleted User',
                    // Deterministic + unique so the users.email unique index
                    // is never violated by two anonymized accounts colliding.
                    'email' => "erased-{$user->id}-{$emailHash}@erased.naarasim.invalid",
                    'password' => Hash::make(Str::random(40)),
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'security_questions' => null,
                    'is_active' => false,
                    'anonymized_at' => now(),
                    'retention_purge_due_at' => $purgeDueAt,
                ]
            ))->save();

            $user->tokens()->delete();
        });
    }

    /**
     * The real, final, irreversible purge — run only once retention_purge_due_at
     * has passed (by the scheduled account:purge-erased command). This is where
     * true data minimization happens: everything erase() deliberately retained
     * is now permanently deleted. There is no undo past this point.
     */
    public function purge(User $user): void
    {
        Auditor::log('account.purged', 'User', $user->id, [
            'note' => 'Retention window elapsed — financial/order records permanently deleted.',
        ]);

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

    /**
     * A heavily-audited, READ-ONLY view of an anonymized account's retained
     * financial/order history, for a legal hold, chargeback dispute, or AML/
     * regulator request. This does NOT and cannot reverse anonymization —
     * once PII fields are overwritten by erase() that's irreversible by
     * design, which is exactly why what a legal-hold request actually needs
     * is the retained financial trail under this user id, not the person's
     * name/email restored. Every call is logged with the case reference and
     * reason, both mandatory.
     *
     * @return array{
     *     user_id:int, anonymized_at:?string, retention_purge_due_at:?string,
     *     wallet_transactions:\Illuminate\Support\Collection,
     *     esim_orders:\Illuminate\Support\Collection,
     *     sms_orders:\Illuminate\Support\Collection,
     *     virtual_numbers:\Illuminate\Support\Collection,
     * }
     */
    public function viewRetainedRecordsForLegalHold(User $user, User $approver, string $caseReference, string $reason): array
    {
        abort_unless($approver->hasRole('super_admin'), 403, 'Only a super admin may view an anonymized account under legal hold.');
        abort_if(trim($caseReference) === '', 422, 'A case reference is required to view retained records under legal hold.');
        abort_if(trim($reason) === '', 422, 'A reason is required to view retained records under legal hold.');
        abort_unless($user->isAnonymized(), 422, 'This account has not been anonymized.');

        Auditor::log('account.legal_hold_viewed', 'User', $user->id, [
            'viewed_by' => $approver->id,
            'case_reference' => $caseReference,
            'reason' => $reason,
        ]);

        return [
            'user_id' => $user->id,
            'anonymized_at' => optional($user->anonymized_at)->toIso8601String(),
            'retention_purge_due_at' => optional($user->retention_purge_due_at)->toIso8601String(),
            'wallet_transactions' => $user->walletTransactions()->get(),
            'esim_orders' => $user->esimOrders()->get(),
            'sms_orders' => $user->smsOrders()->get(),
            'virtual_numbers' => $user->virtualNumbers()->get(),
        ];
    }
}
