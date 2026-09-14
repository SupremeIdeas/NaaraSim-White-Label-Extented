<?php

namespace Tests\Feature;

use App\Livewire\NotificationCenter;
use App\Livewire\Notifications as NotificationsPage;
use App\Models\User;
use App\Notifications\RefundNotification;
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
}
