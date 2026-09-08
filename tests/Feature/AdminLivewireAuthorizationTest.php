<?php

namespace Tests\Feature;

use App\Livewire\Admin\ErrorLogViewer;
use App\Livewire\Admin\Pricing;
use App\Livewire\Admin\PricingArchitect;
use App\Livewire\Admin\Splash;
use App\Livewire\Admin\SupportQueue;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Livewire authorization defence-in-depth (security audit).
 *
 * Livewire dispatches every component method to a single `/livewire/update`
 * endpoint that only re-applies its persistent-middleware allow-list — the
 * route's `role:` / `permission:` middleware is NOT re-run there. So a
 * component protected by route middleware alone would let any authenticated
 * user who holds a valid snapshot invoke its methods (e.g. rewrite platform
 * pricing). These components therefore re-authorize in booted(), which fires
 * on the initial render AND every subsequent update. This test proves a
 * non-privileged user is refused, and a privileged one is admitted.
 */
class AdminLivewireAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** @return array<string, array{0: class-string}> */
    public static function adminOnlyComponents(): array
    {
        return [
            'Pricing' => [Pricing::class],
            'PricingArchitect' => [PricingArchitect::class],
            'ErrorLogViewer' => [ErrorLogViewer::class],
            'Splash' => [Splash::class],
        ];
    }

    /**
     * @param  class-string  $component
     */
    #[DataProvider('adminOnlyComponents')]
    public function test_a_plain_user_is_refused_by_the_admin_gate(string $component): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test($component)
            ->assertForbidden();
    }

    /**
     * @param  class-string  $component
     */
    #[DataProvider('adminOnlyComponents')]
    public function test_an_admin_is_admitted(string $component): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test($component)->assertOk();
    }

    public function test_support_queue_needs_the_tickets_manage_scope(): void
    {
        // A staff account WITHOUT the scope is refused…
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Livewire::actingAs($staff)->test(SupportQueue::class)->assertForbidden();

        // …the same staff account WITH the scope is admitted.
        $staff->givePermissionTo('tickets.manage');
        Livewire::actingAs($staff->fresh())->test(SupportQueue::class)->assertOk();
    }
}
