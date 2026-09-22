<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 5 #16 (final hardening): `role` is a privilege column, not a plain
 * profile field — it must never be settable via mass assignment (a
 * request-shaped array reaching create()/fill()/update()). Only
 * User::setRole() (forceFill under the hood) may change it.
 */
class UserRoleMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_not_in_the_fillable_list(): void
    {
        $this->assertNotContains('role', (new User)->getFillable());
    }

    public function test_mass_assigning_role_via_create_is_silently_ignored(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'masstest@example.com',
            'password' => 'secret-password',
            'role' => 'super_admin',
        ]);

        $this->assertNotSame('super_admin', $user->fresh()->role);
    }

    public function test_mass_assigning_role_via_fill_is_silently_ignored(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $user->fill(['role' => 'super_admin'])->save();

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_set_role_changes_the_column(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $user->setRole('admin');

        $this->assertSame('admin', $user->fresh()->role);
    }
}
