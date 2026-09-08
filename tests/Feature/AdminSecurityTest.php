<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Module 14 — Secure Admin Route (blueprint Section 25).
 */
class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(bool $with2fa = true): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        if ($with2fa) {
            $user->forceFill([
                'two_factor_secret' => encrypt('SECRETKEY'),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }

    public function test_the_admin_path_is_env_driven(): void
    {
        // The admin route group is mounted on config('admin.path')…
        $this->assertStringEndsWith('/'.config('admin.path'), route('admin.dashboard'));

        // …and that config value is bound to the ADMIN_PATH environment variable
        // (so each deployment can set its own non-guessable admin path).
        $this->assertStringContainsString(
            "env('ADMIN_PATH'",
            file_get_contents(base_path('config/admin.php'))
        );
    }

    public function test_guest_is_sent_to_login_and_non_admins_stay_hidden(): void
    {
        // Guest at the real path is sent to the dedicated admin login (the
        // owner's admin entry point), then returned to /adminmaster after signing in.
        $this->get('/adminmaster')->assertRedirect(route('admin.login'));

        // A common guess is a genuine 404 (no such route).
        $this->get('/admin')->assertNotFound();

        // Authenticated non-admin still gets a plain 404 — panel stays invisible.
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster')->assertNotFound();
    }

    public function test_admin_2fa_is_opt_in_and_can_be_required(): void
    {
        $admin = $this->admin(with2fa: false);

        // Default: 2FA not required — the admin reaches the panel with just
        // email + password.
        $this->actingAs($admin)->get('/adminmaster')->assertOk();

        // Once a super-admin turns the requirement on, enrolment is forced —
        // BUT only when a super_admin has actually enrolled (anti-lockout rail),
        // otherwise turning the toggle on would trap everyone. Seed an enrolled
        // super_admin so enforcement is genuinely active.
        $enrolledSuper = User::factory()->create();
        $enrolledSuper->assignRole('super_admin');
        $enrolledSuper->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        \App\Models\Setting::setValue('security.admin_2fa_required', true, 'security');
        \App\Support\SecuritySettings::flush();

        $this->actingAs($admin)->get('/adminmaster')->assertRedirect(route('admin.security'));
        $this->actingAs($admin)->get('/adminmaster/security')->assertOk(); // reachable to enrol
    }

    public function test_an_admin_with_confirmed_2fa_reaches_the_panel(): void
    {
        $this->actingAs($this->admin())->get('/adminmaster')->assertOk();
    }

    public function test_ip_allow_list_hides_the_panel_from_other_ips(): void
    {
        config(['admin.ip_allowlist' => ['10.0.0.5']]);

        $admin = $this->admin();

        // Default test IP (127.0.0.1) is not in the list -> 404 even for an admin.
        $this->actingAs($admin)->get('/adminmaster')->assertNotFound();

        // A request from an allowed IP gets in.
        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->get('/adminmaster')
            ->assertOk();
    }

    public function test_admin_can_enable_and_confirm_two_factor(): void
    {
        $admin = $this->admin(with2fa: false);

        $component = Livewire::actingAs($admin)->test(Security::class)
            ->assertSet('showingSetup', false)
            ->call('enable')
            ->assertSet('showingSetup', true);

        // A real TOTP for the freshly generated secret confirms enrolment.
        $secret = decrypt($admin->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('showingSetup', false);

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.2fa_confirmed']);
    }

    public function test_a_wrong_2fa_code_is_rejected(): void
    {
        $admin = $this->admin(with2fa: false);

        Livewire::actingAs($admin)->test(Security::class)
            ->call('enable')
            ->set('code', '000000')
            ->call('confirm')
            ->assertHasErrors('code');

        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }
}
