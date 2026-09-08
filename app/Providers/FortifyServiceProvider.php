<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Support\MerchantBranding;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Return users to the page they were heading to after login (so the
        // secret admin path works from any device — see LoginResponse).
        $this->app->singleton(
            LoginResponse::class,
            \App\Http\Responses\LoginResponse::class,
        );

        // After signup, play the Aurora Welcome entrance before the dashboard.
        $this->app->singleton(
            RegisterResponse::class,
            \App\Http\Responses\RegisterResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auth views (blueprint Section 3.1 / 19.1). Dark-mode Blade forms.
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register', [
            // Co-branding (ROADMAP §Layer 3.3): if the visitor arrived via a
            // merchant invite, show the merchant on the signup form.
            'inviteMerchant' => MerchantBranding::inviteMerchant(),
        ]));

        // The 2FA challenge page (TOTP — Google Authenticator/Authy/1Password —
        // or a recovery code). MUST be registered or a 2FA-enabled user (incl.
        // an admin) is stranded after entering their password. This is the extra
        // security layer that never blocks a legitimate login.
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        // Password-reset + email-verification UX (Module 22). Without these the
        // Fortify routes exist but have no page to render.
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input(Fortify::username()));
            $throttleKey = Str::transliterate($email.'|'.$request->ip());

            // Admins (super_admin/admin) must be able to log in from ANY device,
            // browser, or IP with just email + password and never hit a 429
            // (blueprint S3.1 / BUILD-1 §3.1). They get a high, non-IP-punitive
            // cap; everyone else keeps the tight 5/min brute-force guard. The
            // role lookup is cached briefly so it isn't a DB hit per attempt —
            // the SAME super_admin/admin source of truth, never a second flag.
            $isAdmin = $email !== '' && Cache::remember(
                'loginlimit:isadmin:'.md5($email),
                60,
                fn () => User::where('email', $email)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))
                    ->exists()
            );

            return Limit::perMinute($isAdmin ? 200 : 5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
