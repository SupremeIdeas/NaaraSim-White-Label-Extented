<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fingerprint / face / screen-lock sign-in (WebAuthn passkeys) is offered on the login page and managed in Account > Security. */
class PasskeyUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_the_login_page_offers_the_fingerprint_button_with_a_password_fallback(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-passkey-login', false)->assertSee('Sign in with fingerprint or face')->assertSee('name="password"', false);
    }

    public function test_the_security_page_lists_and_adds_devices_and_passkey_routes_exist(): void
    {
        $u = User::factory()->create()->fresh();
        $this->actingAs($u)->get(route('security'))->assertOk()->assertSee('data-passkeys', false)->assertSee('Fingerprint, face or screen lock');
        foreach (['passkey.login-options', 'passkey.login', 'passkey.registration-options', 'passkey.store', 'passkey.destroy'] as $r) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($r), $r);
        }
    }

    public function test_a_locked_session_sees_only_the_lock_screen_until_the_password_unlocks_it(): void
    {
        $u = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('Secret123!')])->fresh();
        $u->forceFill(['app_lock_minutes' => 5])->save();

        $this->actingAs($u)->post('/app-lock/lock')->assertOk();
        $this->actingAs($u)->get('/account/security')->assertStatus(423)->assertSee('Welcome back')->assertSee('data-app-lock', false);
        $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'wrong'])->assertStatus(422);
        $this->actingAs($u)->get('/account/security')->assertStatus(423);
        $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'Secret123!'])->assertOk();
        $this->actingAs($u)->get('/account/security')->assertOk();
    }

    public function test_the_lock_is_off_by_default_and_an_idle_session_locks_server_side(): void
    {
        $u = User::factory()->create()->fresh();
        $this->actingAs($u)->get('/account/security')->assertOk();
        $this->assertFalse(\App\Support\AppLock::enabled($u));

        $u->forceFill(['app_lock_minutes' => 1])->save();
        $this->actingAs($u)->withSession(['app_last_active' => time() - 3600])->get('/account/security')->assertStatus(423);
    }

    public function test_the_lock_needs_an_app_password_first_and_that_password_not_the_account_one_opens_it(): void
    {
        $u = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('Account123!')])->fresh();

        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Account\AppLockSettings::class)
            ->set('current', 'Account123!')->call('turnOn')->assertSet('error', 'Set your app password first, then turn the lock on.');
        $this->assertFalse(\App\Support\AppLock::enabled($u->fresh()));

        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Account\AppLockSettings::class)
            ->set('current', 'wrong')->set('newPassword', 'AppPass1')->set('confirmPassword', 'AppPass1')->call('savePassword')->assertSet('error', 'Your account password is not right.')
            ->set('current', 'Account123!')->call('savePassword')->set('minutes', '15')->call('turnOn')->assertSet('flash', 'App lock is on.');
        $u = $u->fresh();
        $this->assertSame(15, $u->app_lock_minutes);

        $this->actingAs($u)->post('/app-lock/lock')->assertOk();
        $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'Account123!'])->assertStatus(422);
        $this->actingAs($u)->postJson('/app-lock/password', ['password' => 'AppPass1'])->assertOk();
        $this->actingAs($u)->get('/account/app-lock')->assertOk()->assertSee('App lock');
        $this->actingAs($u)->get('/account')->assertOk()->assertSee('data-app-lock-link', false);
    }
}
