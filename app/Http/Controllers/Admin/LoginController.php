<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The dedicated admin sign-in page at {ADMIN_PATH}/login. It renders an
 * admin-branded form that POSTs to Fortify's standard /login endpoint — so it
 * reuses the whole battle-tested pipeline (rate limiting, the 2FA challenge,
 * session regeneration) rather than re-implementing auth. On success Fortify's
 * LoginResponse returns the user to the intended URL, which we point at the
 * admin dashboard. Guests reach this page directly (it is NOT behind EnsureAdmin);
 * an already-signed-in admin is sent straight in.
 */
class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user !== null && $user->hasAnyRole(['super_admin', 'admin', 'staff'])) {
            return redirect()->route('admin.dashboard');
        }

        // Land on the admin dashboard after signing in, unless the guest was
        // already heading somewhere specific in the panel (EnsureAdmin set it).
        if (! $request->session()->has('url.intended')) {
            redirect()->setIntendedUrl(route('admin.dashboard'));
        }

        return view('auth.admin-login');
    }
}
