<?php

namespace Database\Seeders;

use App\Support\StaffScopes;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the four platform roles (blueprint Section 3.1) and the granular
     * staff scopes (Section 27).
     *
     * super_admin bypasses every gate (Gate::before in AppServiceProvider);
     * admin + super_admin may view Horizon and hold every scope; staff hold
     * only what a super admin grants them; user has none.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [];
        // `merchant` (ROADMAP §Layer 3) is an additive role a verified user gains
        // on top of `user` — it never replaces it, and carries no admin scopes.
        foreach (['super_admin', 'admin', 'staff', 'user', 'merchant'] as $role) {
            $roles[$role] = Role::findOrCreate($role, 'web');
        }

        foreach (StaffScopes::all() as $scope) {
            Permission::findOrCreate($scope, 'web');
        }

        // admin is broad — it holds every scope. staff are granted per-account.
        $roles['admin']->givePermissionTo(StaffScopes::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
