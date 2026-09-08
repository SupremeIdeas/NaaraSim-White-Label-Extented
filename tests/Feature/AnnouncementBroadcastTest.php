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
use Illuminate\Support\Facades\Queue;
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
