<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushJob;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\RefundNotification;
use App\Services\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeWebPushSender;
use Tests\TestCase;

/**
 * Self-hosted web push (owner request — closed-tab notifications). Browsers
 * subscribe through us (VAPID, no third-party service); user-facing
 * notifications also push; and delivery prunes dead subscriptions.
 */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private function configureVapid(): void
    {
        config([
            'webpush.vapid.public_key' => 'BPUBLIC_TEST_KEY',
            'webpush.vapid.private_key' => 'PRIVATE_TEST_KEY',
            'webpush.vapid.subject' => 'https://naarasim.test',
        ]);
    }

    public function test_a_user_can_store_and_remove_a_subscription(): void
    {
        $this->configureVapid();
        $user = User::factory()->create();
        $payload = [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'akey'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($user->fresh())->postJson(route('push.subscribe'), $payload)->assertOk();
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => 'https://push.example.com/abc']);

        // Re-subscribing the same endpoint updates, never duplicates.
        $this->actingAs($user->fresh())->postJson(route('push.subscribe'), $payload)->assertOk();
        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());

        $this->actingAs($user->fresh())->postJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::where('user_id', $user->id)->count());
    }

    public function test_a_user_facing_notification_dispatches_a_web_push(): void
    {
        $this->configureVapid();
        Queue::fake();
        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'https://p/1', 'endpoint_hash' => hash('sha256', 'https://p/1'),
            'public_key' => 'k', 'auth_token' => 'a',
        ]);

        $user->notifyNow(new RefundNotification(5.00, 'USD', 'test'));

        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->userId === $user->id
            && $job->payload['title'] === 'Refunded to your wallet');
    }

    public function test_no_web_push_when_vapid_is_not_configured(): void
    {
        config(['webpush.vapid.public_key' => '', 'webpush.vapid.private_key' => '']);
        Queue::fake();
        $user = User::factory()->create();

        $user->notifyNow(new RefundNotification(5.00, 'USD'));

        Queue::assertNotPushed(SendWebPushJob::class);
    }

    public function test_the_send_job_delivers_and_prunes_dead_subscriptions(): void
    {
        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'https://p/dead', 'endpoint_hash' => hash('sha256', 'https://p/dead'),
            'public_key' => 'k', 'auth_token' => 'a',
        ]);

        // The browser has unsubscribed → sender reports gone → row is pruned.
        $fake = new FakeWebPushSender(stillValid: false);
        $this->app->instance(WebPushSender::class, $fake);

        (new SendWebPushJob($user->id, ['title' => 'Hi', 'body' => 'there', 'url' => '/notifications']))
            ->handle($fake);

        $this->assertCount(1, $fake->sent);
        $this->assertSame(0, PushSubscription::where('user_id', $user->id)->count());
    }
}
