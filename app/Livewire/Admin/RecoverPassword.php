<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Auditor;
use App\Support\SecurityQuestions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin password recovery via security questions (owner request — fix_admin.md
 * Part 2). A self-service path for a locked-out admin when email reset isn't
 * available. Hard rate-limited and fully audited — this is a sensitive endpoint.
 *
 * Step 1: enter email. Step 2: answer the account's recovery questions. Step 3:
 * set a new password. Only works for admin-panel accounts that have configured
 * questions; everything else returns the same generic message (no enumeration).
 */
#[Layout('components.layouts.auth')]
class RecoverPassword extends Component
{
    public int $step = 1;

    public string $email = '';

    /** question text => answer */
    public array $answers = [];

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $error = null;

    /** @var list<string> */
    public array $prompts = [];

    private const MAX_ATTEMPTS = 5;

    private function throttleKey(): string
    {
        return 'admin-recover:'.mb_strtolower($this->email).'|'.request()->ip();
    }

    /** Resolve an eligible account: an admin-panel user with questions set. */
    private function eligible(): ?User
    {
        $user = User::where('email', $this->email)->first();
        if ($user && $user->hasAnyRole(['super_admin', 'admin', 'staff']) && SecurityQuestions::configured($user)) {
            return $user;
        }

        return null;
    }

    public function lookup(): void
    {
        $this->error = null;
        $this->validate(['email' => 'required|email']);

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $this->error = 'Too many attempts. Please try again later.';

            return;
        }

        $user = $this->eligible();
        Auditor::log('admin.recovery_lookup', $user ? User::class : null, $user?->id, ['email' => $this->email, 'found' => (bool) $user]);

        // Same message whether or not the account exists (anti-enumeration), but
        // only advance with real prompts when it's genuinely recoverable.
        if (! $user) {
            $this->error = 'If this account has recovery questions set up, they will appear here. Otherwise, use email reset or the break-glass CLI.';

            return;
        }

        $this->prompts = SecurityQuestions::questionsFor($user);
        $this->answers = collect($this->prompts)->mapWithKeys(fn ($q) => [$q => ''])->all();
        $this->step = 2;
    }

    public function verify(): void
    {
        $this->error = null;

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $this->error = 'Too many attempts. Please try again later.';

            return;
        }

        $user = $this->eligible();
        if (! $user) {
            $this->step = 1;
            $this->error = 'Recovery is not available for this account.';

            return;
        }

        if (! SecurityQuestions::verify($user, $this->answers)) {
            RateLimiter::hit($this->throttleKey(), 900); // 15-min window
            Auditor::log('admin.recovery_failed', User::class, $user->id, ['email' => $this->email]);
            $this->error = 'Those answers did not match. Please try again.';

            return;
        }

        RateLimiter::clear($this->throttleKey());
        $this->step = 3;
    }

    public function resetPassword(): void
    {
        $this->error = null;
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->eligible();
        if (! $user) {
            $this->step = 1;
            $this->error = 'Recovery is not available for this account.';

            return;
        }

        // Re-verify at the final step so answers can't be skipped by jumping steps.
        if (! SecurityQuestions::verify($user, $this->answers)) {
            $this->step = 1;
            $this->error = 'Recovery could not be completed. Please start again.';

            return;
        }

        $user->forceFill(['password' => Hash::make($this->password), 'is_active' => true])->save();
        Auditor::log('admin.recovery_password_reset', User::class, $user->id, ['email' => $this->email]);

        session()->flash('status', 'Your password has been reset. Please sign in.');

        $this->redirect(route('admin.login'));
    }

    public function render()
    {
        return view('livewire.admin.recover-password');
    }
}
