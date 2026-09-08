<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Auditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Break-glass recovery (owner request): set a new password for an admin account
 * from the shell, for a self-hosted operator locked out of the UI when email
 * reset isn't available (e.g. mail not configured yet on a fresh install).
 *
 *   php artisan admin:reset-password owner@example.com
 *   php artisan admin:reset-password owner@example.com --password="NewSecret123!"
 *
 * With no --password, a strong one is generated and printed ONCE. The account is
 * also reactivated (a deactivated admin can't sign in).
 */
class AdminResetPassword extends Command
{
    protected $signature = 'admin:reset-password
        {email : The admin/staff account email}
        {--password= : Set this password instead of generating one}';

    protected $description = 'Set a new password for an admin account (break-glass recovery)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'is_active' => true, // a deactivated admin can't sign in
        ])->save();

        Auditor::log('admin.password_reset_cli', User::class, $user->id, ['email' => $email]);

        $this->info("Password updated for {$email}.");
        if (! $this->option('password')) {
            $this->newLine();
            $this->line('  New password: <fg=yellow>'.$password.'</>');
            $this->newLine();
            $this->comment('Copy it now — it is not stored or shown again. Change it after signing in.');
        }

        return self::SUCCESS;
    }
}
