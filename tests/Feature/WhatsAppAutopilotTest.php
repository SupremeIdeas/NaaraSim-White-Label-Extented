<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppTemplateJob;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppAutopilot;
use App\Services\WhatsApp\WhatsAppCloudClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WhatsApp Autopilot (BUILD-4 §7). Opt-in, gated, template-based lifecycle
 * notifications over the Meta Cloud API. Covers the gates (config, opt-in,
 * template), the client payload, and the webhook (handshake, signature, STOP).
 */
class WhatsAppAutopilotTest extends TestCase
{
    use RefreshDatabase;

    private function goLive(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '123456',
            'services.whatsapp.access_token' => 'tok_live',
            'services.whatsapp.app_secret' => 'shhh',
            'services.whatsapp.verify_token' => 'verify-me',
            'naara.whatsapp_autopilot.enabled' => true,
            'naara.whatsapp_autopilot.templates.esim_delivered' => 'esim_delivered',
        ]);
    }

    public function test_it_does_nothing_when_the_api_is_not_configured(): void
    {
        Queue::fake();
        config(['services.whatsapp.phone_number_id' => '', 'services.whatsapp.access_token' => '']);
        $user = User::factory()->create(['whatsapp_opt_in' => true, 'phone' => '2348012345678']);

        $this->assertFalse(app(WhatsAppAutopilot::class)->notify($user, 'esim_delivered', ['Ada', 'USA 3GB']));
        Queue::assertNothingPushed();
    }

    public function test_it_does_nothing_for_a_user_who_did_not_opt_in(): void
    {
        Queue::fake();
        $this->goLive();
        $user = User::factory()->create(['whatsapp_opt_in' => false, 'phone' => '2348012345678']);

        $this->assertFalse(app(WhatsAppAutopilot::class)->notify($user, 'esim_delivered', ['Ada', 'USA 3GB']));
        Queue::assertNothingPushed();
    }

    public function test_it_queues_a_template_for_an_opted_in_reachable_user(): void
    {
        Queue::fake();
        $this->goLive();
        $user = User::factory()->create(['whatsapp_opt_in' => true, 'phone' => '2348012345678']);

        $this->assertTrue(app(WhatsAppAutopilot::class)->notify($user, 'esim_delivered', ['Ada', 'USA 3GB']));
        Queue::assertPushed(SendWhatsAppTemplateJob::class, function ($job) {
            return $job->toE164 === '2348012345678' && $job->template === 'esim_delivered';
        });
    }

    public function test_an_unmapped_event_is_skipped(): void
    {
        Queue::fake();
        $this->goLive();
        config(['naara.whatsapp_autopilot.templates.low_balance' => '']); // disabled
        $user = User::factory()->create(['whatsapp_opt_in' => true, 'phone' => '2348012345678']);

        $this->assertFalse(app(WhatsAppAutopilot::class)->notify($user, 'low_balance', []));
        Queue::assertNothingPushed();
    }

    public function test_the_client_posts_a_template_message_and_returns_the_id(): void
    {
        $this->goLive();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200),
        ]);

        $id = app(WhatsAppCloudClient::class)->sendTemplate(
            '2348012345678', 'esim_delivered', 'en', WhatsAppCloudClient::bodyComponents(['Ada', 'USA 3GB']),
        );

        $this->assertSame('wamid.ABC', $id);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/123456/messages')
                && $request['type'] === 'template'
                && $request['template']['name'] === 'esim_delivered'
                && $request['to'] === '2348012345678';
        });
    }

    public function test_the_client_is_a_safe_no_op_when_unconfigured(): void
    {
        Http::fake();
        config(['services.whatsapp.phone_number_id' => '', 'services.whatsapp.access_token' => '']);

        $this->assertNull(app(WhatsAppCloudClient::class)->sendTemplate('2348012345678', 'esim_delivered'));
        Http::assertNothingSent();
    }

    public function test_the_webhook_handshake_echoes_the_challenge_only_with_the_right_token(): void
    {
        $this->goLive();

        $this->get('/webhooks/whatsapp?hub_verify_token=verify-me&hub_challenge=42')
            ->assertOk()->assertSee('42');

        $this->get('/webhooks/whatsapp?hub_verify_token=wrong&hub_challenge=42')
            ->assertStatus(403);
    }

    public function test_the_webhook_rejects_a_bad_signature(): void
    {
        $this->goLive();
        $body = json_encode(['entry' => []]);

        $this->call('POST', '/webhooks/whatsapp', [], [], [],
            ['HTTP_X-Hub-Signature-256' => 'sha256=deadbeef', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);
    }

    public function test_an_inbound_stop_message_opts_the_user_out(): void
    {
        $this->goLive();
        $user = User::factory()->create(['whatsapp_opt_in' => true, 'phone' => '2348012345678']);

        $payload = ['entry' => [['changes' => [['value' => ['messages' => [
            ['from' => '2348012345678', 'text' => ['body' => 'STOP']],
        ]]]]]]];
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'shhh');

        $this->call('POST', '/webhooks/whatsapp', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertFalse($user->fresh()->whatsapp_opt_in);
    }
}
