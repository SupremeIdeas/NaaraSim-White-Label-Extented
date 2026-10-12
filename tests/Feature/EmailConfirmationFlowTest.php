<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerifiedNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailConfirmationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $verified = false): User
    {
        return User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    }

    private function link(User $u, ?\DateTimeInterface $expires = null, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('email.confirm', $expires ?? now()->addHour(), ['id' => $u->id, 'hash' => $hash ?? sha1($u->email)]);
    }

    public function test_same_browser_signed_in_goes_to_the_dashboard_with_a_toast(): void
    {
        Notification::fake();
        $u = $this->user();
        $this->actingAs($u)->get($this->link($u))->assertRedirect(route('dashboard'))->assertSessionHas('flash_toast.title', 'Email confirmed');
        $this->assertTrue($u->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($u, EmailVerifiedNotification::class);
    }

    public function test_another_device_gets_a_public_page_and_the_toast_waits_for_next_sign_in(): void
    {
        Notification::fake();
        $u = $this->user();
        $this->get($this->link($u))->assertOk()->assertSee('Email confirmed')->assertSee('Continue to sign in');
        $this->assertTrue($u->fresh()->hasVerifiedEmail());
        $key = \App\Http\Controllers\EmailConfirmationController::PENDING_PREFIX.$u->id;
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($key));
        event(new \Illuminate\Auth\Events\Login('web', $u, false));
        $this->assertSame('Email confirmed', session('flash_toast.title'));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has($key), 'the pending flag is consumed once');
    }

    public function test_already_confirmed_is_friendly_not_an_error(): void
    {
        $u = $this->user(true);
        $this->actingAs($u)->get($this->link($u))->assertRedirect(route('dashboard'))->assertSessionHas('flash_toast.title', 'Already confirmed');
        auth()->logout();
        $this->get($this->link($u))->assertOk()->assertSee('Already confirmed');
    }

    public function test_expired_link_explains_and_offers_a_resend_when_signed_in(): void
    {
        $u = $this->user();
        $old = $this->link($u, now()->subMinute());
        $this->get($old)->assertOk()->assertSee('This link has expired')->assertSee('Sign in to get a new link');
        $this->actingAs($u)->get($old)->assertOk()->assertSee('Send me a new link');
        $this->assertFalse($u->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_for_the_wrong_hash_confirms_nothing(): void
    {
        $u = $this->user();
        $this->get($this->link($u, null, sha1('someone-else@example.com')))->assertNotFound();
        $this->assertFalse($u->fresh()->hasVerifiedEmail());
    }

    public function test_the_mail_now_carries_the_public_confirmation_link(): void
    {
        $u = $this->user();
        $n = new VerifyEmailNotification;
        $url = (new \ReflectionMethod($n, 'verificationUrl'))->invoke($n, $u);
        $this->assertStringContainsString('/email/confirm/'.$u->id.'/'.sha1($u->email), $url);
        $this->assertStringContainsString('signature=', $url);
    }
}
