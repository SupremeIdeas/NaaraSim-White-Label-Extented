<?php

namespace App\Livewire;

use App\Actions\Fortify\UpdateUserPassword;
use App\Notifications\TwoFactorNotification;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer Security Center (Module 23) — the account-security surface a normal
 * user never had: change password, enrol/manage TOTP two-factor, change email
 * (with re-verification), sign out other sessions, and link/unlink Google.
 * Reuses Fortify's action classes (the user is already authenticated).
 */
#[Layout('components.layouts.customer')]
class SecurityCenter extends Component
{
    // Change password
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Change email
    public string $new_email = '';

    public string $email_password = '';

    // 2FA
    public bool $showing2faSetup = false;

    public string $code = '';

    public ?string $flash = null;

    public ?string $flashType = 'success'; // success | error

    public function mount(): void
    {
        $this->new_email = Auth::user()->email;
    }

    // -- Password ----------------------------------------------------------

    public function updatePassword(UpdateUserPassword $updater): void
    {
        $updater->update(Auth::user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->banner('Password updated. We emailed you a confirmation.');
        Auditor::log('account.password_changed');
    }

    // -- Email -------------------------------------------------------------

    public function updateEmail(): void
    {
        $user = Auth::user();

        $this->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_password' => ['required', 'current_password:web'],
        ], [
            'email_password.current_password' => 'That password is incorrect.',
        ]);

        if ($this->new_email === $user->email) {
            $this->banner('That is already your email.', 'error');

            return;
        }

        $user->forceFill([
            'email' => $this->new_email,
            'email_verified_at' => null, // must re-verify the new address
        ])->save();
        $user->sendEmailVerificationNotification();

        $this->reset('email_password');
        $this->banner('Email changed. Please confirm it from the link we just sent.');
        Auditor::log('account.email_changed');
    }

    // -- Two-factor --------------------------------------------------------

    public function enable2fa(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());
        $this->showing2faSetup = true;
    }

    public function confirm2fa(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => 'required|string']);

        try {
            $confirm(Auth::user(), $this->code);
        } catch (ValidationException) {
            $this->addError('code', 'That code is invalid or expired — try the current one.');

            return;
        }

        $this->showing2faSetup = false;
        $this->code = '';
        $this->banner('Two-factor authentication is on.');
        Auditor::log('account.2fa_enabled');
        Auth::user()->notify(new TwoFactorNotification(enabled: true));
    }

    public function disable2fa(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());
        $this->showing2faSetup = false;
        $this->banner('Two-factor authentication disabled.', 'error');
        Auditor::log('account.2fa_disabled');
        Auth::user()->notify(new TwoFactorNotification(enabled: false));
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());
        $this->banner('New recovery codes generated — save them somewhere safe.');
    }

    // -- Sessions ----------------------------------------------------------

    /**
     * Sign out every other browser session for this user. Works on the database
     * session driver (our shared-hosting default) by deleting the other rows;
     * degrades gracefully on other drivers.
     */
    public function signOutOtherSessions(): void
    {
        $user = Auth::user();

        try {
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', session()->getId())
                    ->delete();
            }
            Auth::logoutOtherDevices($this->current_password ?: null);
            $this->banner('Signed out of your other sessions.');
            Auditor::log('account.sessions_cleared');
        } catch (\Throwable) {
            $this->banner('Could not sign out other sessions right now.', 'error');
        }
    }

    // -- Google ------------------------------------------------------------

    public function unlinkGoogle(): void
    {
        $user = Auth::user();

        // Safe to unlink: they can always regain access via email + password
        // reset. Keep the avatar.
        $user->forceFill(['google_id' => null])->save();
        $this->banner('Google account unlinked.');
        Auditor::log('auth.google_unlinked');
    }

    private function banner(string $message, string $type = 'success'): void
    {
        $this->flash = $message;
        $this->flashType = $type;
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        $enabled = ! is_null($user->two_factor_secret);
        $confirmed = $enabled && ! is_null($user->two_factor_confirmed_at);

        $qr = $secret = null;
        $recoveryCodes = [];
        if ($enabled) {
            $qr = $user->twoFactorQrCodeSvg();
            $secret = decrypt($user->two_factor_secret);
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];
        }

        // Active sessions (database driver only).
        $sessions = collect();
        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn ($s) => [
                    'current' => $s->id === session()->getId(),
                    'ip' => $s->ip_address,
                    'agent' => $s->user_agent,
                    'last' => \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                ]);
        }

        return view('livewire.security-center', [
            'user' => $user,
            'enabled' => $enabled,
            'confirmed' => $confirmed,
            'qr' => $qr,
            'secret' => $secret,
            'recoveryCodes' => $recoveryCodes,
            'sessions' => $sessions,
            'googleLinked' => ! is_null($user->google_id),
        ]);
    }
}
