<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Found by crawling every page: these two answered 500. */
class AuthSecondaryRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_confirm_password_page_renders_and_confirms(): void
    {
        $u = User::factory()->create(['password' => bcrypt('Secret#123')])->fresh();
        $this->actingAs($u)->get('/user/confirm-password')->assertOk()->assertSee('Confirm your password');
        $this->actingAs($u)->post('/user/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($u)->post('/user/confirm-password', ['password' => 'Secret#123'])->assertRedirect();
    }

    public function test_the_passkey_endpoints_answer_a_signed_in_member_instead_of_a_server_error(): void
    {
        $u = User::factory()->create()->fresh();
        $this->assertInstanceOf(\Laravel\Fortify\Contracts\PasskeyUser::class, $u);
        $this->actingAs($u)->withSession(['auth.password_confirmed_at' => time()])->getJson('/user/passkeys/options')->assertSuccessful();
        $this->actingAs($u)->getJson('/passkeys/confirm/options')->assertStatus(200);
    }
}
