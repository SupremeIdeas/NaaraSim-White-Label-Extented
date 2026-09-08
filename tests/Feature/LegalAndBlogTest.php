<?php

namespace Tests\Feature;

use App\Livewire\Admin\LegalEditor;
use App\Livewire\Admin\Posts as AdminPosts;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\LegalContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Module 30 — legal CMS + blog. */
class LegalAndBlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        LegalContent::flush();
    }

    // ---- legal --------------------------------------------------------------

    public function test_all_five_legal_docs_render_at_stable_public_urls(): void
    {
        $this->get('/legal')->assertOk()->assertSee('Legal &amp; Policies', false);

        foreach (['privacy', 'terms', 'refund', 'cookies', 'data-deletion'] as $slug) {
            $this->get("/legal/{$slug}")->assertOk()->assertSee(LegalContent::doc($slug)['title']);
        }

        // The data-deletion URL Facebook/Google reviews expect exists and is real.
        $this->get('/legal/data-deletion')->assertOk()->assertSee('delete your');
        // Legacy refund URL still works.
        $this->get('/refund-policy')->assertOk()->assertSee('Refund Policy');
        // Unknown doc 404s.
        $this->get('/legal/nonsense')->assertNotFound();
    }

    public function test_admin_edits_a_legal_doc_and_can_reset_it(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(LegalEditor::class)
            ->set('slug', 'privacy')
            ->set('title', 'Our Privacy Promise')
            ->set('body', "## Intro\n\nWe keep it simple.")
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/legal/privacy')->assertOk()->assertSee('Our Privacy Promise')->assertSee('We keep it simple.');

        // Reset returns the shipped default.
        Livewire::actingAs($admin)->test(LegalEditor::class)
            ->set('slug', 'privacy')
            ->call('resetToDefault');
        $this->assertDatabaseMissing('settings', ['key' => 'legal.privacy']);
        $this->get('/legal/privacy')->assertOk()->assertSee('Privacy Policy');
    }

    public function test_legal_body_never_renders_admin_html(): void
    {
        Setting::setValue('legal.terms', ['title' => 'Terms', 'body' => '<script>alert(1)</script>', 'updated' => null], 'legal');
        LegalContent::flush();

        $this->get('/legal/terms')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)   // not raw
            ->assertSee('&lt;script&gt;', false);                 // escaped
    }

    public function test_legal_editor_is_admin_only(): void
    {
        $user = User::factory()->create()->fresh();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/legal')->assertNotFound();
    }

    // ---- blog ---------------------------------------------------------------

    public function test_only_published_posts_are_public(): void
    {
        $author = User::factory()->create();
        $live = Post::create([
            'title' => 'Travelling with an eSIM', 'slug' => 'travelling-with-an-esim',
            'category' => 'Guides', 'excerpt' => 'How it works.', 'body' => 'Body copy here.',
            'status' => 'published', 'published_at' => now()->subDay(), 'author_id' => $author->id,
        ]);
        $draft = Post::create([
            'title' => 'Secret draft', 'slug' => 'secret-draft',
            'body' => 'Not ready.', 'status' => 'draft',
        ]);
        $future = Post::create([
            'title' => 'Scheduled', 'slug' => 'scheduled', 'body' => 'Later.',
            'status' => 'published', 'published_at' => now()->addWeek(),
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Travelling with an eSIM')
            ->assertDontSee('Secret draft')
            ->assertDontSee('Scheduled');

        $this->get('/blog/travelling-with-an-esim')->assertOk()->assertSee('Body copy here.');
        $this->get('/blog/secret-draft')->assertNotFound();     // draft hidden
        $this->get('/blog/scheduled')->assertNotFound();        // future hidden
    }

    public function test_blog_filters_by_category(): void
    {
        Post::create(['title' => 'A', 'slug' => 'a', 'category' => 'Guides', 'body' => 'x', 'status' => 'published', 'published_at' => now()->subDay()]);
        Post::create(['title' => 'B', 'slug' => 'b', 'category' => 'News', 'body' => 'y', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/blog?category=Guides')->assertOk()->assertSee('>A<', false)->assertDontSee('>B<', false);
    }

    public function test_admin_creates_a_post_with_a_cover_and_it_goes_live(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(AdminPosts::class)
            ->call('newPost')
            ->set('title', 'Best eSIM tips for 2026')
            ->set('category', 'Guides')
            ->set('excerpt', 'Our top tips.')
            ->set('body', "## Tip one\n\nInstall before you fly.")
            ->set('cover', UploadedFile::fake()->image('cover.jpg', 1600, 900))
            ->set('status', 'published')
            ->call('save')
            ->assertHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertSame('best-esim-tips-for-2026', $post->slug);
        $this->assertNotNull($post->published_at);
        $this->assertNotNull($post->cover_image_url);

        auth()->logout();
        $this->get('/blog/best-esim-tips-for-2026')->assertOk()->assertSee('Install before you fly.');
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        foreach (['First', 'First'] as $t) {
            Livewire::actingAs($admin)->test(AdminPosts::class)
                ->call('newPost')->set('title', $t)->set('body', 'b')->set('status', 'draft')->call('save')->assertHasNoErrors();
        }

        $this->assertSame(['first', 'first-2'], Post::orderBy('id')->pluck('slug')->all());
    }

    public function test_blog_manager_is_admin_only(): void
    {
        $user = User::factory()->create()->fresh();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/blog')->assertNotFound();
    }
}
