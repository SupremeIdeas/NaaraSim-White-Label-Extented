<?php

namespace Tests\Feature;

use App\Livewire\SecurityCenter;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\TwoFactorNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 23 — Customer Security Center.
 */
class SecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_security_page_loads_for_a_signed_in_user(): void
    {
        // ->fresh() hydrates is_active (DB default true) so the "paused account"
        // guard doesn't trip on the in-memory model.
        $user = User::factory()->create()->fresh();
        $this->actingAs($user)->get('/account/security')->assertOk()->assertSee('Two-factor');
    }

    public function test_user_can_change_their_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('OldPass123!')]);

        Livewire::actingAs($user)->test(SecurityCenter::class)
            ->set('current_password', 'OldPass123!')
            ->set('password', 'BrandNew456!')
            ->set('password_confirmation', 'BrandNew456!')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('BrandNew456!', $user->fresh()->password));
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_changing_email_requires_password_and_triggers_reverification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('OldPass123!'), 'email' => 'old@naara.test']);

        // Wrong password -> rejected.
        Livewire::actingAs($user)->test(SecurityCenter::class)
            ->set('new_email', 'fresh@naara.test')
            ->set('email_password', 'wrong')
            ->call('updateEmail')
            ->assertHasErrors('email_password');

        $this->assertSame('old@naara.test', $user->fresh()->email);

        // Correct password -> email changes, verification reset + resent.
        Livewire::actingAs($user)->test(SecurityCenter::class)
            ->set('new_email', 'fresh@naara.test')
            ->set('email_password', 'OldPass123!')
            ->call('updateEmail')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('fresh@naara.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        Notification::assertSentTo($fresh, VerifyEmailNotification::class);
    }

    public function test_user_can_enable_and_confirm_two_factor(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(SecurityCenter::class)
            ->call('enable2fa')
            ->assertSet('showing2faSetup', true);

        $secret = decrypt($user->fresh()->two_factor_secret);
        $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)->call('confirm2fa')->assertHasNoErrors();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        Notification::assertSentTo($user, TwoFactorNotification::class, fn ($n) => $n->enabled === true);
    }

    public function test_user_can_disable_two_factor(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(SecurityCenter::class)->call('enable2fa');
        $secret = decrypt($user->fresh()->two_factor_secret);
        $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);
        $component->set('code', $code)->call('confirm2fa');

        Livewire::actingAs($user)->test(SecurityCenter::class)->call('disable2fa');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        Notification::assertSentTo($user, TwoFactorNotification::class, fn ($n) => $n->enabled === false);
    }

    public function test_user_can_unlink_google(): void
    {
        $user = User::factory()->create(['google_id' => 'G-9']);

        Livewire::actingAs($user)->test(SecurityCenter::class)
            ->call('unlinkGoogle');

        $this->assertNull($user->fresh()->google_id);
    }
}
