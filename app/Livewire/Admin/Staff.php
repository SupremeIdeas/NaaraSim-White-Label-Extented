<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Staff\StaffCompensationService;
use App\Services\Staff\StaffService;
use App\Support\StaffScopes;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Admin → Staff (blueprint Section 27). Super-admin-only: create staff members
 * and grant/revoke granular scopes. The privilege-escalation guard lives in
 * StaffService; this component only surfaces scopes the actor may grant.
 */
#[Layout('components.layouts.admin')]
class Staff extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:190|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    /** Selected scopes for the create form. */
    public array $scopes = [];

    /** Promote-an-existing-user flow. */
    public string $promoteEmail = '';

    public array $promoteScopes = [];

    public ?string $saved = null;

    public ?string $promoteError = null;

    public function mount(StaffService $service): void
    {
        // Hard gate: only a super admin may even open this page.
        $service->assertCanManageStaff(Auth::user());
    }

    public function createStaff(StaffService $service): void
    {
        $this->validate();

        $service->createStaff(Auth::user(), [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ], $this->scopes);

        $this->reset('name', 'email', 'password', 'scopes');
        $this->saved = 'Staff member created.';
    }

    public function promote(StaffService $service): void
    {
        $this->promoteError = null;
        $this->validate(['promoteEmail' => 'required|email']);

        $user = User::where('email', $this->promoteEmail)->first();
        if ($user === null) {
            $this->promoteError = 'No user found with that email. Use the "create" form for a brand-new account.';

            return;
        }

        $service->promote(Auth::user(), $user, $this->promoteScopes);
        $this->reset('promoteEmail', 'promoteScopes');
        $this->saved = 'User promoted to staff.';
    }

    public function toggleScope(int $staffId, string $scope, StaffService $service): void
    {
        $staff = User::findOrFail($staffId);
        $current = $staff->getPermissionNames()->all();

        $next = in_array($scope, $current, true)
            ? array_values(array_diff($current, [$scope]))
            : array_values(array_merge($current, [$scope]));

        $service->syncScopes(Auth::user(), $staff, $next);
        $this->saved = 'Scopes updated.';
    }

    public function revoke(int $staffId, StaffService $service): void
    {
        $service->revokeStaff(Auth::user(), User::findOrFail($staffId));
        $this->saved = 'Staff access revoked.';
    }

    // -- Edit an existing staff member (owner request — fix_admin Part 5) ----

    public ?int $editingStaffId = null;

    public string $edit_name = '';

    public string $edit_email = '';

    public string $edit_password = '';

    public function editStaff(int $staffId): void
    {
        $staff = User::findOrFail($staffId);
        $this->editingStaffId = $staff->id;
        $this->edit_name = (string) $staff->name;
        $this->edit_email = (string) $staff->email;
        $this->edit_password = '';
    }

    public function cancelEditStaff(): void
    {
        $this->reset('editingStaffId', 'edit_name', 'edit_email', 'edit_password');
    }

    public function saveStaff(StaffService $service): void
    {
        $staff = User::findOrFail($this->editingStaffId);
        $this->validate([
            'edit_name' => ['required', 'string', 'max:120'],
            'edit_email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($staff->id)],
            'edit_password' => ['nullable', 'string', 'min:8'],
        ]);

        $service->updateStaff(Auth::user(), $staff, [
            'name' => $this->edit_name,
            'email' => $this->edit_email,
            'password' => $this->edit_password ?: null,
        ]);

        $this->cancelEditStaff();
        $this->saved = 'Staff member updated.';
    }

    public function toggleStaffActive(int $staffId, StaffService $service): void
    {
        $staff = User::findOrFail($staffId);
        $service->setActive(Auth::user(), $staff, $staff->isDeactivated());
        $this->saved = $staff->fresh()->isDeactivated() ? 'Staff deactivated.' : 'Staff reactivated.';
    }

    public function deleteStaff(int $staffId, StaffService $service): void
    {
        $service->deleteStaff(Auth::user(), User::findOrFail($staffId));
        $this->cancelEditStaff();
        $this->saved = 'Staff member deleted.';
    }

    /* -------- Compensation (profit-share, NAARA-BUILD-23 §2) --------------- */

    /** Per-staff percentage input, keyed by user id. */
    public array $comp = [];

    public ?string $compError = null;

    /**
     * Set (or update) a staff member's profit-share percentage. The rate is
     * non-retroactive — effective_from is stamped to today, so an already-closed
     * month is never altered. Rejected if it would push the combined committed
     * percentage (partners + staff) over 100% of platform profit.
     */
    public function saveCompensation(int $staffId, StaffCompensationService $comp): void
    {
        $this->compError = null;
        $this->saved = null;
        (new StaffService)->assertCanManageStaff(Auth::user());

        $staff = User::role('staff')->findOrFail($staffId);
        $pct = round((float) ($this->comp[$staffId] ?? 0), 3);
        if ($pct < 0 || $pct > 100) {
            $this->compError = 'Percentage must be between 0 and 100.';

            return;
        }

        $profile = \App\Models\StaffCompensationProfile::firstOrNew(['user_id' => $staff->id]);

        // Over-commitment guard: combined active partner + staff share can't exceed 100%.
        $combined = $comp->combinedCommittedPct($pct, excludeStaffProfileId: $profile->id ?: null);
        if ($combined > 100) {
            $this->compError = "That would commit {$combined}% of platform profit across all partners + staff — over 100%. Lower another share first.";

            return;
        }

        $profile->fill([
            'profit_share_pct' => $pct,
            'is_active' => $profile->exists ? $profile->is_active : true,
            'effective_from' => now()->toDateString(),
        ])->save();

        $this->saved = 'Compensation updated.';
    }

    public function toggleCompensationActive(int $staffId): void
    {
        (new StaffService)->assertCanManageStaff(Auth::user());
        $profile = \App\Models\StaffCompensationProfile::where('user_id', $staffId)->first();
        if ($profile) {
            $profile->update(['is_active' => ! $profile->is_active]);
            $this->saved = $profile->is_active ? 'Compensation activated.' : 'Compensation paused.';
        }
    }

    public function render(StaffCompensationService $comp)
    {
        $staff = User::role('staff')->orderBy('name')->get();
        $profiles = \App\Models\StaffCompensationProfile::whereIn('user_id', $staff->pluck('id'))->get()->keyBy('user_id');

        // Seed the per-staff inputs with current rates.
        foreach ($staff as $member) {
            if (! array_key_exists($member->id, $this->comp)) {
                $this->comp[$member->id] = (float) ($profiles[$member->id]->profit_share_pct ?? 0);
            }
        }

        $projected = [];
        foreach ($profiles as $uid => $profile) {
            $projected[$uid] = $comp->projectedThisMonth($profile);
        }

        return view('livewire.admin.staff', [
            'staff' => $staff,
            'grantable' => (new StaffService)->grantableScopes(Auth::user()),
            'labels' => StaffScopes::labels(),
            'profiles' => $profiles,
            'projected' => $projected,
            'combinedPct' => $comp->combinedCommittedPct(),
        ]);
    }
}
