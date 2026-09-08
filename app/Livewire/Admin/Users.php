<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Merchants\MerchantService;
use App\Support\Auditor;
use App\Support\EngagementScore;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Users (blueprint Section 27, owner request). super_admin/admin can
 * see every registered user, search/filter them, inspect a profile (wallet,
 * orders, roles, KYC), and activate / deactivate an account. Deleting a user is
 * NOT done here — that stays a super-admin-approved lifecycle action (S26), so
 * staff can manage almost anything EXCEPT deleting users.
 */
#[Layout('components.layouts.admin')]
class Users extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all'; // all | active | deactivated | staff | merchants

    public ?int $viewingId = null;

    // Inline edit buffer
    public bool $editing = false;

    public string $edit_name = '';

    public string $edit_email = '';

    public string $edit_phone = '';

    /** A generated temporary password, shown to the admin once. */
    public ?string $tempPassword = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    /**
     * Shared safety gate for every mutating action: never touch your own account
     * destructively here, and never touch a super_admin unless you are one.
     */
    private function guardManage(User $user): bool
    {
        if ($user->id === Auth::id()) {
            $this->dispatch('nx-toast', type: 'error', message: 'Manage your own account from “My account”.');

            return false;
        }
        if ($user->hasRole('super_admin') && ! Auth::user()->hasRole('super_admin')) {
            $this->dispatch('nx-toast', type: 'error', message: 'Only a super admin can manage a super admin.');

            return false;
        }

        return true;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->viewingId = $this->viewingId === $id ? null : $id;
    }

    /** Deactivate / reactivate an account (never delete — that's S26). */
    public function toggleActive(int $id, AccountService $accounts): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);

        // Never let an admin lock themselves out, and never touch a super admin
        // unless you are one.
        if ($user->id === Auth::id()) {
            $this->dispatch('nx-toast', type: 'error', message: 'You can’t deactivate your own account here.');

            return;
        }
        if ($user->hasRole('super_admin') && ! Auth::user()->hasRole('super_admin')) {
            $this->dispatch('nx-toast', type: 'error', message: 'Only a super admin can manage a super admin.');

            return;
        }

        if ($user->isDeactivated()) {
            $accounts->reactivate($user);
            $msg = $user->name.' reactivated.';
        } else {
            $accounts->deactivate($user);
            $msg = $user->name.' deactivated.';
        }
        Auditor::log('admin.user_toggled', 'User', $user->id, ['active' => ! $user->fresh()->isDeactivated()]);
        $this->dispatch('nx-toast', type: 'success', message: $msg);
    }

    public function editUser(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);
        if (! $this->guardManage($user)) {
            return;
        }
        $this->viewingId = $id;
        $this->edit_name = (string) $user->name;
        $this->edit_email = (string) $user->email;
        $this->edit_phone = (string) $user->phone;
        $this->editing = true;
        $this->tempPassword = null;
    }

    public function cancelEdit(): void
    {
        $this->reset('editing', 'edit_name', 'edit_email', 'edit_phone');
    }

    public function saveUser(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($this->viewingId);
        if (! $this->guardManage($user)) {
            return;
        }

        $this->validate([
            'edit_name' => ['required', 'string', 'max:120'],
            'edit_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'edit_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $emailChanged = $this->edit_email !== $user->email;
        $user->forceFill([
            'name' => trim($this->edit_name),
            'email' => $this->edit_email,
            'phone' => $this->edit_phone ?: null,
            // A changed email must be re-verified (same rule as self-service).
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Auditor::log('admin.user_profile_updated', 'User', $user->id, ['email_changed' => $emailChanged]);
        $this->editing = false;
        $this->dispatch('nx-toast', type: 'success', message: 'User updated.');
    }

    /** Email the user a standard password-reset link. */
    public function sendPasswordReset(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);
        if (! $this->guardManage($user)) {
            return;
        }

        Password::sendResetLink(['email' => $user->email]);
        Auditor::log('admin.user_password_reset_emailed', 'User', $user->id);
        $this->dispatch('nx-toast', type: 'success', message: 'Password-reset link sent to '.$user->email.'.');
    }

    /** Generate a temporary password and show it to the admin once to relay. */
    public function generateTempPassword(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);
        if (! $this->guardManage($user)) {
            return;
        }

        $password = Str::password(14);
        $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($password)])->save();
        $this->forceLogoutUser($user); // old sessions can't keep the old password alive

        $this->viewingId = $id;
        $this->tempPassword = $password; // shown once in the panel, never stored
        Auditor::log('admin.user_temp_password_set', 'User', $user->id);
    }

    /** Revoke all of a user's sessions (force-logout everywhere). */
    public function forceLogout(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);
        if (! $this->guardManage($user)) {
            return;
        }

        $count = $this->forceLogoutUser($user);
        Auditor::log('admin.user_force_logout', 'User', $user->id, ['sessions' => $count]);
        $this->dispatch('nx-toast', type: 'success', message: 'Signed '.$user->name.' out of all devices.');
    }

    private function forceLogoutUser(User $user): int
    {
        // Cycle the remember token so "remember me" cookies stop working…
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        // …and drop the database session rows for this user (database driver).
        try {
            return DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
            return 0; // non-database session driver — remember-token cycle still applied
        }
    }

    /** Grant/revoke the admin role — super_admin only, never on yourself. */
    public function toggleAdmin(int $id): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            $this->dispatch('nx-toast', type: 'error', message: 'You can’t change your own admin role here.');

            return;
        }

        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
            $user->forceFill(['role' => 'user'])->save();
            $msg = $user->name.' is no longer an admin.';
        } else {
            $user->assignRole('admin');
            $user->forceFill(['role' => 'admin'])->save();
            $msg = $user->name.' is now an admin.';
        }
        Auditor::log('admin.user_role_changed', 'User', $user->id, ['admin' => $user->hasRole('admin')]);
        $this->dispatch('nx-toast', type: 'success', message: $msg);
    }

    /**
     * One-click promote a user to Merchant V1/V2 (BUILD-4 §4.2) — free,
     * admin-initiated, no application. Fires a celebratory confetti event + a
     * toast; the promotion service handles the role + audit.
     *
     * @param  'v1'|'v2'  $tier
     */
    public function promote(int $id, string $tier, MerchantService $merchants): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);
        $merchants->promote($user, $tier === 'v2' ? 'v2' : 'v1', Auth::user(), 'Admin promotion');

        $this->dispatch('reward-claimed'); // reuse the confetti celebration
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'Merchant promoted',
            message: $user->name.' is now a Merchant '.strtoupper($tier).'.');
    }

    public function render()
    {
        $minSpend = MerchantSettings::minSpendUsd();
        $minReferrals = MerchantSettings::minReferrals();

        $users = User::query()
            ->with('wallet')
            ->withCount(['referralsMade', 'referralsMade as verified_referrals_count' => fn ($q) => $q->whereHas('referred.wallet', fn ($w) => $w->where('total_spent', '>', 0))])
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->when($this->filter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filter === 'deactivated', fn ($q) => $q->where('is_active', false))
            ->when($this->filter === 'merchants', fn ($q) => $q->whereNotNull('merchant_id')
                ->orWhereHas('merchantAccount'))
            ->when($this->filter === 'staff', fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['staff', 'admin', 'super_admin'])))
            // "Ready to promote" (§4.3): not already an active merchant, and meeting
            // any eligibility path (spend / referrals / paid enrollment).
            ->when($this->filter === 'ready', fn ($q) => $q
                ->whereDoesntHave('merchantAccount', fn ($m) => $m->where('status', 'active'))
                ->where(fn ($w) => $w
                    ->whereNotNull('merchant_enrollment_paid_at')
                    ->orWhereHas('wallet', fn ($wl) => $wl->where('total_spent', '>=', $minSpend))
                    ->orHas('referralsMade', '>=', $minReferrals)))
            ->latest('id')
            ->paginate(15);

        // Engagement score per listed user (§4.1), computed from the loaded fields.
        $scores = [];
        foreach ($users as $u) {
            $scores[$u->id] = EngagementScore::for($u);
        }

        $viewing = $this->viewingId ? User::with('wallet')->find($this->viewingId) : null;

        // Recent sessions (login history) for the viewed user — database driver.
        $sessions = collect();
        if ($viewing) {
            try {
                $sessions = DB::table('sessions')->where('user_id', $viewing->id)
                    ->orderByDesc('last_activity')->limit(10)->get()
                    ->map(fn ($s) => [
                        'ip' => $s->ip_address,
                        'agent' => Str::limit((string) $s->user_agent, 80),
                        'when' => \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                    ]);
            } catch (\Throwable) {
                $sessions = collect();
            }
        }

        return view('livewire.admin.users', [
            'users' => $users,
            'scores' => $scores,
            'viewing' => $viewing,
            'sessions' => $sessions,
            'totals' => [
                'all' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'deactivated' => User::where('is_active', false)->count(),
            ],
        ]);
    }
}
