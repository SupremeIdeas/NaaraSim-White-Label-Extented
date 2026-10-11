<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Addendum D-3.19: the two payout staff scopes. RoleSeeder creates them on a fresh install; this makes sure an
 * EXISTING install (which will not re-run the seeder) has them too, and that `admin` holds them — otherwise the
 * payouts page, now guarded by these scopes, would be closed to admins after the update. Idempotent.
 */
return new class extends Migration
{
    private const SCOPES = ['payouts.review', 'payouts.finance'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::SCOPES as $scope) {
            Permission::findOrCreate($scope, 'web');
        }
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo(self::SCOPES);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Additive and harmless to keep; scopes are not removed so granted staff access is never silently lost.
    }
};
