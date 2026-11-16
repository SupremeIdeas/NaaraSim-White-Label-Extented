<?php

namespace App\Http\Controllers;

use App\Support\AppLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AppLockController extends Controller
{
    public function lock(Request $request): JsonResponse
    {
        if (AppLock::enabled($request->user())) {
            AppLock::lock($request->session());
        }

        return response()->json(['locked' => true]);
    }

    public function unlockWithPassword(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        // The app password can be short, so guess attempts are capped per member: 8 wrong tries lock the password path for 15 minutes (the fingerprint still works).
        $key = 'app-lock-fail:'.$request->user()->getAuthIdentifier();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 8)) {
            $mins = max(1, (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($key) / 60));

            return response()->json(['message' => "Too many wrong tries. Use your fingerprint, or try again in {$mins} minute(s).", 'errors' => ['password' => ["Too many wrong tries. Try again in {$mins} minute(s)."]]], 429);
        }
        $u = $request->user();
        // The app password is its own secret; the account password is only the fallback for a member who has not set one yet.
        $hash = (string) ($u->app_lock_hash ?: $u->password);
        if (! Hash::check((string) $request->input('password'), $hash)) {
            \Illuminate\Support\Facades\RateLimiter::hit($key, 900);
            return response()->json(['message' => 'That app password is not right.', 'errors' => ['password' => ['That app password is not right.']]], 422);
        }
        \Illuminate\Support\Facades\RateLimiter::clear($key);
        AppLock::unlock($request->session());

        return response()->json(['redirect' => $this->back($request)]);
    }

    /** After a successful passkey confirm (the passkey route stamps the session), open the lock. */
    public function unlockWithPasskey(Request $request): JsonResponse
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if ($confirmedAt < time() - 60 || ! $request->user()->passkeys()->exists()) {
            return response()->json(['message' => 'Fingerprint was not confirmed.'], 422);
        }
        AppLock::unlock($request->session());

        return response()->json(['redirect' => $this->back($request)]);
    }

    /** "Lost your phone?": sign every device out and remove the fingerprint devices, then require a fresh password sign-in. */
    public function secure(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->passkeys()->delete();
        $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getAuthIdentifier())->delete();
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['redirect' => route('login')]);
    }

    private function back(Request $request): string
    {
        $to = (string) $request->session()->pull('app_lock_back', '/');

        // Only ever return to this site.
        return str_starts_with($to, url('/')) || str_starts_with($to, '/') ? $to : '/';
    }
}
