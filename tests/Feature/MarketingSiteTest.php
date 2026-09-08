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
            ->assertSeeText('Land Anywhere. Connect Instantly.')
            ->assertSee('Connected in Three Steps')
            ->assertSee('Why Travelers Choose NaaraSim')
            ->assertSee('Your Next Trip Starts Here')
            ->assertSee('Supreme Ideas Agency'); // footer attribution
    }

    public function test_about_how_it_works_and_contact_render(): void
    {
        $this->get('/about')->assertOk()->assertSee('We Built the eSIM We Wished Existed');
        $this->get('/how-it-works')->assertOk()->assertSee('From Purchase to Connected in Under 3 Minutes');
        $this->get('/contact')->assertOk()->assertSee('We Actually Respond');
    }

    public function test_an_admin_override_changes_the_public_page(): void
    {
        SiteContent::saveOverrides('home', [
            'hero' => ['headline' => 'Fly Now. Connect Faster.'],
        ]);

        $this->get('/')->assertOk()
            ->assertSeeText('Fly Now. Connect Faster.')
            ->assertDontSeeText('Land Anywhere. Connect Instantly.');
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
            ->assertSee('Pick Your Destination')
            ->assertSee('Select Your Data Plan')
            ->assertSee('Activate on Your Phone')
            // Each stacking card shows its shipped step render.
            ->assertSee('/images/steps/choose-destination.webp', false)
            ->assertSee('/images/steps/select-data-plan.webp', false)
            ->assertSee('/images/steps/activate-on-phone.webp', false);
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

        // The public page now shows the uploaded artwork, not the shipped default.
        $this->get('/')->assertOk()->assertDontSee('/images/steps/choose-destination.webp', false);

        // Reset returns the shipped default.
        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->call('removeFieldImage', 'how', 'step_1_image')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertOk()->assertSee('/images/steps/choose-destination.webp', false);
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
