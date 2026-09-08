<?php

namespace Tests\Feature;

use App\Livewire\Admin\EmailSettings;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\WelcomeNotification;
use App\Support\MailSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 22 — Transactional email system + admin mail config.
 */
class EmailSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        MailSettings::flush();
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');
        $u->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }

    public function test_saved_mail_settings_overlay_config_at_boot(): void
    {
        config(['mail.default' => 'log']);

        MailSettings::save([
            'mailer' => 'smtp',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'from_address' => 'hello@naara.test',
            'from_name' => 'NaaraSim',
        ]);
        MailSettings::applyToConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('hello@naara.test', config('mail.from.address'));
    }

    public function test_smtp_password_is_encrypted_at_rest_and_blank_keeps_it(): void
    {
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N', 'smtp_password' => 'sup3rSecret']);

        $raw = DB::table('settings')->where('key', MailSettings::SETTING_KEY)->value('value');
        $this->assertStringNotContainsString('sup3rSecret', (string) $raw);

        // Blank password on a later save keeps the stored one.
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N', 'smtp_password' => '']);
        MailSettings::applyToConfig();
        $this->assertSame('sup3rSecret', config('mail.mailers.smtp.password'));
    }

    public function test_admin_email_page_is_super_admin_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin)->get('/adminmaster/email')->assertForbidden();
        $this->actingAs($this->superAdmin())->get('/adminmaster/email')->assertOk();
    }

    public function test_admin_can_send_a_test_email(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(EmailSettings::class)
            ->call('sendTest')
            ->assertSet('testError', null);

        Notification::assertSentTo($admin, \App\Notifications\TestMailNotification::class);
    }

    public function test_registration_sends_branded_verify_and_welcome_mail(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@naara.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'ada@naara.test')->firstOrFail();
        Notification::assertSentTo($user, WelcomeNotification::class);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_queued_auth_notifications_serialize_without_error(): void
    {
        // Regression: VerifyEmail/ResetPassword notifications implement ShouldQueue,
        // so they MUST use Queueable (expose $connection/$queue/$delay). With a
        // real queue this reproduces the "Undefined property $connection" 500 the
        // faked-notification tests missed. Sync queue + Mail::fake exercises the
        // actual dispatch path.
        \Illuminate\Support\Facades\Mail::fake();
        config(['queue.default' => 'sync']);

        // Both queued auth notifications must have the queue-config properties.
        $this->assertTrue(property_exists(new VerifyEmailNotification, 'connection'));
        $this->assertTrue(property_exists(new ResetPasswordNotification('tok'), 'connection'));

        // Registration (which fires the verification notification) must not 500.
        $this->post('/register', [
            'name' => 'Grace',
            'email' => 'grace@naara.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'grace@naara.test']);
    }

    public function test_password_reset_uses_the_branded_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_auth_email_pages_render(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Reset your password');
        $this->get('/reset-password/faketoken')->assertOk()->assertSee('Choose a new password');
    }

    public function test_soft_gate_never_blocks_and_hard_mode_still_enforces(): void
    {
        // ->fresh() so is_active (DB default true) is hydrated.
        $user = User::factory()->unverified()->create()->fresh();

        // Mail NOT configured yet -> users are NOT trapped; they can use the app.
        $this->actingAs($user)->get('/dashboard')->assertOk();

        // Mail configured, default mode is now SOFT (NAARA-BUILD-20 §2) — an
        // unverified user is nudged, never blocked: money/core routes stay open.
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N']);
        $this->assertSame('soft', MailSettings::verificationMode());
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/wallet')->assertOk();

        // Switching to HARD restores the mandatory wall (the prior behaviour).
        MailSettings::setVerificationMode('hard');
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/wallet')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/account')->assertOk(); // always reachable

        // OFF never enforces or nudges even with mail configured.
        MailSettings::setVerificationMode('off');
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_soft_gate_banner_shows_only_for_unverified_in_soft_mode(): void
    {
        $user = User::factory()->unverified()->create()->fresh();
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N']);

        // Soft (default) + unverified -> the nudge banner renders.
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Confirm your email', false);

        // Hard mode redirects (no banner needed); a verified user never sees it.
        MailSettings::setVerificationMode('soft');
        $verified = User::factory()->create(['email_verified_at' => now()])->fresh();
        $this->actingAs($verified)->get('/dashboard')->assertOk()
            ->assertDontSee('Confirm your email to secure', false);
    }
}
