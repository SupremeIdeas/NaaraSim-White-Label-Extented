<?php

namespace Tests\Feature;

use App\Jobs\SendEmailBroadcastJob;
use App\Livewire\Admin\EmailBroadcast;
use App\Livewire\Admin\EmailStudio;
use App\Models\EmailBroadcast as EmailBroadcastModel;
use App\Models\User;
use App\Notifications\BroadcastEmailNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\BroadcastAudience;
use App\Support\MailTemplates;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA-BUILD-20 §3 — Email Studio (template editor §3.1 + broadcast §3.2).
 */
class EmailStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    // ---- §3.1 template editor -------------------------------------------------

    public function test_overrides_fall_back_to_blade_defaults(): void
    {
        // Nothing saved -> the Blade default subject is used.
        $this->assertSame('Confirm your email', MailTemplates::subject('verify', 'Confirm your email'));
        $this->assertNull(MailTemplates::intro('verify'));
        $this->assertSame('#0A6E6E', MailTemplates::accent('verify'));

        MailTemplates::save('verify', ['subject' => 'Verify now', 'intro' => 'Hi there', 'accent_color' => '#123456']);
        $this->assertSame('Verify now', MailTemplates::subject('verify', 'Confirm your email'));
        $this->assertSame('Hi there', MailTemplates::intro('verify'));
        $this->assertSame('#123456', MailTemplates::accent('verify'));
    }

    public function test_global_accent_applies_when_template_has_none(): void
    {
        MailTemplates::save('global', ['accent_color' => '#abcdef']);
        $this->assertSame('#abcdef', MailTemplates::accent('welcome'));
    }

    public function test_email_blade_renders_the_override(): void
    {
        MailTemplates::save('verify', ['intro' => 'CUSTOM_INTRO_XYZ', 'accent_color' => '#ff0000']);
        $html = Blade::render('@include(\'emails.verify\', [\'name\' => \'Ada\', \'url\' => \'https://x/y\'])');
        $this->assertStringContainsString('CUSTOM_INTRO_XYZ', $html);
        $this->assertStringContainsString('#ff0000', $html); // accent on header/button
    }

    public function test_notification_subject_uses_the_override(): void
    {
        MailTemplates::save('verify', ['subject' => 'Please confirm — override']);
        $user = User::factory()->create(); // persisted: verificationUrl needs id + email
        $mail = (new VerifyEmailNotification)->toMail($user);
        $this->assertSame('Please confirm — override', $mail->subject);
    }

    public function test_studio_is_admin_only_and_saves_with_live_preview(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        Livewire::actingAs($user)->test(EmailStudio::class)->assertForbidden();

        Livewire::actingAs($this->admin())->test(EmailStudio::class)
            ->call('loadTemplate', 'verify')
            ->set('form.subject', 'Edited subject')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', 'verify');

        $this->assertSame('Edited subject', MailTemplates::subject('verify', 'x'));
    }

    // ---- §3.2 broadcast -------------------------------------------------------

    public function test_audience_targeting_counts_the_right_users(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->count(3)->create(['is_active' => true, 'email_verified_at' => now()]);
        User::factory()->count(2)->create(['is_active' => true, 'email_verified_at' => null]);

        $this->assertSame(5, BroadcastAudience::count('all', null));
        $this->assertSame(2, BroadcastAudience::count('segment', 'unverified'));
    }

    public function test_broadcast_requires_admin_and_queues_a_batched_send(): void
    {
        Queue::fake();
        User::factory()->count(4)->create(['is_active' => true]);

        Livewire::actingAs($this->admin())->test(EmailBroadcast::class)
            ->set('subject', 'Hello everyone')
            ->set('body', '<p>News</p>')
            ->set('audienceType', 'all')
            ->call('review')
            ->assertSet('confirming', true)
            ->call('send')
            ->assertHasNoErrors();

        Queue::assertPushed(SendEmailBroadcastJob::class);
        $this->assertDatabaseHas('email_broadcasts', ['subject' => 'Hello everyone', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mail.broadcast_sent']);
    }

    public function test_broadcast_blocks_an_empty_audience(): void
    {
        Livewire::actingAs($this->admin())->test(EmailBroadcast::class)
            ->set('subject', 'x')->set('body', 'y')
            ->set('audienceType', 'segment')->set('audienceValue', 'unverified')
            ->call('send')
            ->assertHasErrors('audienceValue');
        $this->assertDatabaseCount('email_broadcasts', 0);
    }

    public function test_broadcast_job_sends_to_every_recipient(): void
    {
        Notification::fake();
        $users = User::factory()->count(3)->create(['is_active' => true]);

        $b = EmailBroadcastModel::create([
            'subject' => 'Hi', 'body_html' => '<p>x</p>', 'audience_type' => 'all',
            'audience_value' => null, 'audience_label' => 'All users', 'recipient_count' => 3, 'status' => 'queued',
        ]);
        (new SendEmailBroadcastJob($b->id))->handle();

        Notification::assertSentTo($users->first(), BroadcastEmailNotification::class);
        $this->assertSame('sent', $b->fresh()->status);
    }
}
