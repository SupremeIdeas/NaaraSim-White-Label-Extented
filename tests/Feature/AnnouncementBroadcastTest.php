<?php

namespace Tests\Feature;

use App\Jobs\BroadcastAnnouncementJob;
use App\Livewire\Admin\Announcements;
use App\Livewire\Catalogue;
use App\Models\Announcement;
use App\Models\Coupon;
use App\Models\User;
use App\Support\PendingCoupon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin announcements / offers (owner request). An admin pushes an offer once;
 * it fans out to every active user's bell, and an attached coupon becomes a
 * one-tap claim. Money-safety: a broadcast coupon must be live, and the discount
 * stays MarginGuard-clamped at checkout.
 */
class AnnouncementBroadcastTest extends TestCase
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

    public function test_admin_composes_and_dispatches_a_broadcast(): void
    {
        Queue::fake();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Announcements::class)
            ->set('title', 'Weekend sale')
            ->set('body', '20% off all eSIMs this weekend.')
            ->set('icon', 'tag')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('announcements', ['title' => 'Weekend sale', 'created_by' => $admin->id]);
        Queue::assertPushed(BroadcastAnnouncementJob::class);
    }

    public function test_a_non_admin_cannot_reach_the_composer(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Announcements::class)
            ->assertForbidden();
    }

    public function test_a_dead_coupon_is_rejected(): void
    {
        Queue::fake();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Announcements::class)
            ->set('title', 'Bad offer')->set('body', 'x')->set('icon', 'gift')
            ->set('coupon_code', 'NOPE') // no such coupon
            ->call('send')
            ->assertHasErrors('coupon_code');

        Queue::assertNothingPushed();
    }

    public function test_the_job_fans_out_to_active_users_only(): void
    {
        $active = User::factory()->count(3)->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);
        $announcement = Announcement::create([
            'title' => 'Hello', 'body' => 'World', 'icon' => 'bell', 'audience' => 'all', 'status' => 'draft',
        ]);

        (new BroadcastAnnouncementJob($announcement->id))->handle();

        $this->assertSame('sent', $announcement->fresh()->status);
        $this->assertSame(3, $announcement->fresh()->recipients);
        foreach ($active as $u) {
            $this->assertSame(1, $u->notifications()->count());
            $this->assertSame('offer', $u->notifications()->first()->data['category']);
        }
        $this->assertSame(0, $inactive->notifications()->count());
    }

    public function test_the_job_is_idempotent_and_wont_resend(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $announcement = Announcement::create(['title' => 'x', 'body' => 'y', 'status' => 'draft', 'audience' => 'all']);

        (new BroadcastAnnouncementJob($announcement->id))->handle();
        (new BroadcastAnnouncementJob($announcement->id))->handle(); // second run must no-op

        $this->assertSame(1, $user->notifications()->count());
    }

    /** Tier 5 #11 Phase A1 — banner_hero is the default style with an optional wide image. */
    public function test_banner_hero_is_the_default_style_and_stores_an_uploaded_image(): void
    {
        Storage::fake('public');
        Queue::fake();

        Livewire::actingAs($this->admin())->test(Announcements::class)
            ->assertSet('style', 'banner_hero')
            ->set('title', 'New feature')
            ->set('body', 'Faster activation is here.')
            ->set('image', UploadedFile::fake()->image('banner.jpg', 1600, 800))
            ->call('send')
            ->assertHasNoErrors();

        $announcement = Announcement::where('title', 'New feature')->firstOrFail();
        $this->assertSame('banner_hero', $announcement->style);
        $this->assertNotNull($announcement->image_path);
        $this->assertNull($announcement->feature_image_path);
    }

    /** Tier 5 #11 Phase A1 — dark_feature: bullets (one per line) + a secondary link. */
    public function test_dark_feature_style_stores_bullets_and_the_secondary_link(): void
    {
        Storage::fake('public');
        Queue::fake();

        Livewire::actingAs($this->admin())->test(Announcements::class)
            ->set('title', "What's new")
            ->set('body', 'A few things landed this week.')
            ->set('style', 'dark_feature')
            ->set('featureImage', UploadedFile::fake()->image('inset.jpg', 800, 800))
            ->set('bulletsText', "Faster eSIM activation\n\nNew number ports\nLower fees")
            ->set('secondary_label', 'View all changelogs')
            ->set('secondary_url', 'https://example.com/whats-new')
            ->call('send')
            ->assertHasNoErrors();

        $announcement = Announcement::where('title', "What's new")->firstOrFail();
        $this->assertSame('dark_feature', $announcement->style);
        $this->assertNotNull($announcement->feature_image_path);
        $this->assertNull($announcement->image_path);
        // Blank lines are dropped, order and content preserved.
        $this->assertSame(['Faster eSIM activation', 'New number ports', 'Lower fees'], $announcement->bullets);
        $this->assertSame('View all changelogs', $announcement->secondary_label);
        $this->assertSame('https://example.com/whats-new', $announcement->secondary_url);
    }

    /** banner_hero never carries bullets, even if the field was left over from a prior dark_feature draft. */
    public function test_banner_hero_style_never_stores_bullets(): void
    {
        Queue::fake();

        Livewire::actingAs($this->admin())->test(Announcements::class)
            ->set('title', 'x')->set('body', 'y')
            ->set('style', 'banner_hero')
            ->set('bulletsText', "This should be ignored")
            ->call('send')
            ->assertHasNoErrors();

        $this->assertNull(Announcement::where('title', 'x')->firstOrFail()->bullets);
    }

    /** The full in-app payload carries every style-specific field for the notification surface to render. */
    public function test_to_in_app_carries_the_full_style_payload(): void
    {
        $announcement = Announcement::create([
            'title' => 'Hello', 'body' => 'World', 'icon' => 'gift',
            'style' => 'dark_feature', 'feature_image_path' => 'https://cdn.example.com/inset.jpg',
            'bullets' => ['One', 'Two'], 'cta_label' => 'Open', 'cta_url' => 'https://example.com',
            'secondary_label' => 'More', 'secondary_url' => 'https://example.com/more',
            'audience' => 'all', 'status' => 'draft',
        ]);

        $payload = $announcement->toInApp();

        $this->assertSame('dark_feature', $payload['style']);
        $this->assertSame('https://cdn.example.com/inset.jpg', $payload['feature_image_url']);
        $this->assertSame(['One', 'Two'], $payload['bullets']);
        $this->assertSame('More', $payload['secondary_label']);
        $this->assertSame('https://example.com/more', $payload['secondary_url']);
    }

    public function test_an_offer_coupon_becomes_a_one_tap_claim_that_prefills_checkout(): void
    {
        Coupon::create([
            'code' => 'WEEKEND20', 'percent_off' => 20, 'applies_to' => 'all', 'is_active' => true,
        ]);
        $user = User::factory()->create();

        // Tapping "Claim" lands on the catalogue with ?claim=WEEKEND20.
        Livewire::actingAs($user)->withQueryParams(['claim' => 'WEEKEND20'])
            ->test(Catalogue::class)
            ->assertOk();

        // The code is stashed for the next checkout.
        $this->assertSame('WEEKEND20', PendingCoupon::peek());
    }
}
