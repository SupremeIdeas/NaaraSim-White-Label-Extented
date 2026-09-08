<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteEditor;
use App\Models\User;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reusable sections — an admin can copy a portable section (e.g. the homepage
 * "Who Naara Is For" audience tabs) onto any other marketing page, and it
 * renders there through the shared `marketing.sections.*` partial.
 */
class ReusableSectionsTest extends TestCase
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

    public function test_a_portable_section_can_be_copied_to_another_page(): void
    {
        // audiences is not part of the About page's shipped defaults.
        $this->assertArrayNotHasKey('audiences', SiteContent::page('about', includeHidden: true));

        $fields = SiteContent::portableDefaults('audiences');
        $this->assertTrue(SiteContent::copySection('audiences', $fields, 'about'));

        $about = SiteContent::page('about', includeHidden: true);
        $this->assertArrayHasKey('audiences', $about);
        $this->assertTrue($about['audiences']['visible']);
        $this->assertTrue($about['audiences']['_copied']);
    }

    public function test_a_copied_section_renders_on_the_target_public_page(): void
    {
        SiteContent::copySection('audiences', SiteContent::portableDefaults('audiences'), 'about');

        $this->get('/about')->assertOk()
            ->assertSee('Who Naara Is For', false)
            ->assertSee('Travel Smarter. Stay Connected Everywhere.', false);
    }

    public function test_a_non_portable_section_cannot_be_copied(): void
    {
        $this->assertFalse(SiteContent::copySection('hero', ['headline' => 'x'], 'about'));
        $this->assertArrayNotHasKey('hero', SiteContent::overrides('about'));
    }

    public function test_admin_can_copy_and_then_remove_a_reused_section(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('page', 'home')
            ->call('copyTo', 'audiences', 'about');

        $this->assertArrayHasKey('audiences', SiteContent::page('about', includeHidden: true));

        // Now remove it from the About page.
        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('page', 'about')
            ->call('removeSection', 'audiences');

        $this->assertArrayNotHasKey('audiences', SiteContent::page('about', includeHidden: true));
    }

    public function test_copy_is_admin_gated(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(SiteEditor::class)
            ->call('copyTo', 'audiences', 'about')
            ->assertStatus(403);
    }
}
