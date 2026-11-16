<?php

namespace App\Livewire\Account;

use App\Support\AppLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Account > App lock (fingerprint). Turning it on needs the member's own app password first; the fingerprint is the quick way to open the
 * locked app and the app password is the fallback. Changing anything needs the account password (so a borrowed phone cannot switch it off).
 */
#[Layout('components.layouts.app')]
class AppLockSettings extends Component
{
    public string $current = '';

    public string $newPassword = '';

    public string $confirmPassword = '';

    public string $minutes = '5';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        $m = Auth::user()->app_lock_minutes;
        $this->minutes = $m === null ? '5' : (string) $m;
    }

    private function confirmAccountPassword(): bool
    {
        if (! Hash::check($this->current, (string) Auth::user()->password)) {
            $this->error = 'Your account password is not right.';
            $this->flash = null;

            return false;
        }
        // The fingerprint registration route asks for a recently confirmed password; this proves it.
        session()->passwordConfirmed();
        $this->error = null;

        return true;
    }

    public function savePassword(): void
    {
        $this->validate([
            'newPassword' => ['required', 'string', 'min:6', 'max:100', 'same:confirmPassword'],
        ], ['newPassword.same' => 'The two app passwords do not match.', 'newPassword.min' => 'Use at least 6 characters.']);
        if (! $this->confirmAccountPassword()) {
            return;
        }
        Auth::user()->forceFill(['app_lock_hash' => Hash::make($this->newPassword)])->save();
        $this->reset('newPassword', 'confirmPassword');
        $this->flash = 'Your app password is saved.';
    }

    /** Called after the browser has registered the fingerprint, or to confirm the account password before it does. */
    public function prepare(): bool
    {
        return $this->confirmAccountPassword();
    }

    public function turnOn(): void
    {
        $user = Auth::user();
        if (! $user->app_lock_hash) {
            $this->error = 'Set your app password first, then turn the lock on.';

            return;
        }
        if (! $this->confirmAccountPassword()) {
            return;
        }
        $choices = array_keys(AppLock::CHOICES);
        $m = in_array((int) $this->minutes, $choices, true) ? (int) $this->minutes : 5;
        $user->forceFill(['app_lock_minutes' => $m])->save();
        AppLock::unlock(session()->driver());
        $this->flash = 'App lock is on.';
    }

    public function turnOff(): void
    {
        if (! $this->confirmAccountPassword()) {
            return;
        }
        Auth::user()->forceFill(['app_lock_minutes' => null])->save();
        $this->flash = 'App lock is off.';
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        return view('livewire.account.app-lock-settings', [
            'hasPassword' => (bool) $user->app_lock_hash,
            'on' => AppLock::enabled($user),
            'passkeys' => $user->passkeys()->latest()->get(),
        ])->title('App lock');
    }
}
