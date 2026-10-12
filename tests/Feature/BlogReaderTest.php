<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Support\Blog\BlogReaderSettings;
use App\Support\Blog\RelatedPosts;
use App\Support\Blog\ShareLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlogReaderTest extends TestCase
{
    use RefreshDatabase;

    private function mk(string $title, string $cat = 'Guides', int $ago = 1): Post
    {
        return Post::create(['title' => $title, 'slug' => Post::uniqueSlug($title), 'category' => $cat, 'excerpt' => 'Short '.$title, 'body' => str_repeat('word ', 400),
            'status' => 'published', 'published_at' => now()->subDays($ago)]);
    }

    public function test_share_links_encode_once_carry_the_canonical_url_and_utm(): void
    {
        $links = ShareLinks::for('https://x.test/blog/a-b', 'Hello & "world"', 'Read this', 'https://x.test/c.jpg', ['whatsapp', 'email', 'nope', 'x']);
        $this->assertSame(['whatsapp', 'email', 'x'], array_column($links, 'key'));
        foreach ($links as $l) {
            $this->assertStringContainsString(rawurlencode('https://x.test/blog/a-b?'), $l['href']);
            $this->assertStringContainsString(rawurlencode('utm_medium='.$l['key']), $l['href']);
            $this->assertStringContainsString('utm_source%3Dshare', $l['href']);
        }
        $this->assertStringStartsWith('mailto:?subject=Hello%20%26%20%22world%22', $links[1]['href']);
        $this->assertFalse($links[1]['popup']);
    }

    public function test_every_configured_channel_builds_a_well_formed_url(): void
    {
        $all = array_keys(config('blog_share.channels'));
        foreach (ShareLinks::for('https://x.test/p', 'T', 'T', 'https://x.test/i.jpg', $all) as $l) {
            $this->assertNotFalse(filter_var(str_starts_with($l['href'], 'mailto:') ? 'https://m.test' : $l['href'], FILTER_VALIDATE_URL), $l['key']);
            $this->assertStringNotContainsString('{', $l['href'], $l['key'].' left a placeholder');
        }
    }

    public function test_related_posts_prefer_category_exclude_self_backfill_and_hide_when_thin(): void
    {
        $cur = $this->mk('Current', 'Guides');
        $this->mk('Other one', 'News', 5);
        $this->assertSame([], RelatedPosts::for($cur), 'fewer than 3 candidates hides the feature');
        $same = $this->mk('Same cat', 'Guides', 9);
        $this->mk('Third', 'News', 3);
        $this->mk('Fourth', 'News', 2);
        $titles = array_column(RelatedPosts::for($cur), 'title');
        $this->assertSame('Same cat', $titles[0]);
        $this->assertNotContains('Current', $titles);
        $this->assertGreaterThanOrEqual(3, count($titles));
        $this->assertContains('Fourth', $titles);   // back-filled with the latest
    }

    public function test_publishing_invalidates_the_related_cache(): void
    {
        $cur = $this->mk('Current');
        foreach (['A', 'B', 'C'] as $t) { $this->mk($t); }
        $this->assertCount(3, RelatedPosts::for($cur));
        $this->mk('D');
        $this->assertCount(4, RelatedPosts::for($cur));
    }

    public function test_article_renders_modal_inline_carousel_and_og_tags(): void
    {
        $cur = $this->mk('Current');
        foreach (['A', 'B', 'C'] as $t) { $this->mk($t); }
        $html = $this->get(route('blog.show', $cur))->assertOk()->getContent();
        $this->assertStringContainsString('Share this article', $html);
        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('og:type" content="article"', $html);
        $this->assertStringContainsString('twitter:card" content="summary_large_image"', $html);
        $this->assertStringContainsString('data-keep-reading', $html);
    }

    public function test_inline_only_mode_drops_the_popup_but_keeps_the_carousel(): void
    {
        $cur = $this->mk('Current');
        foreach (['A', 'B', 'C'] as $t) { $this->mk($t); }
        BlogReaderSettings::save(['keep_popup' => false]);
        $html = $this->get(route('blog.show', $cur))->getContent();
        $this->assertStringNotContainsString('data-keep-reading', $html);
        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
        BlogReaderSettings::save(['keep_enabled' => false]);
        $this->assertStringNotContainsString('aria-roledescription="carousel"', $this->get(route('blog.show', $cur))->getContent());
    }

    public function test_event_endpoint_counts_daily_totals_and_rejects_unknown_events(): void
    {
        $p = $this->mk('Current');
        $this->postJson(route('blog.reader-event'), ['event' => 'share_click', 'channel' => 'whatsapp', 'post' => $p->id])->assertOk();
        $this->postJson(route('blog.reader-event'), ['event' => 'share_click', 'channel' => 'whatsapp', 'post' => $p->id])->assertOk();
        $this->assertSame(2, (int) DB::table('blog_reader_events')->where(['event' => 'share_click', 'channel' => 'whatsapp'])->value('total'));
        $this->postJson(route('blog.reader-event'), ['event' => 'drop table'])->assertStatus(422);
    }

    public function test_settings_are_clamped_and_unknown_channels_dropped(): void
    {
        BlogReaderSettings::save(['share_channels' => ['x', 'bogus'], 'keep_count' => 99, 'keep_scroll_pct' => 5, 'header_blog_layout' => 'zigzag']);
        $s = BlogReaderSettings::all();
        $this->assertSame(['x'], $s['share_channels']);
        $this->assertSame(8, $s['keep_count']);
        $this->assertSame(50, $s['keep_scroll_pct']);
        $this->assertSame('carousel', $s['header_blog_layout']);
    }

    public function test_admin_can_save_reader_settings_and_sees_the_analytics(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $u = \App\Models\User::factory()->create();
        $u->assignRole('super_admin');
        DB::table('blog_reader_events')->insert(['day' => now()->toDateString(), 'event' => 'readnext_shown', 'channel' => '', 'post_id' => 0, 'total' => 4]);
        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Admin\BlogReader::class)
            ->assertSee('Keep reading shown')
            ->call('toggleChannel', 'reddit')->set('keep_count', 5)->set('header_blog_layout', 'grid')->call('save')->assertHasNoErrors();
        $s = BlogReaderSettings::all();
        $this->assertContains('reddit', $s['share_channels']);
        $this->assertSame(5, $s['keep_count']);
        $this->assertSame('grid', $s['header_blog_layout']);
    }

    public function test_header_preview_follows_the_layout_setting_and_is_empty_without_posts(): void
    {
        $render = fn () => view('components.blog.menu-preview')->render();
        $this->assertStringNotContainsString('From the blog', $render());
        foreach (['A', 'B', 'C'] as $t) { $this->mk($t); }
        foreach (['grid', 'list', 'carousel'] as $layout) {
            BlogReaderSettings::save(['header_blog_layout' => $layout, 'header_blog_count' => 3]);
            $html = $render();
            $this->assertStringContainsString('data-blog-preview="'.$layout.'"', $html);
            $this->assertStringContainsString('From the blog', $html);
        }
        $this->mk('Newest');
        $this->assertStringContainsString('Newest', $render() . view('components.blog.menu-preview')->render()) ;
    }

    public function test_blog_preview_lives_in_the_global_menu_with_legal_not_in_the_more_sheet(): void
    {
        $this->assertStringContainsString('x-blog.menu-preview', file_get_contents(resource_path('views/components/global-sidebar.blade.php')));
        $this->assertStringNotContainsString('menu-preview', file_get_contents(resource_path('views/components/more-sheet.blade.php')));
    }
}
