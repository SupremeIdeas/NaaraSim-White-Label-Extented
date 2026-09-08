<?php

namespace Tests\Feature;

use App\Livewire\Admin\HomeMedia;
use App\Models\HomeVideo;
use App\Models\User;
use App\Support\HomeStory;
use App\Support\HomeVideos;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Homepage video (BUILD-3 §8) + story (§9) sections and their admin management.
 */
class HomeMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        HomeStory::flush();
        HomeVideos::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_youtube_id_is_parsed_from_any_common_url_shape(): void
    {
        $this->assertSame('dQw4w9WgXcQ', HomeVideo::parseYouTubeId('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', HomeVideo::parseYouTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3'));
        $this->assertSame('dQw4w9WgXcQ', HomeVideo::parseYouTubeId('dQw4w9WgXcQ'));
        $this->assertNull(HomeVideo::parseYouTubeId('not a video'));
    }

    public function test_video_section_is_hidden_until_an_active_entry_exists(): void
    {
        $this->assertFalse(HomeVideos::isVisible());

        HomeVideo::create(['title' => 'Guide', 'source_type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ', 'orientation' => 'portrait', 'is_active' => true]);
        HomeVideos::flush();

        $this->assertTrue(HomeVideos::isVisible());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&modestbranding=1&playsinline=1',
            HomeVideos::active()->first()->youTubeEmbedUrl());
    }

    public function test_admin_can_add_a_youtube_video_and_it_shows_on_the_homepage(): void
    {
        Livewire::actingAs($this->admin())->test(HomeMedia::class)
            ->set('v_title', 'How to install your eSIM')
            ->set('v_source', 'youtube')
            ->set('v_youtube', 'https://youtu.be/dQw4w9WgXcQ')
            ->set('v_orientation', 'landscape')
            ->call('addVideo')
            ->assertHasNoErrors();

        $v = HomeVideo::first();
        $this->assertSame('dQw4w9WgXcQ', $v->youtube_id);

        $this->get('/')->assertOk()
            ->assertSee('How to install your eSIM')
            ->assertSee('youtube'); // the data-kind wiring on the card
    }

    public function test_a_bad_youtube_link_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(HomeMedia::class)
            ->set('v_title', 'Bad')
            ->set('v_source', 'youtube')
            ->set('v_youtube', 'https://example.com/nope')
            ->call('addVideo')
            ->assertHasErrors('v_youtube');

        $this->assertSame(0, HomeVideo::count());
    }

    public function test_admin_can_upload_a_self_hosted_clip(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        Livewire::actingAs($this->admin())->test(HomeMedia::class)
            ->set('v_title', 'Local clip')
            ->set('v_source', 'upload')
            ->set('v_upload', UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'))
            ->set('v_orientation', 'portrait')
            ->call('addVideo')
            ->assertHasNoErrors();

        $v = HomeVideo::first();
        $this->assertSame('upload', $v->source_type);
        $this->assertNotNull($v->video_url);
        $this->assertTrue($v->isPlayable());
    }

    public function test_story_is_hidden_by_default_and_shows_once_enabled(): void
    {
        $this->assertFalse(HomeStory::isVisible());
        $this->get('/')->assertDontSee('storytestheading');

        Livewire::actingAs($this->admin())->test(HomeMedia::class)
            ->set('story_enabled', true)
            ->set('story_heading', 'storytestheading')
            ->set('story_body', 'First paragraph.')
            ->call('saveStory')
            ->assertHasNoErrors();

        $this->assertTrue(HomeStory::isVisible());
        $this->get('/')->assertOk()->assertSee('storytestheading');
    }

    public function test_the_csp_allows_the_youtube_embed_and_cloud_video(): void
    {
        $res = $this->get('/')->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://www.youtube-nocookie.com', $csp);
        $this->assertStringContainsString('media-src', $csp);
    }
}
