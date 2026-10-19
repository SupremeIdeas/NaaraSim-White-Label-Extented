<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a default super_admin so the operator can sign in the moment the
 * installer finishes (the pattern CodeCanyon products ship with). Idempotent —
 * only created if it doesn't already exist.
 *
 * SECURITY: change this password immediately after first login. The Done screen
 * and DEPLOYMENT.md both say so.
 */
class DefaultAdminSeeder extends Seeder
{
    public const EMAIL = 'adminmaster1234@gmail.com';

    public const PASSWORD = '123456789@AdminMaster';

    public function run(): void
    {
        if (User::query()->where('email', self::EMAIL)->exists()) {
            return;
        }

        $admin = User::create([
            'name' => 'Supreme Admin',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
            'email_verified_at' => now(),
            'referral_code' => Str::upper(Str::random(8)),
        ]);

        $admin->setRole('super_admin');
        $admin->assignRole('super_admin');
    }
}
