<?php

namespace Tests\Feature;

use App\Livewire\Admin\Posts as AdminPosts;
use App\Livewire\Blog;
use App\Models\Post;
use App\Models\User;
use App\Support\BlogSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Blog overhaul: the public Livewire blog (hero + infinite feed), the per-post
 * accent colour + graceful fallback, and the admin hero/accent controls.
 */
class BlogOverhaulTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    private function posts(int $n, string $category = 'Guides'): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Post::create([
                'title' => "Post {$category} {$i}", 'slug' => strtolower($category)."-{$i}",
                'category' => $category, 'body' => 'Body', 'status' => 'published',
                'published_at' => now()->subDays($i),
            ]);
        }
    }

    public function test_accent_colour_uses_the_set_value_then_a_category_fallback(): void
    {
        $set = new Post(['category' => 'News', 'accent_color' => '#123456']);
        $this->assertSame('#123456', $set->accentColor());

        $a = new Post(['category' => 'Guides']);
        $b = new Post(['category' => 'Guides']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $a->accentColor());
        $this->assertSame($a->accentColor(), $b->accentColor()); // deterministic per category

        $this->assertSame('#0A6E6E', (new Post(['category' => '']))->accentColor()); // base fallback
    }

    public function test_public_blog_renders_and_lists_published_posts(): void
    {
        $this->posts(3);
        Livewire::test(Blog::class)
            ->assertOk()
            ->assertSee('Post Guides 1');
    }

    public function test_infinite_scroll_loads_more(): void
    {
        $this->posts(15);

        Livewire::test(Blog::class)
            ->assertViewHas('hasMore', true)
            ->assertViewHas('posts', fn ($posts) => $posts->count() === 9)
            ->call('loadMore')
            ->assertViewHas('posts', fn ($posts) => $posts->count() === 15)
            ->assertViewHas('hasMore', false);
    }

    public function test_switching_category_resets_the_page_size(): void
    {
        $this->posts(15);

        Livewire::test(Blog::class)
            ->call('loadMore')->assertSet('perPage', 15)
            ->set('category', 'Guides')->assertSet('perPage', 9);
    }

    public function test_admin_can_save_the_blog_hero(): void
    {
        Livewire::actingAs($this->admin())->test(AdminPosts::class)
            ->set('heroTitle', 'Read the latest')
            ->set('heroSubtitle', 'Fresh guides')
            ->call('saveHero')->assertHasNoErrors();

        $this->assertSame('Read the latest', BlogSettings::get('title'));
    }

    public function test_admin_can_set_a_post_accent_colour(): void
    {
        Livewire::actingAs($this->admin())->test(AdminPosts::class)
            ->call('newPost')
            ->set('title', 'Coloured')->set('body', 'Body')
            ->set('category', 'News')->set('accent_color', '#E8412A')
            ->set('status', 'published')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('#E8412A', Post::where('title', 'Coloured')->first()->accent_color);
    }
}
