<?php

namespace Tests\Feature;

use App\Livewire\NotificationCenter;
use App\Livewire\Notifications as NotificationsPage;
use App\Models\Announcement;
use App\Models\User;
use App\Models\WalletGroupMember;
use App\Notifications\BroadcastAnnouncement;
use App\Notifications\RefundNotification;
use App\Services\Wallet\WalletGroupService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * In-app notification centre (owner request). Every user-facing notification is
 * delivered to the bell as well as email; the bell shows unread counts and lets
 * the user read/act; and a user only ever sees their OWN notifications.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_facing_notification_lands_in_the_database_channel(): void
    {
        $user = User::factory()->create();
        // RefundNotification now declares ['mail','database'] — sent synchronously
        // here (no queue) so the row exists immediately.
        $user->notifyNow(new RefundNotification(5.00, 'USD', 'test refund'));

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(1, $user->unreadNotifications()->count());
        $data = $user->notifications()->first()->data;
        $this->assertSame('wallet', $data['category']);
        $this->assertSame('Refunded to your wallet', $data['title']);
        $this->assertSame('/wallet', parse_url($data['action_url'], PHP_URL_PATH));
    }

    public function test_the_bell_shows_the_unread_count_and_can_mark_all_read(): void
    {
        $user = User::factory()->create();
        $user->notifyNow(new RefundNotification(5.00, 'USD'));
        $user->notifyNow(new RefundNotification(6.00, 'USD'));

        Livewire::actingAs($user)->test(NotificationCenter::class)
            ->assertSee('2') // badge
            ->call('markAllRead')
            ->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_go_marks_read_and_redirects_to_the_action_url(): void
    {
        $user = User::factory()->create();
        $user->notifyNow(new RefundNotification(5.00, 'USD'));
        $id = $user->notifications()->first()->id;

        Livewire::actingAs($user)->test(NotificationCenter::class)
            ->call('go', $id)
            ->assertRedirect('/wallet');

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_a_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notifyNow(new RefundNotification(5.00, 'USD'));
        $id = $owner->notifications()->first()->id;

        $attacker = User::factory()->create();
        Livewire::actingAs($attacker)->test(NotificationCenter::class)
            ->call('markAsRead', $id);

        // The owner's notification is untouched — scoped by the notifiable.
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    /**
     * Owner request: the bell's popup used to be a hand-rolled Alpine panel
     * with a generic fade/scale transition that read as an abrupt "jump" on
     * mobile instead of a proper slide-up. It now reuses the platform's ONE
     * shared modal engine (S31) — same slide-up-from-the-bottom sheet every
     * other dialog uses, still hands off to the full /notifications page.
     */
    public function test_the_bell_reuses_the_shared_modal_engine(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(NotificationCenter::class)
            ->assertSeeHtml('x-on:open-modal.window')
            ->assertSee('See all notifications');
    }

    /**
     * Frontend-UX-fix blueprint Phase A — found while verifying the modal
     * engine's own x-teleport fix: the mobile and desktop headers each mount
     * a SEPARATE instance of this component. `open-modal`/`close-modal` fire
     * as global window events matched by name, so a shared literal modal
     * name would open BOTH instances' dialogs the instant either bell is
     * clicked. This went unnoticed for a long time because the mobile
     * instance's dialog lived inside a `lg:hidden` header at desktop
     * widths, which happened to keep its (also-open) dialog invisible —
     * teleporting removed that accidental masking. The modal name must be
     * derived from this Livewire instance's own id so two instances never
     * collide.
     */
    public function test_two_bell_instances_never_share_a_modal_name(): void
    {
        $user = User::factory()->create();

        $mobile = Livewire::actingAs($user)->test(NotificationCenter::class);
        $desktop = Livewire::actingAs($user)->test(NotificationCenter::class);

        preg_match("/name: '([^']+)'/", $mobile->html(), $mobileMatch);
        preg_match("/name: '([^']+)'/", $desktop->html(), $desktopMatch);

        $this->assertNotEmpty($mobileMatch[1] ?? null);
        $this->assertNotEmpty($desktopMatch[1] ?? null);
        $this->assertNotSame($mobileMatch[1], $desktopMatch[1]);
    }

    public function test_the_full_page_lists_and_filters(): void
    {
        $user = User::factory()->create();
        $user->notifyNow(new RefundNotification(5.00, 'USD', 'refund one'));

        Livewire::actingAs($user)->test(NotificationsPage::class)
            ->assertSee('Refunded to your wallet')
            ->set('filter', 'wallet')
            ->assertSee('Refunded to your wallet')
            ->set('filter', 'support')
            ->assertDontSee('Refunded to your wallet');
    }

    /**
     * Tier 5 #11 Phase A1/A3 — the real notification surface (not just the
     * admin preview) renders each announcement's chosen style: banner_hero's
     * wide image + single CTA, or dark_feature's dark card + bullets +
     * secondary link. The compact bell dropdown stays a generic row for
     * every notification type — only the full /notifications page gets the
     * rich per-style treatment.
     */
    public function test_the_full_page_renders_the_banner_hero_style(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::create([
            'title' => 'Weekend sale', 'body' => '20% off everything.', 'icon' => 'tag',
            'style' => 'banner_hero', 'image_path' => 'https://cdn.example.com/banner.jpg',
            'cta_label' => 'Shop now', 'cta_url' => 'https://example.com/catalogue',
            'audience' => 'all', 'status' => 'draft',
        ]);
        $user->notifyNow(new BroadcastAnnouncement($announcement));

        Livewire::actingAs($user)->test(NotificationsPage::class)
            ->assertSee('Weekend sale')
            ->assertSee('Shop now')
            ->assertSee('https://cdn.example.com/banner.jpg', false);
    }

    public function test_the_full_page_renders_the_dark_feature_style_with_bullets_and_secondary_link(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::create([
            'title' => "What's new", 'body' => 'A few things landed.', 'icon' => 'bell',
            'style' => 'dark_feature', 'feature_image_path' => 'https://cdn.example.com/inset.jpg',
            'bullets' => ['Faster eSIM activation', 'Lower fees'],
            'cta_label' => 'Open', 'cta_url' => 'https://example.com',
            'secondary_label' => 'View all changelogs', 'secondary_url' => 'https://example.com/whats-new',
            'audience' => 'all', 'status' => 'draft',
        ]);
        $user->notifyNow(new BroadcastAnnouncement($announcement));

        Livewire::actingAs($user)->test(NotificationsPage::class)
            ->assertSee("What's new")
            ->assertSee('Faster eSIM activation')
            ->assertSee('Lower fees')
            ->assertSee('View all changelogs')
            ->assertSee('https://cdn.example.com/inset.jpg', false);
    }

    public function test_the_bell_dropdown_stays_a_compact_row_for_a_styled_announcement(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::create([
            'title' => 'Weekend sale', 'body' => '20% off everything.', 'icon' => 'tag',
            'style' => 'banner_hero', 'image_path' => 'https://cdn.example.com/banner.jpg',
            'cta_label' => 'Shop now', 'cta_url' => 'https://example.com/catalogue',
            'audience' => 'all', 'status' => 'draft',
        ]);
        $user->notifyNow(new BroadcastAnnouncement($announcement));

        Livewire::actingAs($user)->test(NotificationCenter::class)
            ->assertSee('Weekend sale')
            ->assertDontSee('https://cdn.example.com/banner.jpg', false);
    }

    /**
     * Tier 5 #11 Phase B — the header's own 30s poll doubles as the live
     * shared-wallet-invite check. A pending invite that hasn't been toasted
     * yet fires a hero toast with wired Accept/Decline actions the instant
     * the poll ticks, no page reload needed.
     */
    public function test_the_poll_toasts_a_new_pending_wallet_invite(): void
    {
        $owner = User::factory()->create(['name' => 'Ada']);
        $invitee = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($invitee)->test(NotificationCenter::class)
            ->call('checkForWalletInvites')
            ->assertDispatched('nx-toast', function (string $name, array $params) use ($member) {
                return ($params['variant'] ?? null) === 'hero'
                    && str_contains($params['message'], 'Ada')
                    && ($params['actions'][0]['payload']['memberId'] ?? null) === $member->id;
            });

        $this->assertNotNull($member->fresh()->toast_shown_at);
    }

    public function test_the_poll_never_double_toasts_the_same_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($invitee)->test(NotificationCenter::class)
            ->call('checkForWalletInvites')
            ->assertDispatched('nx-toast');

        // A second instance (e.g. the desktop header) polling right after
        // must see the invite already marked toasted and stay silent.
        Livewire::actingAs($invitee)->test(NotificationCenter::class)
            ->call('checkForWalletInvites')
            ->assertNotDispatched('nx-toast');
    }

    public function test_the_toasts_accept_action_actually_joins_the_plan(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        app(WalletService::class)->credit($owner, 50.0, 'USD', ['reference' => 'seed']);
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($invitee)->test(NotificationCenter::class)
            ->call('respondToWalletInvite', $member->id, true)
            ->assertDispatched('nx-toast', type: 'success');

        $this->assertNotNull($member->fresh()->accepted_at);
    }

    public function test_the_toasts_decline_action_removes_the_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($invitee)->test(NotificationCenter::class)
            ->call('respondToWalletInvite', $member->id, false)
            ->assertDispatched('nx-toast', type: 'info');

        $this->assertDatabaseMissing('wallet_group_members', ['id' => $member->id]);
    }

    public function test_a_user_cannot_respond_to_someone_elses_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $attacker = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($attacker)->test(NotificationCenter::class)
            ->call('respondToWalletInvite', $member->id, true)
            ->assertNotDispatched('nx-toast');

        $this->assertNull($member->fresh()->accepted_at);
        $this->assertDatabaseHas('wallet_group_members', ['id' => $member->id]);
    }
}
