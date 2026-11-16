<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * App lock: after the chosen idle time (or whenever the app is left) the account shows a "Welcome back" screen and needs a fingerprint / face /
 * screen lock (passkey) or the password. The lock is enforced on the SERVER (session flag), never only hidden in the browser.
 */
class AppLock
{
    public const CHOICES = [0 => 'Whenever I leave the app', 1 => 'After 1 minute away', 5 => 'After 5 minutes away', 15 => 'After 15 minutes away', 60 => 'After 1 hour away'];

    public static function enabled(?User $user): bool
    {
        return $user !== null && $user->app_lock_minutes !== null;
    }

    public static function minutes(?User $user): ?int
    {
        return self::enabled($user) ? (int) $user->app_lock_minutes : null;
    }

    public static function isLocked(Session $s, User $user): bool
    {
        if (! self::enabled($user)) {
            return false;
        }
        if ($s->get('app_locked') === true) {
            return true;
        }
        $last = (int) $s->get('app_last_active', 0);
        $mins = (int) $user->app_lock_minutes;
        // A fresh session (just signed in) is active; "whenever I leave" is driven by the browser telling us it was hidden.
        if ($last > 0 && $mins > 0 && (time() - $last) > $mins * 60) {
            $s->put('app_locked', true);

            return true;
        }

        return false;
    }

    public static function touch(Session $s): void
    {
        $s->put('app_last_active', time());
    }

    public static function lock(Session $s): void
    {
        $s->put('app_locked', true);
    }

    public static function unlock(Session $s): void
    {
        $s->forget('app_locked');
        self::touch($s);
    }
}
