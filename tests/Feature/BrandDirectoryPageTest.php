<?php

namespace Tests\Feature;

use App\Livewire\BrandHunt;
use App\Models\BrandPartner;
use App\Models\BrandPartnerVideo;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** BUILD-9 §8: the public directory shows only active listings; videos earn once. */
class BrandDirectoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_shows_only_active_listings(): void
    {
        BrandPartner::create(['brand_name' => 'LiveBrand', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'is_featured' => true, 'background_color' => '#000', 'is_active' => true, 'category' => 'Tech & Apps']);
        BrandPartner::create(['brand_name' => 'PausedBrand', 'listing_status' => BrandPartner::STATUS_PAUSED, 'background_color' => '#000', 'is_active' => true, 'category' => 'Tech & Apps']);

        Livewire::actingAs(User::factory()->create())->test(BrandHunt::class)
            ->assertSee('LiveBrand')
            ->assertDontSee('PausedBrand');
    }

    public function test_watching_a_brand_video_earns_once_through_the_component(): void
    {
        $user = User::factory()->create();
        $brand = BrandPartner::create(['brand_name' => 'V', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#000', 'is_active' => true, 'category' => 'Tech & Apps']);
        $video = BrandPartnerVideo::create(['brand_partner_id' => $brand->id, 'video_url' => 'https://youtube.com/watch?v=abc123', 'platform' => 'youtube', 'sort_order' => 1]);

        // videoStart on the component mints a real token bound to this user+video.
        $comp = Livewire::actingAs($user)->test(BrandHunt::class);
        $comp->call('videoStart', $video->id);

        // Drive heartbeats past 60s of confirmed watch-time via the component method.
        for ($t = 10; $t <= 80; $t += 10) {
            $token = app(\App\Services\Social\VideoWatchService::class)->start($user, $video)['token'];
        }
        // Use one continuous session for the grant.
        $token = app(\App\Services\Social\VideoWatchService::class)->start($user, $video)['token'];
        for ($t = 10; $t <= 80; $t += 10) {
            $comp->call('videoHeartbeat', $token, $t);
        }
        $this->assertSame(\App\Services\Social\VideoWatchService::reward(), app(CreditService::class)->balance($user->fresh()));
    }
}
