<?php

namespace App\Services\Payouts\Hardening;

use App\Models\User;
use App\Notifications\PayoutStepUpCode;
use App\Services\Payouts\PayoutException;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Step-up authentication for the account-takeover paths (Addendum D-3.5): adding/changing a payout
 * account and every global-rail withdrawal. Uses the user's authenticator (Fortify 2FA) when they have
 * confirmed it, otherwise a 6-digit code emailed to them. Passing grants a short "fresh" window
 * (payouts.step_up.valid_minutes); the whole thing is OFF until the owner switches it on.
 */
class StepUpAuth
{
    private const MAX_ATTEMPTS = 5;

    private const MAX_CODES = 3;

    public function required(): bool
    {
        return PayoutSettings::stepUpRequired();
    }

    public function fresh(User $user): bool
    {
        return Cache::has($this->okKey($user));
    }

    public function usesAuthenticator(User $user): bool
    {
        return filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null;
    }

    /** @throws PayoutException */
    public function assertFresh(User $user): void
    {
        if ($this->required() && ! $this->fresh($user)) {
            throw new PayoutException('For your security, please verify it\'s you first (a quick code check), then try again.');
        }
    }

    /** Sends the email code (no-op for authenticator users). @return 'authenticator'|'email' */
    public function sendCode(User $user): string
    {
        if ($this->usesAuthenticator($user)) {
            return 'authenticator';
        }
        $sent = Cache::increment($this->sentKey($user)); // counts sends in the window
        if ($sent === 1) {
            Cache::put($this->sentKey($user), 1, 600);
        }
        if ($sent > self::MAX_CODES) {
            throw new PayoutException('Too many codes requested. Please wait a few minutes and try again.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->codeKey($user), Hash::make($code), 600);
        Cache::forget($this->tryKey($user));
        $user->notify(new PayoutStepUpCode($code));

        return 'email';
    }

    public function verify(User $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        $tries = (int) Cache::get($this->tryKey($user), 0);
        if ($tries >= self::MAX_ATTEMPTS) {
            throw new PayoutException('Too many attempts. Please request a new code.');
        }
        Cache::put($this->tryKey($user), $tries + 1, 600);

        $ok = $this->usesAuthenticator($user)
            ? (bool) app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $code)
            : (($hash = Cache::get($this->codeKey($user))) !== null && Hash::check($code, $hash));

        if ($ok) {
            Cache::put($this->okKey($user), 1, now()->addMinutes(PayoutSettings::stepUpValidMinutes()));
            Cache::forget($this->codeKey($user));
            Cache::forget($this->tryKey($user));
            Auditor::log('payout.step_up_passed', 'User', $user->id);
        }

        return $ok;
    }

    private function okKey(User $u): string { return 'payouts.stepup.ok.'.$u->id; }

    private function codeKey(User $u): string { return 'payouts.stepup.code.'.$u->id; }

    private function tryKey(User $u): string { return 'payouts.stepup.tries.'.$u->id; }

    private function sentKey(User $u): string { return 'payouts.stepup.sent.'.$u->id; }
}
