<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Support\Auditor;
use App\Support\SocialAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Social sign-in for every configured provider (Module 23, extended per owner
 * request). The callback resolves three cases, provider-agnostically:
 *   1. Known (provider, provider_id) -> log in.
 *   2. Email already exists          -> LINK the provider to that account (safe:
 *      the provider has verified the email) and log in.
 *   3. Brand new                     -> create a verified account, assign the
 *      user role, send the welcome email, log in.
 *
 * Each provider route 404s until its keys are configured (SocialAuth::enabled),
 * so buttons never dangle. The legacy users.google_id is preserved on link.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        abort_unless(SocialAuth::enabled($provider), 404);

        return Socialite::driver(SocialAuth::driver($provider))
            ->redirectUrl(SocialAuth::redirect($provider))
            ->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(SocialAuth::enabled($provider), 404);

        try {
            $social = Socialite::driver(SocialAuth::driver($provider))
                ->redirectUrl(SocialAuth::redirect($provider))
                ->user();
        } catch (\Throwable $e) {
            return redirect('/login')->withErrors([
                'email' => ucfirst($provider).' sign-in failed. Please try again or use your email and password.',
            ]);
        }

        $providerId = (string) $social->getId();
        $email = $social->getEmail();

        // 1. Known identity.
        $link = SocialAccount::where('provider', $provider)->where('provider_id', $providerId)->first();
        $user = $link?->user;

        // 2. Link to an existing email account.
        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        // 3. Brand new account.
        if (! $user) {
            $user = User::create([
                'name' => $social->getName() ?: Str::before((string) $email, '@') ?: ucfirst($provider).' user',
                'email' => $email,
                'password' => bcrypt(Str::random(40)), // unusable until they set one
                'avatar' => $social->getAvatar(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save(); // provider-verified
            $user->assignRole('user');
            try {
                $user->notify(new WelcomeNotification);
            } catch (\Throwable) {
                // best-effort
            }
            Auditor::log('auth.social_registered', User::class, $user->id, ['provider' => $provider]);
        }

        // Ensure the identity is linked (idempotent) + keep google_id for legacy.
        SocialAccount::firstOrCreate(
            ['provider' => $provider, 'provider_id' => $providerId],
            ['user_id' => $user->id, 'avatar' => $social->getAvatar()],
        );
        if ($provider === 'google' && ! $user->google_id) {
            $user->forceFill(['google_id' => $providerId])->save();
        }
        if (! $user->avatar && $social->getAvatar()) {
            $user->forceFill(['avatar' => $social->getAvatar()])->save();
        }
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
