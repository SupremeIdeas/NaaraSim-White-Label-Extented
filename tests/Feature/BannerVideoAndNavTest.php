<?php

namespace Tests\Feature;

use App\Livewire\Admin\Banners;
use App\Models\Banner;
use App\Models\User;
use App\Support\Banners as BannerCache;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Motion banners (video in the "More" zone) + the collapsible desktop sidebar.
 */
class BannerVideoAndNavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_a_banner_with_a_video_renders_a_looping_video_with_a_poster(): void
    {
        Banner::create([
            'title' => 'Motion promo', 'placement' => 'menu_sheet',
            'image_url' => '/banners/poster.png', 'video_url' => '/banners/clip.mp4',
            'is_active' => true,
        ]);
        BannerCache::flush();

        $html = Blade::render('<x-banner-zone placement="menu_sheet" />');

        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('src="/banners/clip.mp4"', $html);
        $this->assertStringContainsString('type="video/mp4"', $html);
        $this->assertStringContainsString('poster="/banners/poster.png"', $html); // fallback poster
        $this->assertStringContainsString('muted', $html);
        $this->assertStringContainsString('loop', $html);
    }

    public function test_an_image_only_banner_still_renders_a_picture_not_a_video(): void
    {
        Banner::create([
            'title' => 'Static promo', 'placement' => 'menu_sheet',
            'image_url' => '/banners/poster.png', 'is_active' => true,
        ]);
        BannerCache::flush();

        $html = Blade::render('<x-banner-zone placement="menu_sheet" />');
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringNotContainsString('<video', $html);
    }

    public function test_the_video_upload_rules_cap_at_ten_megabytes_and_accept_mp4_webm(): void
    {
        $rules = MediaStorage::videoUploadRules();
        $this->assertContains('max:10240', $rules);              // 10 MB
        $this->assertContains('mimetypes:video/mp4,video/webm', $rules);
    }

    public function test_admin_rejects_an_oversize_or_wrong_type_banner_video(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        // 12 MB "video" — over the 10 MB cap.
        $tooBig = UploadedFile::fake()->create('promo.mp4', 12 * 1024, 'video/mp4');
        Livewire::actingAs($admin)->test(Banners::class)
            ->set('title', 'Promo')->set('placement', 'menu_sheet')
            ->set('image', UploadedFile::fake()->image('poster.jpg'))
            ->set('video', $tooBig)
            ->call('save')
            ->assertHasErrors(['video']);
    }

    public function test_the_desktop_sidebar_exposes_a_collapse_toggle_and_persisted_state(): void
    {
        $user = User::factory()->create();
        $html = Blade::render(
            '<x-app-shell :primary="$primary" :more="$more">x</x-app-shell>',
            ['primary' => [['route' => 'dashboard', 'label' => 'Home', 'icon' => 'signal']], 'more' => []]
        );

        // Collapsible state is wired and remembered in localStorage.
        $this->assertStringContainsString('navCollapsed', $html);
        $this->assertStringContainsString('nx_nav_collapsed', $html);
        $this->assertStringContainsString('Collapse menu', $html);   // the toggle's aria-label expression
        $this->assertStringContainsString('lg:!w-24', $html);        // icon-only rail width
    }
}
