<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Auditor;
use Illuminate\Console\Command;

/**
 * Break-glass recovery (owner request): clear an admin's TOTP 2FA enrolment from
 * the shell, for a self-hosted operator locked out of the UI (e.g. 2FA was
 * required but never successfully enrolled). No database hand-editing needed.
 *
 *   php artisan admin:reset-2fa owner@example.com
 *
 * This only REMOVES the 2FA secret so the admin can sign in with their password
 * and re-enrol; it never sets or reveals one.
 */
class AdminResetTwoFactor extends Command
{
    protected $signature = 'admin:reset-2fa {email : The admin/staff account email}';

    protected $description = 'Clear an admin account\'s two-factor (TOTP) enrolment (break-glass recovery)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Auditor::log('admin.2fa_reset_cli', User::class, $user->id, ['email' => $email]);

        $this->info("Two-factor authentication cleared for {$email}.");
        $this->line('They can now sign in with their password and re-enrol from the admin Security page.');

        return self::SUCCESS;
    }
}
