<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EventNotification;
use App\Support\Notifications\NotificationPrefs;
use App\Support\Notifications\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_default_person_gets_the_events_channels_and_the_copy_is_filled(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $this->assertSame(['in_app', 'toast', 'push'], Notify::event($u, 'reward.referral_credited', ['amount' => '$2.50'], 'k1'));
        Notification::assertSentTo($u, EventNotification::class, function ($n) {
            return str_contains($n->payload['body'], '$2.50') && $n->via === ['database', 'webpush'] && $n->payload['toast'] === true;
        });
    }

    public function test_the_same_event_and_key_is_delivered_once(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $this->assertNotSame([], Notify::event($u, 'payout.frozen', [], 'f1'));
        $this->assertSame([], Notify::event($u, 'payout.frozen', [], 'f1'));
        $this->assertNotSame([], Notify::event($u, 'payout.frozen', [], 'f2'));
        Notification::assertSentToTimes($u, EventNotification::class, 2);
    }

    public function test_preferences_switch_channels_off_but_never_silence_a_critical_bell_copy(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        // lifecycle (non-critical): turn everything off -> nothing is sent
        NotificationPrefs::save($u, ['lifecycle' => ['in_app' => false, 'push' => false, 'email' => false]], null, null);
        $this->assertSame([], Notify::event($u, 'reward.referral_credited', ['amount' => '$1'], 'a'));
        // security (critical): asking to turn everything off still keeps the bell, drops email
        NotificationPrefs::save($u, ['security' => ['in_app' => false, 'push' => false, 'email' => false]], null, null);
        $this->assertSame(['in_app'], Notify::event($u, 'payout.frozen', [], 'b'));
    }

    public function test_quiet_hours_hold_push_but_not_the_bell_and_cross_midnight(): void
    {
        $u = User::factory()->create(['timezone' => 'UTC']);
        NotificationPrefs::save($u, [], '22:00', '07:00');
        $this->assertTrue(NotificationPrefs::inQuietHours($u, \Carbon\Carbon::parse('2026-10-12 23:30', 'UTC')));
        $this->assertTrue(NotificationPrefs::inQuietHours($u, \Carbon\Carbon::parse('2026-10-12 06:59', 'UTC')));
        $this->assertFalse(NotificationPrefs::inQuietHours($u, \Carbon\Carbon::parse('2026-10-12 12:00', 'UTC')));

        \Illuminate\Support\Carbon::setTestNow('2026-10-12 23:30:00');
        $plan = Notify::plan($u, 'reward.referral_credited', ['amount' => '$1']);
        \Illuminate\Support\Carbon::setTestNow();
        $this->assertSame(['in_app', 'toast'], $plan['channels']);
    }

    public function test_an_unknown_event_or_a_failing_delivery_never_throws_into_the_caller(): void
    {
        $u = User::factory()->create();
        $this->assertSame([], Notify::event($u, 'nope.nothing'));
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class)->shouldReceive('send')->andThrow(new \RuntimeException('queue down'));
        $this->assertSame([], Notify::event($u, 'payout.frozen', [], 'x'));
    }

    public function test_freezing_a_payee_tells_them(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        app(\App\Services\Payouts\Hardening\PayoutFreeze::class)->freeze($u, 'not_me');
        Notification::assertSentTo($u, EventNotification::class, fn ($n) => $n->event === 'payout.frozen');
    }

    public function test_the_preferences_page_saves_and_keeps_critical_in_app_on(): void
    {
        $u = User::factory()->create();
        $c = \Livewire\Livewire::actingAs($u)->test(\App\Livewire\NotificationPreferences::class)
            ->set('channels.lifecycle.push', false)->set('channels.security.in_app', false)->set('quiet_start', '22:00')->set('quiet_end', '07:00')->call('save')->assertHasNoErrors();
        $p = NotificationPrefs::for($u);
        $this->assertFalse($p['channels']['lifecycle']['push']);
        $this->assertTrue($p['channels']['security']['in_app']);
        $this->assertSame('22:00', $p['quiet_start']);
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]))->get('/notifications/settings')->assertOk()->assertSee('Quiet hours');
    }
}
