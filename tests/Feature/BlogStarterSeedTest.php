<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\BlogPostSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * White-label starter blog (2026-10-02): five evergreen, brand-neutral guides with
 * their images — deliberately NOT the master's 40-post series.
 */
class BlogStarterSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        User::factory()->create()->assignRole('super_admin');
    }

    public function test_it_seeds_exactly_five_published_posts(): void
    {
        $this->seed(BlogPostSeeder::class);

        $this->assertSame(5, Post::count());
        $this->assertSame(5, Post::published()->count());
        $this->assertNotNull(Post::first()->author_id);
    }

    public function test_every_post_has_a_cover_that_exists_and_the_numbers_post_has_its_inline_image(): void
    {
        $this->seed(BlogPostSeeder::class);

        Post::all()->each(function (Post $post) {
            $this->assertFileExists(public_path('images/blog/'.$post->slug.'.webp'));
            $this->assertStringEndsWith('/images/blog/'.$post->slug.'.webp', $post->cover_image_url);
        });

        $numbers = Post::where('slug', 'sms-verification-numbers-explained-otp-vs-rental')->first();
        $this->assertFileExists(public_path('images/blog/'.$numbers->slug.'-2.webp'));
        $this->assertSame(1, substr_count($numbers->body, '/images/blog/'.$numbers->slug.'-2.webp'));

        $this->seed(BlogPostSeeder::class); // re-seed never stacks a second copy
        $this->assertSame(5, Post::count());
        $this->assertSame(1, substr_count(Post::where('slug', $numbers->slug)->value('body'), '-2.webp'));
    }

    public function test_the_brand_token_resolves_to_the_install_brand_and_no_master_or_supplier_names_leak(): void
    {
        Setting::setValue('brand.name', 'Acme Connect');
        Cache::forget('brand.settings');

        $this->seed(BlogPostSeeder::class);

        $all = Post::all()->map(fn ($p) => $p->title.' '.$p->excerpt.' '.$p->body.' '.$p->meta_description)->implode(' ');
        $this->assertStringNotContainsString('{brand}', $all);
        $this->assertStringContainsString('Acme Connect', $all);
        foreach (['NaaraSim', 'Airalo', 'eSIM Go', 'Holafly', 'Getatext', '5sim', 'Twilio'] as $leak) {
            $this->assertStringNotContainsString($leak, $all, "{$leak} must not appear in a white-label starter post");
        }
    }

    public function test_a_post_page_renders_with_its_cover(): void
    {
        $this->seed(BlogPostSeeder::class);

        $html = $this->get('/blog/how-to-install-esim-step-by-step-guide')->assertOk()->getContent();

        $this->assertStringContainsString('/images/blog/how-to-install-esim-step-by-step-guide.webp', $html);
    }
}
