<?php

namespace App\Services\Staff;

use App\Models\User;
use App\Notifications\StaffAccountNotification;
use App\Support\Auditor;
use App\Support\StaffScopes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff accounts & scoped roles (blueprint Section 27). Single owner of staff
 * creation and scope assignment, with the privilege-escalation guard:
 *
 *  - Only a super admin manages staff.
 *  - No one may grant a scope they don't themselves hold (super_admin holds all
 *    via Gate::before; a bounded actor is intersected against their own scopes).
 *  - Staff management never touches super_admin/admin accounts, and never
 *    deletes a user (that stays super-admin-only in AccountService).
 *
 * Every mutation is written to the immutable audit log.
 */
class StaffService
{
    /** Scopes $actor is allowed to grant. super_admin => all; else their own. */
    public function grantableScopes(User $actor): array
    {
        if ($actor->hasRole('super_admin')) {
            return StaffScopes::all();
        }

        return array_values(array_intersect(
            StaffScopes::all(),
            $actor->getPermissionNames()->all()
        ));
    }

    /**
     * Create a staff member with the given scopes.
     *
     * @param  array{name:string,email:string,password:string}  $data
     * @param  list<string>  $scopes
     */
    public function createStaff(User $actor, array $data, array $scopes): User
    {
        $this->assertCanManageStaff($actor);
        $grantable = $this->assertGrantable($actor, $scopes);

        return DB::transaction(function () use ($data, $grantable, $actor) {
            $staff = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'referral_code' => 'STAFF-'.strtoupper(Str::random(6)),
            ]);
            $staff->email_verified_at = now();
            $staff->save();
            $staff->setRole('staff');

            $staff->assignRole('staff');
            $staff->syncPermissions($grantable);

            Auditor::log('staff.created', 'User', $staff->id, ['scopes' => $grantable]);
            $staff->notify(new StaffAccountNotification(StaffAccountNotification::GRANTED, $grantable));

            return $staff;
        });
    }

    /**
     * Promote an EXISTING active user into a staff member (blueprint Section 27
     * — "made staff by super admin from choosing any active user account"). The
     * user keeps their `user` role, so they still enjoy the end-user app; they
     * simply gain the `staff` role plus the granted scopes.
     *
     * @param  list<string>  $scopes
     */
    public function promote(User $actor, User $user, array $scopes): void
    {
        $this->assertCanManageStaff($actor);
        abort_if($user->hasAnyRole(['super_admin', 'admin']), 422, 'That account is already an admin.');
        abort_unless($user->is_active, 422, 'Only an active user account can be made staff.');

        $grantable = $this->assertGrantable($actor, $scopes);

        if (! $user->hasRole('staff')) {
            $user->assignRole('staff');
        }
        $user->forceFill(['role' => 'staff'])->save(); // mirror column for display
        $user->syncPermissions($grantable);

        Auditor::log('staff.promoted', 'User', $user->id, ['scopes' => $grantable]);
        $user->notify(new StaffAccountNotification(StaffAccountNotification::GRANTED, $grantable));
    }

    /**
     * Replace a staff member's scopes. Refuses to touch a non-staff account and
     * refuses any scope the actor can't grant.
     *
     * @param  list<string>  $scopes
     */
    public function syncScopes(User $actor, User $staff, array $scopes): void
    {
        $this->assertCanManageStaff($actor);
        $this->assertManageableTarget($staff);
        $grantable = $this->assertGrantable($actor, $scopes);

        $staff->syncPermissions($grantable);
        Auditor::log('staff.scopes_updated', 'User', $staff->id, ['scopes' => $grantable]);
        $staff->notify(new StaffAccountNotification(StaffAccountNotification::SCOPES_UPDATED, $grantable));
    }

    /** Revoke staff access (role + scopes). Does NOT delete the user account. */
    public function revokeStaff(User $actor, User $staff): void
    {
        $this->assertCanManageStaff($actor);
        $this->assertManageableTarget($staff);

        $staff->syncPermissions([]);
        $staff->removeRole('staff');
        $staff->forceFill(['role' => 'user'])->save();
        $staff->assignRole('user');

        Auditor::log('staff.revoked', 'User', $staff->id);
        $staff->notify(new StaffAccountNotification(StaffAccountNotification::REVOKED));
    }

    /**
     * Edit a staff member's name / email / (optional) password. A changed email
     * re-verifies. Blank password leaves it unchanged.
     *
     * @param  array{name?: string, email?: string, password?: ?string}  $data
     */
    public function updateStaff(User $actor, User $staff, array $data): void
    {
        $this->assertCanManageStaff($actor);
        $this->assertManageableTarget($staff);

        $emailChanged = isset($data['email']) && $data['email'] !== $staff->email;
        $fill = [
            'name' => trim((string) ($data['name'] ?? $staff->name)),
            'email' => (string) ($data['email'] ?? $staff->email),
        ];
        if ($emailChanged) {
            $fill['email_verified_at'] = null;
        }
        if (! empty($data['password'])) {
            $fill['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        }
        $staff->forceFill($fill)->save();

        if ($emailChanged) {
            $staff->sendEmailVerificationNotification();
        }

        Auditor::log('staff.updated', 'User', $staff->id, ['email_changed' => $emailChanged]);
    }

    /** Deactivate / reactivate a staff member without deleting them. */
    public function setActive(User $actor, User $staff, bool $active): void
    {
        $this->assertCanManageStaff($actor);
        $this->assertManageableTarget($staff);

        $accounts = app(\App\Services\Account\AccountService::class);
        $active ? $accounts->reactivate($staff) : $accounts->deactivate($staff);

        Auditor::log('staff.active_toggled', 'User', $staff->id, ['active' => $active]);
    }

    /** Permanently delete a staff account — super_admin only, guarded. */
    public function deleteStaff(User $actor, User $staff): void
    {
        $this->assertCanManageStaff($actor);
        $this->assertManageableTarget($staff);
        abort_if($staff->id === $actor->id, 403, 'You cannot delete your own account here.');

        Auditor::log('staff.deleted', 'User', $staff->id, ['email' => $staff->email]);
        // Sent synchronously and before the row is deleted, same reasoning as
        // AccountService::erase() — a queued job can't resolve a deleted model.
        $staff->notifyNow(new StaffAccountNotification(StaffAccountNotification::REMOVED));
        $staff->delete();
    }

    // -- Guards ------------------------------------------------------------

    public function assertCanManageStaff(User $actor): void
    {
        abort_unless($actor->hasRole('super_admin'), 403, 'Only a super admin may manage staff.');
    }

    /** The target must be an ordinary staff member — never an admin/super_admin. */
    private function assertManageableTarget(User $staff): void
    {
        abort_if($staff->hasAnyRole(['super_admin', 'admin']), 403, 'You cannot manage an admin account here.');
    }

    /**
     * Every requested scope must be valid AND grantable by the actor; otherwise
     * the whole operation is refused (no silent partial escalation).
     *
     * @param  list<string>  $scopes
     * @return list<string>
     */
    private function assertGrantable(User $actor, array $scopes): array
    {
        $scopes = array_values(array_unique($scopes));
        $grantable = $this->grantableScopes($actor);

        foreach ($scopes as $scope) {
            abort_unless(StaffScopes::isValid($scope), 422, "Unknown scope: {$scope}");
            abort_unless(in_array($scope, $grantable, true), 403, "You cannot grant the scope: {$scope}");
        }

        return $scopes;
    }
}
