<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteChromePage;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteChrome;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 28 — two-column auth layout + admin-assignable footer.
 */
class SiteChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SiteChrome::flush();
    }

    public function test_auth_pages_use_the_two_column_layout_with_default_panel_copy(): void
    {
        // Default headline shows in the media panel; the assignable footer's
        // Supreme Ideas attribution appears at the bottom of every auth page.
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('Stay Connected. No Borders. No Swaps.')
                ->assertSee('Supreme Ideas Agency');
        }
    }

    public function test_admin_sets_the_auth_media_panel_and_it_renders(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteChromePage::class)
            ->set('media_type', 'image')
            ->set('media', UploadedFile::fake()->image('panel.jpg', 1200, 1600))
            ->set('headline', 'Your world, connected')
            ->set('subtext', 'Data and numbers everywhere you go.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(SiteChrome::hasAuthMedia());
        $this->assertSame('Your world, connected', SiteChrome::authPanel()['headline']);

        auth()->logout(); // a guest visits the login page
        $this->get('/login')
            ->assertOk()
            ->assertSee('Your world, connected')
            ->assertSee(SiteChrome::authPanel()['media_url'], false);
    }

    public function test_admin_edits_footer_columns_and_legal_links_which_render_on_the_site(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteChromePage::class)
            ->set('columns', [
                ['heading' => 'Explore', 'links' => [
                    ['label' => 'Plans', 'url' => '/catalogue'],
                    ['label' => 'Blog', 'url' => 'https://blog.example.com'],
                ]],
            ])
            ->set('legal', [['label' => 'Privacy', 'url' => '/legal']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Explore', SiteChrome::footerColumns()[0]['heading']);

        // Renders on the public marketing footer.
        $this->get('/')
            ->assertOk()
            ->assertSee('Explore')
            ->assertSee('https://blog.example.com', false)
            ->assertSee('Supreme Ideas Agency');
    }

    public function test_footer_links_reject_non_path_non_url_values(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteChromePage::class)
            ->set('columns', [
                ['heading' => 'Bad', 'links' => [['label' => 'X', 'url' => 'javascript:alert(1)']]],
            ])
            ->call('save')
            ->assertHasErrors(['columns.0.links.0.url']);
    }

    public function test_chrome_page_is_admin_only(): void
    {
        $user = User::factory()->create()->fresh();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/chrome')->assertNotFound();
    }

    public function test_saving_flushes_the_cache(): void
    {
        // Warm the cache with defaults, then a direct Setting write + flush hook
        // must surface the new value.
        $this->assertSame('Stay Connected. No Borders. No Swaps.', SiteChrome::authPanel()['headline']);
        Setting::setValue('site.auth.headline', 'Changed', 'site');
        SiteChrome::flush();
        $this->assertSame('Changed', SiteChrome::authPanel()['headline']);
    }
}
