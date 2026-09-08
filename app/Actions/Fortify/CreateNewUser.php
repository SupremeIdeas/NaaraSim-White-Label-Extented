<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        // Branded, queued welcome email (Module 22). Never let a mail hiccup
        // break registration — the verification email is sent separately by the
        // MustVerifyEmail flow regardless.
        try {
            $user->notify(new \App\Notifications\WelcomeNotification);
        } catch (\Throwable) {
            // swallow — welcome mail is best-effort.
        }

        // Merchant invite (ROADMAP §Layer 3.3): if this signup came through a
        // /merchant/{slug}/join link, permanently link the account to that
        // merchant so co-branding + earnings follow.
        \App\Support\MerchantBranding::consumeInviteFor($user);

        // NaaraCredits signup bonus (loyalty module) — best-effort, idempotent.
        app(\App\Services\Credits\CreditService::class)->grantOnce(
            $user,
            (float) \App\Support\CreditSettings::get('signup_bonus', 0),
            'signup',
            'Welcome bonus',
        );

        return $user;
    }
}
