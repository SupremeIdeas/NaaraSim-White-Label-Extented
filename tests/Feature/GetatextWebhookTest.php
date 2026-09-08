<?php

namespace Tests\Feature;

use App\Events\OtpReceived;
use App\Models\SmsOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GetatextWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): SmsOrder
    {
        return SmsOrder::create([
            'user_id' => User::factory()->create()->id,
            'provider' => 'getatext',
            'service_name' => 'whatsapp',
            'getatext_id' => '12345',
            'phone_number' => '15551234567',
            'status' => 'waiting',
            'provider_cost' => 1.0,
            'charged_to_user' => 3.0,
            'ordered_at' => now(),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => 12345,
            'code' => 654321,
            'received_at' => '2026-07-06 15:43:37',
            'number' => '15551234567',
            'service_name' => 'Whatsapp',
            'status' => 'active',
        ], $overrides);
    }

    public function test_valid_webhook_stores_code_completes_order_and_broadcasts(): void
    {
        config(['services.getatext.webhook_token' => 'shhh']);
        Event::fake([OtpReceived::class]);
        $order = $this->pendingOrder();

        $this->withHeaders(['X-Webhook-Token' => 'shhh'])
            ->postJson('/webhooks/getatext', $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('654321', $order->otp_code);

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'getatext', 'verified' => true, 'processed' => true,
        ]);
        Event::assertDispatched(OtpReceived::class);
    }

    public function test_repeat_delivery_is_idempotent(): void
    {
        config(['services.getatext.webhook_token' => 'shhh']);
        Event::fake([OtpReceived::class]);
        $this->pendingOrder();

        $this->withHeaders(['X-Webhook-Token' => 'shhh'])->postJson('/webhooks/getatext', $this->payload())->assertOk();
        $this->withHeaders(['X-Webhook-Token' => 'shhh'])->postJson('/webhooks/getatext', $this->payload())->assertOk();

        // Code broadcast exactly once despite two deliveries.
        Event::assertDispatchedTimes(OtpReceived::class, 1);
    }

    public function test_unverified_webhook_is_rejected_when_a_secret_is_set(): void
    {
        config(['services.getatext.webhook_token' => 'shhh']);
        Event::fake([OtpReceived::class]);
        $order = $this->pendingOrder();

        $this->postJson('/webhooks/getatext', $this->payload())->assertStatus(401);

        $this->assertSame('waiting', $order->fresh()->status);
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'getatext', 'verified' => false]);
        Event::assertNotDispatched(OtpReceived::class);
    }

    public function test_correct_secret_is_accepted(): void
    {
        config(['services.getatext.webhook_token' => 'shhh']);
        $order = $this->pendingOrder();

        $this->withHeaders(['X-Webhook-Token' => 'shhh'])
            ->postJson('/webhooks/getatext', $this->payload())
            ->assertOk();

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_no_secret_configured_fails_closed_not_open(): void
    {
        // Readiness-audit fix (2026-09-07): an unconfigured secret must
        // reject every request, never silently "verify" them.
        config(['services.getatext.webhook_token' => '']);
        Event::fake([OtpReceived::class]);
        $order = $this->pendingOrder();

        $this->postJson('/webhooks/getatext', $this->payload())->assertStatus(401);

        $this->assertSame('waiting', $order->fresh()->status);
        Event::assertNotDispatched(OtpReceived::class);
    }
}
