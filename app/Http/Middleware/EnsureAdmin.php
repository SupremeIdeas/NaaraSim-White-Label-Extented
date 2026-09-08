<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gate the admin panel (blueprint Section 25). Hardening, in order:
 *
 *  1. Optional IP allow-list — an IP outside it gets a plain 404 and never
 *     learns the admin area exists.
 *  2. Role — guests and anyone without an admin-panel role (super_admin, admin
 *     or staff) get a plain 404 (never a login page, so the secret path reveals
 *     nothing). Per-page scopes for staff are enforced by the `role`/
 *     `permission` middleware on the individual routes (blueprint Section 27).
 *  3. Two-factor — a panel user without a confirmed TOTP secret is redirected
 *     to the admin security page to enrol before any other admin page opens.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $allow = config('admin.ip_allowlist', []);
        if (! empty($allow) && ! in_array($request->ip(), $allow, true)) {
            // The response is a plain 404 (the panel stays invisible), but the
            // reason is LOGGED so a locked-out operator with server access can
            // actually diagnose it instead of chasing a phantom routing bug.
            Log::warning('[admin] Blocked by IP allow-list.', [
                'ip' => $request->ip(),
                'allowed' => $allow,
                'path' => $request->path(),
            ]);

            throw new NotFoundHttpException;
        }

        // Panel-managed IP + country allow-lists (owner request). Off by default;
        // both fail-open on unknowns and log every block (see AdminAccess).
        if (\App\Support\AdminAccess::blockReason($request) !== null) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();

        // A GUEST at the admin path is sent to the DEDICATED admin login and
        // returned here after (the owner uses /adminmaster as the admin entry
        // point). redirect()->guest() remembers the intended admin URL. This
        // works from any browser/device — access is session-based, not
        // device-locked (an optional IP allow-list is the only location gate).
        // Authenticated NON-admins still get a plain 404, so the panel stays
        // invisible to ordinary users (blueprint Section 25).
        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }
        if (! $user->hasAnyRole(['super_admin', 'admin', 'staff'])) {
            throw new NotFoundHttpException;
        }

        // Presence heartbeat (Module 25) — mark this panel user online so they
        // can be shown as available to take support tickets.
        \App\Support\StaffPresence::heartbeat($user);

        // 2FA is opt-in: only force enrolment when a super-admin has turned it on.
        if (\App\Support\SecuritySettings::admin2faRequired()
            && ! $this->hasConfirmedTwoFactor($user)
            && ! $request->routeIs('admin.security')) {
            // Anti-lockout rail: if NOBODY (no super_admin) has actually enrolled
            // 2FA yet, enforcing it would trap every admin on the security page
            // with no way out (e.g. no authenticator handy). Never do that —
            // allow access and log it, so turning the toggle on is reversible
            // without shell access. Once a super_admin confirms 2FA, enforcement
            // kicks in for everyone as intended.
            if (! $this->anySuperAdminHasTwoFactor()) {
                Log::warning('[admin] 2FA is required but no super_admin has enrolled — allowing access to avoid a lockout. Enrol 2FA or turn the requirement off.', [
                    'user_id' => $user->id,
                ]);
            } else {
                return redirect()->route('admin.security');
            }
        }

        return $next($request);
    }

    /** Whether at least one super_admin has a confirmed TOTP enrolment. */
    private function anySuperAdminHasTwoFactor(): bool
    {
        return User::role('super_admin')
            ->whereNotNull('two_factor_secret')
            ->whereNotNull('two_factor_confirmed_at')
            ->exists();
    }

    /**
     * A confirmed TOTP enrolment: the secret exists AND was confirmed (Fortify
     * runs with the 'confirm' feature, so confirmed_at is the reliable signal).
     */
    private function hasConfirmedTwoFactor(User $user): bool
    {
        return ! is_null($user->two_factor_secret)
            && ! is_null($user->two_factor_confirmed_at);
    }
}
