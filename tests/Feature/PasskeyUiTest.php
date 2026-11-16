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
}
