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
 * SECURITY: the installer never uses the constants below on a real install; it calls use() with the operator's own email and a freshly generated
 * password (shown once on the Done screen). The constants only serve `db:seed` in local development and the test suite.
 */
class DefaultAdminSeeder extends Seeder
{
    public const EMAIL = 'adminmaster1234@gmail.com';

    public const PASSWORD = '123456789@AdminMaster';

    private static ?string $email = null;

    private static ?string $password = null;

    /** The installer hands over the credentials chosen for this install before it seeds. */
    public static function use(string $email, string $password): void
    {
        self::$email = $email;
        self::$password = $password;
    }

    public static function forget(): void
    {
        self::$email = self::$password = null;
    }

    public function run(): void
    {
        $email = self::$email ?? self::EMAIL;
        $password = self::$password ?? self::PASSWORD;
        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $admin = User::create([
            'name' => 'Supreme Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
            'referral_code' => Str::upper(Str::random(8)),
        ]);

        $admin->setRole('super_admin');
        $admin->assignRole('super_admin');
    }
}
