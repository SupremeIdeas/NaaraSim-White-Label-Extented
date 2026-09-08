<?php

namespace Tests\Feature;

use App\Livewire\Admin\CustomPages;
use App\Models\CustomPage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin custom-HTML pages (CMS): authored in the admin panel, served at
 * /p/{slug}. Admin-only editing; drafts and reserved slugs never resolve.
 */
class CustomPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_admin_can_create_and_publish_a_custom_html_page(): void
    {
        Livewire::actingAs($this->admin())->test(CustomPages::class)
            ->set('title', 'Our Story')
            ->set('html', '<h1>Hello world</h1><p>Custom content.</p>')
            ->set('isPublished', true)
            ->call('save')
            ->assertSet('slug', 'our-story');

        $this->assertDatabaseHas('custom_pages', ['slug' => 'our-story', 'is_published' => true]);

        // Public render includes the raw HTML inside the branded shell.
        $this->get('/p/our-story')->assertOk()
            ->assertSee('Hello world', false)
            ->assertSee('Custom content.', false);
    }

    public function test_a_draft_page_is_not_public(): void
    {
        CustomPage::create(['slug' => 'secret', 'title' => 'Secret', 'html' => '<p>hi</p>', 'is_published' => false]);

        $this->get('/p/secret')->assertNotFound();
    }

    public function test_reserved_slugs_are_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(CustomPages::class)
            ->set('title', 'Login clone')
            ->set('slug', 'login')
            ->call('save')
            ->assertHasErrors('slug');

        $this->assertDatabaseMissing('custom_pages', ['slug' => 'login']);
    }

    public function test_a_published_nav_page_appears_in_the_marketing_menu(): void
    {
        CustomPage::create(['slug' => 'partners', 'title' => 'Partners', 'html' => '<p>x</p>',
            'is_published' => true, 'in_nav' => true]);

        $this->get('/')->assertOk()->assertSee('/p/partners', false)->assertSee('Partners', false);
    }

    public function test_a_non_admin_cannot_open_the_editor(): void
    {
        Livewire::actingAs(User::factory()->create())->test(CustomPages::class)->assertForbidden();
    }

    public function test_editing_updates_the_page(): void
    {
        $page = CustomPage::create(['slug' => 'faq-extra', 'title' => 'FAQ Extra', 'html' => '<p>OLD-BODY-ZZZ</p>', 'is_published' => true]);

        Livewire::actingAs($this->admin())->test(CustomPages::class)
            ->call('edit', $page->id)
            ->set('html', '<p>NEW-BODY-ZZZ</p>')
            ->call('save');

        $this->get('/p/faq-extra')->assertOk()->assertSee('NEW-BODY-ZZZ', false)->assertDontSee('OLD-BODY-ZZZ', false);
    }
}
