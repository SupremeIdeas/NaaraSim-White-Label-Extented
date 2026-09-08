<?php

namespace App\Http\Middleware;

use App\Services\Kyc\KycService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind an approved KYC level (ROADMAP §Layer 0.3). Aliased as
 * `kyc:2` (withdraw) / `kyc:3` (become a merchant). A user below the level is
 * sent to the identity-verification page rather than hard-blocked.
 */
class EnsureKycLevel
{
    public function __construct(private KycService $kyc)
    {
    }

    public function handle(Request $request, Closure $next, int $level = 2): Response
    {
        $user = $request->user();
        if ($user === null || ! $this->kyc->hasLevel($user, $level)) {
            if ($request->expectsJson()) {
                abort(403, 'Identity verification required.');
            }

            return redirect()->route('account.verify')->with('status', 'Please verify your identity to continue.');
        }

        return $next($request);
    }
}
