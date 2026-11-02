<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminPathChangedNotification;
use App\Support\AdminPath;
use App\Support\MailSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA-PROMPT-22 — dynamic/database-backed admin login path. The admin
 * panel's URL segment is no longer a boot-time env literal alone: it can be
 * changed live from the dashboard (super_admin only), takes effect on the
 * very next request, and the fallback (env/config) still holds until it is.
 */
class AdminPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(string $password = 'current-pass-1'): User
    {
        $u = User::factory()->create(['is_active' => true, 'password' => Hash::make($password)]);
        $u->assignRole('super_admin');

        return $u->fresh();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u->fresh();
    }

    public function test_the_config_fallback_default_diverges_from_the_master_convention(): void
    {
        // The literal fallback baked into config/admin.php is this repo's
        // own default (master: "adminmaster"); a white-label fork keeps a
        // different literal here and must never have it overwritten by a
        // master sync. This test just pins today's actual default so an
        // accidental revert to "adminmaster" (or anything else) is caught.
        $this->assertStringContainsString(
            "env('ADMIN_PATH', 'adminmaster')",
            file_get_contents(base_path('config/admin.php'))
        );
    }

    public function test_admin_path_resolves_the_database_setting_first_then_falls_back_to_config(): void
    {
        // No Setting row yet -> falls back to config('admin.path').
        $this->assertSame(config('admin.path'), AdminPath::current());

        // Once a Setting row exists, it wins over the config literal.
        Setting::setValue(AdminPath::SETTING_KEY, 'my-custom-gate', 'security');

        $this->assertSame('my-custom-gate', AdminPath::current());
    }

    public function test_changing_the_path_takes_effect_on_the_very_next_request(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->get('/'.config('admin.path'))->assertOk();

        Livewire::actingAs($super)->test(Security::class)
            ->set('admin_path', 'freshly-set-path')
            ->set('admin_path_confirmation', 'freshly-set-path')
            ->set('admin_path_current_password', 'current-pass-1')
            ->call('saveAdminPath')
            ->assertHasNoErrors();

        // The old path is gone immediately — plain 404, not a redirect.
        $this->actingAs($super)->get('/'.config('admin.path'))->assertNotFound();

        // The new path works right away, same request cycle onward.
        $this->actingAs($super)->get('/freshly-set-path')->assertOk();
    }

    public function test_a_wrong_or_stale_admin_path_gets_a_plain_404_never_a_redirect(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/totally-made-up-gate-path');

        $response->assertNotFound();
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_only_super_admin_may_change_the_admin_path(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Security::class)
            ->set('admin_path', 'sneaky-path')
            ->set('admin_path_confirmation', 'sneaky-path')
            ->set('admin_path_current_password', 'irrelevant')
            ->call('saveAdminPath');

        $this->assertSame(config('admin.path'), AdminPath::current());
    }

    public function test_changing_the_path_requires_the_current_password(): void
    {
        $super = $this->superAdmin();

        Livewire::actingAs($super)->test(Security::class)
            ->set('admin_path', 'new-secure-path')
            ->set('admin_path_confirmation', 'new-secure-path')
            ->set('admin_path_current_password', 'totally-wrong-password')
            ->call('saveAdminPath')
            ->assertHasErrors('admin_path_current_password');

        $this->assertSame(config('admin.path'), AdminPath::current());
    }

    public function test_the_new_path_must_be_typed_identically_twice(): void
    {
        $super = $this->superAdmin();

        Livewire::actingAs($super)->test(Security::class)
            ->set('admin_path', 'path-one')
            ->set('admin_path_confirmation', 'path-two')
            ->set('admin_path_current_password', 'current-pass-1')
            ->call('saveAdminPath')
            ->assertHasErrors('admin_path');

        $this->assertSame(config('admin.path'), AdminPath::current());
    }

    public function test_a_path_colliding_with_a_reserved_top_level_route_is_rejected(): void
    {
        $super = $this->superAdmin();

        Livewire::actingAs($super)->test(Security::class)
            ->set('admin_path', 'login')
            ->set('admin_path_confirmation', 'login')
            ->set('admin_path_current_password', 'current-pass-1')
            ->call('saveAdminPath')
            ->assertHasErrors('admin_path');

        $this->assertSame(config('admin.path'), AdminPath::current());
    }

    public function test_every_super_admin_is_notified_and_the_change_is_audited(): void
    {
        MailSettings::save([
            'mailer' => 'smtp',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'from_address' => 'hello@naara.test',
            'from_name' => 'NaaraSim',
        ]);
        Notification::fake();

        $acting = $this->superAdmin();
        $otherSuper = $this->superAdmin('other-pass-1');

        Livewire::actingAs($acting)->test(Security::class)
            ->set('admin_path', 'audited-new-path')
            ->set('admin_path_confirmation', 'audited-new-path')
            ->set('admin_path_current_password', 'current-pass-1')
            ->call('saveAdminPath')
            ->assertHasNoErrors();

        Notification::assertSentTo($acting, AdminPathChangedNotification::class);
        Notification::assertSentTo($otherSuper, AdminPathChangedNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $acting->id,
            'action' => 'admin.path_changed',
        ]);
    }

    public function test_the_acting_admins_own_session_survives_the_path_change(): void
    {
        $super = $this->superAdmin();

        Livewire::actingAs($super)->test(Security::class)
            ->set('admin_path', 'session-survives-path')
            ->set('admin_path_confirmation', 'session-survives-path')
            ->set('admin_path_current_password', 'current-pass-1')
            ->call('saveAdminPath')
            ->assertHasNoErrors();

        // Cookie-based auth isn't tied to the URL path — the same
        // authenticated session reaches the panel at its new address.
        $this->actingAs($super)->get('/session-survives-path')->assertOk();
    }
}
