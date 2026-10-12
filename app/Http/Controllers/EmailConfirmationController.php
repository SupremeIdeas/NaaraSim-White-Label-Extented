<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The link in the "Confirm your email" mail. Unlike the framework route it needs no login, so a link opened on a different device or browser
 * still works. The signature (expiry + id + hash) is the proof of ownership; the account is only ever the one the link names.
 *
 *   same browser, signed in   -> dashboard + "Email confirmed" toast
 *   other device / signed out -> branded confirmation page; toast waits for the person's next sign-in
 *   already confirmed         -> friendly note, never an error page
 *   expired / tampered        -> explains why, with a one-click resend (signed in) or a sign-in link
 */
class EmailConfirmationController extends Controller
{
    public const PENDING_PREFIX = 'verified_notice_pending:';

    public function __invoke(Request $request, int $id, string $hash)
    {
        $user = User::find($id);
        $matches = $user && hash_equals(sha1($user->getEmailForVerification()), $hash);

        if (! $request->hasValidSignature()) {
            return $this->state('expired', $user && $matches ? $user : null);
        }
        if (! $matches) {
            return $this->state('invalid');
        }

        $mine = $request->user()?->is($user) ?? false;

        if ($user->hasVerifiedEmail()) {
            return $mine
                ? redirect()->route('dashboard')->with('flash_toast', ['type' => 'info', 'title' => 'Already confirmed', 'message' => 'Your email is already confirmed. Nothing more to do.', 'variant' => 'hero'])
                : $this->state('already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($mine) {
            return redirect()->route('dashboard')->with('flash_toast', self::successToast());
        }
        Cache::put(self::PENDING_PREFIX.$user->id, 1, now()->addDays(7));

        return $this->state('done');
    }

    public static function successToast(): array
    {
        return ['type' => 'success', 'variant' => 'hero', 'title' => 'Email confirmed', 'message' => "You're all set. Welcome aboard."];
    }

    private function state(string $state, ?User $user = null)
    {
        return response()->view('auth.email-confirmation', ['state' => $state, 'canResend' => $user !== null && auth()->id() === $user->id], $state === 'invalid' ? 404 : 200);
    }
}
