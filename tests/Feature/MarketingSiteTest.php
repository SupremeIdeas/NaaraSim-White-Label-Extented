<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteEditor;
use App\Livewire\ContactForm;
use App\Models\SupportConversation;
use App\Models\User;
use App\Notifications\ContactMessageNotification;
use App\Support\MailSettings;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 27 — public marketing site (CMS-editable landing pages).
 */
class MarketingSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SiteContent::flush();
    }

    public function test_the_landing_page_renders_the_brand_copy(): void
    {
        $this->get('/')->assertOk()
            // The hero headline's last word is wrapped in a gradient <span>, so
            // assert the visible text (assertSeeText strips the inline markup).
            ->assertSeeText('Touch down. Already online.')
            ->assertSee('From tap to signal')
            ->assertSee('What makes '.\App\Support\BrandSettings::name().' different')
            ->assertSee('Your next trip starts online')
            ->assertSee(\App\Support\BrandSettings::company()); // footer attribution
    }

    public function test_about_how_it_works_and_contact_render(): void
    {
        $this->get('/about')->assertOk()->assertSee('We make getting online the easy part');
        $this->get('/how-it-works')->assertOk()->assertSee('From checkout to connected in minutes');
        $this->get('/contact')->assertOk()->assertSee('Talk to a person');
    }

    /**
     * FAQ wiring: the dedicated /faq page is a real SiteContent page (like
     * about/how-it-works/contact) with its own admin-editable hero, and its
     * Q&A content is pulled LIVE from the homepage's own 'faq' section — a
     * single source of truth, so the two never show different answers.
     */
    /**
     * The dedicated /faq page itself is master-only (owner decision,
     * 2026-09-21 — see the fork-specific tests below); this fork's default
     * identity is 'naarasim-whitelabel', so the underlying page-rendering
     * logic (still present and shared code) is exercised here under a
     * temporary master identity, same as master's own test.
     */
    public function test_the_faq_page_renders_its_own_hero_and_the_homes_faq_content(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');

        $this->get('/faq')->assertOk()
            ->assertSee('Questions, answered')
            ->assertSee('Does my phone support eSIM?') // home.faq's q1, proves it's the same source
            ->assertSee('Quick answers');     // home.faq's own headline field

        config()->set('updater.product_identifier', 'naarasim-whitelabel');
    }

    public function test_editing_the_homes_faq_from_the_admin_also_changes_the_dedicated_faq_page(): void
    {
        SiteContent::saveOverrides('home', [
            'faq' => ['q1' => 'A brand new question only an admin could have written?'],
        ]);

        $this->get('/')->assertOk()->assertSee('A brand new question only an admin could have written?');

        config()->set('updater.product_identifier', 'naarasim-core');
        $this->get('/faq')->assertOk()->assertSee('A brand new question only an admin could have written?');
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
    }

    public function test_the_faq_page_is_a_registered_site_editor_page_with_its_own_hero_image(): void
    {
        $this->assertContains('faq', SiteContent::PAGES);

        SiteContent::saveOverrides('faq', [
            'hero' => ['image' => 'https://cdn.example.com/faq-hero.jpg'],
        ]);

        config()->set('updater.product_identifier', 'naarasim-core');
        $this->get('/faq')->assertOk()
            ->assertSee('https://cdn.example.com/faq-hero.jpg', false)
            ->assertDontSee('data-webgl-hero="liquid"', false);
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
    }

    /**
     * FAQ is master-only (owner decision, 2026-09-21) — a white-label fork
     * never gets the dedicated page: the route 404s, the nav link
     * disappears, and the admin's Pages editor drops its tab too.
     */
    public function test_the_faq_page_404s_on_a_white_label_fork(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->get('/faq')->assertNotFound();

        config()->set('updater.product_identifier', 'naarasim-core');
    }

    public function test_the_faq_nav_link_is_hidden_on_a_white_label_fork(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->get('/')->assertOk()->assertDontSee('Answers to common questions');

        config()->set('updater.product_identifier', 'naarasim-core');
    }

    public function test_the_faq_page_is_dropped_from_the_site_editors_page_list_on_a_white_label_fork(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->assertNotContains('faq', SiteContent::editablePages());
        $this->assertContains('faq', SiteContent::PAGES); // the raw catalogue is unchanged

        config()->set('updater.product_identifier', 'naarasim-core');
    }

    public function test_an_admin_override_changes_the_public_page(): void
    {
        SiteContent::saveOverrides('home', [
            'hero' => ['headline' => 'Fly Now. Connect Faster.'],
        ]);

        $this->get('/')->assertOk()
            ->assertSeeText('Fly Now. Connect Faster.')
            ->assertDontSeeText('Touch down. Already online.');
    }

    public function test_a_hidden_section_disappears_from_the_public_page(): void
    {
        SiteContent::saveOverrides('home', [
            'testimonials' => ['visible' => false],
        ]);

        $this->get('/')->assertOk()->assertDontSee('What Our Customers Say');
    }

    public function test_the_site_editor_is_admin_only_and_saves_overrides(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/site')->assertNotFound();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('sections.hero.headline', 'Custom Headline From The Editor')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertSeeText('Custom Headline From The Editor');
    }

    public function test_the_home_hero_renders_the_webgl_planet_with_a_fallback(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-webgl-hero="planet"', false) // the lazy hero scene canvas
            ->assertSee('blur-3xl', false);                // the glow-orb fallback beneath it
    }

    public function test_the_about_hero_renders_the_liquid_webgl_scene(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('data-webgl-hero="liquid"', false)
            ->assertSee('blur-3xl', false);
    }

    public function test_an_admin_hero_image_replaces_the_webgl_backdrop(): void
    {
        SiteContent::saveOverrides('home', [
            'hero' => ['image' => 'https://cdn.example.com/hero.jpg'],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('https://cdn.example.com/hero.jpg', false)
            ->assertDontSee('data-webgl-hero="planet"', false);
    }

    public function test_the_marketing_footer_renders_the_water_webgl_scene(): void
    {
        $this->get('/')->assertOk()->assertSee('data-webgl-hero="water"', false);
    }

    public function test_the_how_it_works_hero_renders_the_city_webgl_scene(): void
    {
        $this->get('/how-it-works')->assertOk()->assertSee('data-webgl-hero="city"', false);
    }

    public function test_the_three_step_showcase_renders_its_artwork(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Choose where you are going')
            ->assertSee('Pay once, get a QR code')
            ->assertSee('Scan and fly')
            // Each stacking card shows its coded phone mockup until a picture is uploaded.
            ->assertSee('nx-phone', false)
            ->assertDontSee('/images/steps/', false);
    }

    public function test_admin_can_swap_a_step_image_and_reset_it(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = \Illuminate\Http\UploadedFile::fake()->image('new-step.jpg', 800, 600);

        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('imageUpload', $file)
            ->call('uploadFieldImage', 'how', 'step_1_image')
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        // The public page now shows the uploaded picture in the first card (two coded phones remain for the others).
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('nx-journey__visual', $html);
        $this->assertSame(2, substr_count($html, 'nx-phone__notch'));

        // Reset returns the coded phone.
        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->call('removeFieldImage', 'how', 'step_1_image')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(3, substr_count($this->get('/')->assertOk()->getContent(), 'nx-phone__notch'));
    }

    public function test_a_non_image_field_cannot_be_swapped_as_artwork(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('imageUpload', \Illuminate\Http\UploadedFile::fake()->image('x.jpg'))
            ->call('uploadFieldImage', 'how', 'headline')
            ->assertForbidden();
    }

    public function test_guest_contact_message_is_emailed_when_mail_is_configured(): void
    {
        Notification::fake();
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N']);

        Livewire::test(ContactForm::class)
            ->set('name', 'Ngozi')
            ->set('email', 'ngozi@example.com')
            ->set('subject', 'Before I buy')
            ->set('message', 'Does this work for a two-week trip to Kenya?')
            ->call('send')
            ->assertSet('sent', true);

        Notification::assertSentOnDemand(ContactMessageNotification::class);
    }

    public function test_signed_in_contact_message_becomes_an_escalated_ticket(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ContactForm::class)
            ->set('subject', 'Billing question')
            ->set('message', 'I was charged twice for my USA plan, please help.')
            ->call('send')
            ->assertSet('sent', true);

        $ticket = SupportConversation::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($ticket->escalated);
        $this->assertSame('Billing question', $ticket->escalation_reason);
        $this->assertDatabaseHas('support_messages', ['conversation_id' => $ticket->id, 'role' => 'user']);
    }

    public function test_the_honeypot_silently_drops_bots(): void
    {
        Notification::fake();
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N']);

        Livewire::test(ContactForm::class)
            ->set('website', 'http://spam.example')
            ->set('message', 'buy my thing')
            ->call('send')
            ->assertSet('sent', true);

        Notification::assertNothingSent();
    }
}
