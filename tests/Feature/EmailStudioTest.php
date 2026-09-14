<?php

namespace Tests\Feature;

use App\Jobs\SendEmailBroadcastJob;
use App\Livewire\Admin\EmailBroadcast;
use App\Livewire\Admin\EmailStudio;
use App\Models\EmailBroadcast as EmailBroadcastModel;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BroadcastEmailNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\BroadcastAudience;
use App\Support\MailTemplates;
use App\Support\SiteChrome;
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

    /**
     * Sept-14 owner request: the "heading" field existed on the form/model and
     * was even validated + saved, but had no input in the Email Studio blade
     * (an admin could never actually reach it), AND the layout component never
     * routed a saved override into anything real — so even a value written
     * straight to the database had zero visible effect. Both are fixed now:
     * an admin can save one, and it reaches the rendered email's <title>.
     */
    public function test_heading_override_reaches_the_rendered_email(): void
    {
        MailTemplates::save('welcome', ['heading' => 'CUSTOM_HEADING_XYZ']);
        $html = Blade::render('@include(\'emails.welcome\', [\'name\' => \'Ada\', \'url\' => \'https://x/y\'])');
        $this->assertStringContainsString('<title>CUSTOM_HEADING_XYZ</title>', $html);
    }

    public function test_the_heading_input_is_present_in_the_studio_form(): void
    {
        Livewire::actingAs($this->admin())->test(EmailStudio::class)
            ->call('loadTemplate', 'welcome')
            ->assertSeeHtml('wire:model.live.debounce.400ms="form.heading"');
    }

    /**
     * Sept-14 owner request: 4 of the 6 editable templates (welcome,
     * order-placed, top-up, refund) had preview sample data missing fields
     * the real view expects (or, for top-up, the wrong key — 'balance'
     * instead of 'newBalance') — previewHtml() caught the resulting error and
     * silently showed a broken "Preview error" box in the admin UI instead of
     * a real preview. Every editable template must now preview cleanly.
     */
    public function test_every_editable_template_previews_without_error(): void
    {
        $component = Livewire::actingAs($this->admin())->test(EmailStudio::class);

        foreach (array_keys(MailTemplates::templates()) as $key) {
            $component->call('loadTemplate', $key);
            $html = $component->instance()->previewHtml();
            $this->assertStringNotContainsString('Preview error', $html, "template '{$key}' failed to preview: {$html}");
        }
    }

    /**
     * Sept-14 owner request: white-label buyers must be able to change the
     * hardcoded "Supreme Ideas Agency" wording on the email template. Reuses
     * the SAME phrasing mechanism already governing the web footer
     * (SiteChrome::footerCreditParts()) rather than a second, email-only
     * config surface — one admin setting now reaches both surfaces.
     */
    public function test_the_agency_credit_on_the_email_reflects_site_chrome_phrasing(): void
    {
        SiteChrome::flush();
        config()->set('updater.product_identifier', 'naarasim-core');
        $html = Blade::render('@include(\'emails.welcome\', [\'name\' => \'Ada\', \'url\' => \'https://x/y\'])');
        $this->assertStringContainsString('A product of', $html);
        $this->assertStringContainsString(SiteChrome::AGENCY_NAME, $html);

        Setting::setValue('site.footer.credit_phrasing', SiteChrome::CREDIT_MADE_WITH_LOVE, 'site');
        SiteChrome::flush();
        $html = Blade::render('@include(\'emails.welcome\', [\'name\' => \'Ada\', \'url\' => \'https://x/y\'])');
        $this->assertStringContainsString('Made with love by', $html);
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
