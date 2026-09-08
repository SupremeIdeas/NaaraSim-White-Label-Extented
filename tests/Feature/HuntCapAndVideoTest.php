<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandPartnerVideo;
use App\Models\BrandVideoWatchClaim;
use App\Models\SocialFollowHandle;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Social\SocialFollowService;
use App\Services\Social\VideoWatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BUILD-9 §3 + §4: the daily credit cap and server-confirmed video watch-time. */
class HuntCapAndVideoTest extends TestCase
{
    use RefreshDatabase;

    private function video(): BrandPartnerVideo
    {
        $brand = BrandPartner::create(['brand_name' => 'Acme', 'background_color' => '#000', 'is_active' => true, 'listing_status' => BrandPartner::STATUS_ACTIVE]);

        return BrandPartnerVideo::create(['brand_partner_id' => $brand->id, 'video_url' => 'https://youtube.com/watch?v=x', 'platform' => 'youtube', 'sort_order' => 1]);
    }

    public function test_daily_cap_blocks_a_follow_once_reached(): void
    {
        $user = User::factory()->create();
        // Pre-load today's earnings to the cap via a big-reward follow.
        $big = SocialFollowHandle::create(['platform' => 'x', 'handle_label' => 'Big', 'handle_url' => 'https://x.com/b', 'credit_reward' => 100, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1]);
        $next = SocialFollowHandle::create(['platform' => 'instagram', 'handle_label' => 'Next', 'handle_url' => 'https://instagram.com/n', 'credit_reward' => 5, 'verification' => 'self', 'is_active' => true, 'sort_order' => 2]);
        $svc = app(SocialFollowService::class);

        $svc->claim($user, $big); // 100 credits → cap reached
        $blocked = $svc->claim($user, $next);

        $this->assertTrue($blocked['capped']);
        $this->assertSame(0.0, $blocked['earned']);
        $this->assertSame(100.0, app(CreditService::class)->balance($user->fresh()));
        // The blocked follow was NOT recorded — it can be claimed tomorrow.
        $this->assertFalse($svc->hasClaimed($user->fresh(), $next));
    }

    public function test_video_credit_only_after_60_confirmed_seconds_and_never_faked_by_a_jump(): void
    {
        $user = User::factory()->create();
        $video = $this->video();
        $svc = app(VideoWatchService::class);
        $s = $svc->start($user, $video);

        // A single jump to 999s cannot fake it — each step is capped at 15s.
        $r = $svc->heartbeat($user, $s['token'], 999);
        $this->assertFalse($r['claimed']);
        $this->assertLessThan(60, $r['confirmed']);
        $this->assertSame(0.0, app(CreditService::class)->balance($user->fresh()));

        // Steady ~10s heartbeats accumulate real watch-time to the threshold; the
        // grant fires exactly once, on the heartbeat that crosses 60s.
        $claimedCount = 0;
        for ($t = 10; $t <= 80; $t += 10) {
            $r = $svc->heartbeat($user, $s['token'], 999 + $t); // forward from the jump baseline
            $claimedCount += $r['claimed'] ? 1 : 0;
        }
        $this->assertSame(1, $claimedCount);
        $this->assertSame(VideoWatchService::reward(), app(CreditService::class)->balance($user->fresh()));
        $this->assertSame(1, BrandVideoWatchClaim::where('user_id', $user->id)->count());

        // A replay never re-grants.
        $again = $svc->start($user, $video);
        for ($t = 10; $t <= 80; $t += 10) {
            $svc->heartbeat($user, $again['token'], $t);
        }
        $this->assertSame(VideoWatchService::reward(), app(CreditService::class)->balance($user->fresh()));
        $this->assertSame(1, BrandVideoWatchClaim::where('user_id', $user->id)->count());
    }

    public function test_a_forged_token_for_another_user_grants_nothing(): void
    {
        $user = User::factory()->create();
        $attacker = User::factory()->create();
        $video = $this->video();
        $svc = app(VideoWatchService::class);
        $s = $svc->start($user, $video); // token minted for $user

        // Attacker replays the victim's token — bound to the victim's id, rejected.
        for ($t = 10; $t <= 80; $t += 10) {
            $svc->heartbeat($attacker, $s['token'], $t);
        }
        $this->assertSame(0.0, app(CreditService::class)->balance($attacker->fresh()));
    }
}
