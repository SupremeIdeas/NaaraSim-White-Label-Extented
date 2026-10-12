<?php

namespace Tests\Feature;

use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletTransaction;
use App\Services\SMS\Numbers\TelnyxService;
use App\Services\SMS\Numbers\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** SMS delivery and inbound webhooks follow each provider's documented shape: Twilio (form + X-Twilio-Signature) and Telnyx (JSON + Ed25519), and a carrier failure refunds once. */
class SmsProviderWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function message(string $provider, string $ref, float $charged = 0.05): OutboundMessage
    {
        $user = User::factory()->create();
        $line = VirtualNumber::create(['user_id' => $user->id, 'provider' => $provider, 'phone_number' => '+15005550006', 'sid' => 'PN1', 'status' => 'active', 'monthly_cost' => 1.0, 'monthly_retail' => 3.0]);

        return OutboundMessage::create(['user_id' => $user->id, 'virtual_number_id' => $line->id, 'to_number' => '+2348012345678', 'body' => 'hi', 'provider' => $provider,
            'provider_ref' => $ref, 'status' => 'sent', 'segments' => 1, 'amount_charged' => $charged, 'reference' => 'sms-send:'.$user->id.':'.$ref]);
    }

    private function twilioPost(string $url, array $params, bool $sign = true)
    {
        config(['services.twilio.auth_token' => 'tok', 'services.twilio.account_sid' => 'AC1']);
        $sorted = $params;
        ksort($sorted);
        $data = $url;
        foreach ($sorted as $k => $v) {
            $data .= $k.$v;
        }
        $headers = $sign ? ['X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $data, 'tok', true))] : [];

        return $this->post($url, $params, $headers);
    }

    private function telnyxPost(string $url, array $body, bool $sign = true, ?int $ts = null)
    {
        $kp = sodium_crypto_sign_keypair();
        config(['services.telnyx.public_key' => base64_encode(sodium_crypto_sign_publickey($kp))]);
        $raw = json_encode($body);
        $ts = (string) ($ts ?? time());
        $sig = base64_encode(sodium_crypto_sign_detached($ts.'|'.$raw, sodium_crypto_sign_secretkey($kp)));
        $server = $sign ? ['HTTP_TELNYX_SIGNATURE_ED25519' => $sig, 'HTTP_TELNYX_TIMESTAMP' => $ts] : [];

        return $this->call('POST', $url, [], [], [], $server + ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw);
    }

    private function refunds(OutboundMessage $m): int
    {
        return WalletTransaction::where('reference', 'refund:'.$m->reference)->count();
    }

    public function test_a_twilio_delivered_report_needs_a_valid_signature_and_settles_the_message(): void
    {
        $m = $this->message('twilio', 'SM1');
        $url = route('webhooks.sms-delivery', 'twilio');

        $this->twilioPost($url, ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'], false)->assertStatus(401);
        $this->assertSame('sent', $m->fresh()->status);
        $this->twilioPost($url, ['MessageSid' => 'SM1', 'MessageStatus' => 'sent'])->assertOk();
        $this->assertSame('sent', $m->fresh()->status, 'an in-flight status changes nothing');
        $this->twilioPost($url, ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'])->assertOk();
        $this->assertSame('delivered', $m->fresh()->status);
    }

    public function test_a_twilio_failure_refunds_once_even_when_the_report_is_repeated_or_late_delivery_arrives(): void
    {
        $m = $this->message('twilio', 'SM2');
        $url = route('webhooks.sms-delivery', 'twilio');

        $this->twilioPost($url, ['MessageSid' => 'SM2', 'MessageStatus' => 'undelivered', 'ErrorCode' => '30005'])->assertOk();
        $this->twilioPost($url, ['MessageSid' => 'SM2', 'MessageStatus' => 'undelivered', 'ErrorCode' => '30005'])->assertOk();
        $this->assertSame('failed', $m->fresh()->status);
        $this->assertSame(1, $this->refunds($m), 'one refund, never two');
        $this->assertEqualsWithDelta(0.05, (float) $m->user->fresh()->wallet->usd_balance, 0.00001);
    }

    public function test_a_delivered_message_is_never_flipped_to_failed_or_refunded(): void
    {
        $m = $this->message('twilio', 'SM3');
        $url = route('webhooks.sms-delivery', 'twilio');
        $this->twilioPost($url, ['MessageSid' => 'SM3', 'MessageStatus' => 'delivered'])->assertOk();
        $this->twilioPost($url, ['MessageSid' => 'SM3', 'MessageStatus' => 'failed'])->assertOk();

        $this->assertSame('delivered', $m->fresh()->status);
        $this->assertSame(0, $this->refunds($m));
    }

    public function test_the_refund_switch_marks_failed_without_refunding(): void
    {
        \App\Models\Setting::setValue('sms.refund_on_failed_delivery', false);
        $m = $this->message('twilio', 'SM4');
        $this->twilioPost(route('webhooks.sms-delivery', 'twilio'), ['MessageSid' => 'SM4', 'MessageStatus' => 'failed'])->assertOk();

        $this->assertSame('failed', $m->fresh()->status);
        $this->assertSame(0, $this->refunds($m));
    }

    public function test_a_telnyx_finalized_report_is_verified_and_maps_the_documented_statuses(): void
    {
        $m = $this->message('telnyx', 'tx-1');
        $url = route('webhooks.sms-delivery', 'telnyx');
        $body = fn (string $event, string $status) => ['data' => ['event_type' => $event, 'payload' => ['id' => 'tx-1', 'to' => [['phone_number' => '+2348012345678', 'status' => $status]], 'errors' => []]]];

        $this->telnyxPost($url, $body('message.finalized', 'delivered'), false)->assertStatus(401);
        $this->telnyxPost($url, $body('message.finalized', 'delivered'), true, time() - 3600)->assertStatus(401);   // stale timestamp: replay window
        $this->telnyxPost($url, $body('message.sent', 'sent'))->assertOk();
        $this->telnyxPost($url, $body('message.finalized', 'delivery_unconfirmed'))->assertOk();
        $this->assertSame('sent', $m->fresh()->status, 'progress and unconfirmed are not final');

        $this->telnyxPost($url, $body('message.finalized', 'delivery_failed'))->assertOk();
        $this->assertSame('failed', $m->fresh()->status);
        $this->assertSame(1, $this->refunds($m));
    }

    public function test_a_telnyx_inbound_text_is_read_from_its_nested_payload(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        VirtualNumber::create(['user_id' => $user->id, 'provider' => 'telnyx', 'phone_number' => '+15005550006', 'sid' => 'PN9', 'status' => 'active', 'monthly_cost' => 1.0, 'monthly_retail' => 3.0]);
        $url = route('webhooks.sms-inbound', 'telnyx');
        $in = ['data' => ['event_type' => 'message.received', 'payload' => ['id' => 'in-1', 'from' => ['phone_number' => '+2348012345678'], 'to' => [['phone_number' => '+15005550006']], 'text' => 'hello there', 'media' => []]]];

        $this->telnyxPost($url, $in, false)->assertStatus(401);
        $this->telnyxPost($url, $in)->assertOk()->assertJsonMissing(['matched' => false]);
        Queue::assertPushed(\App\Jobs\RecordInboundMessageJob::class, fn ($j) => $j->fromNumber === '+2348012345678' && $j->body === 'hello there' && $j->providerRef === 'in-1');

        Queue::fake();
        $this->telnyxPost($url, ['data' => ['event_type' => 'message.finalized', 'payload' => []]])->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_sends_ask_the_provider_to_report_delivery_back_to_us(): void
    {
        config(['services.twilio.account_sid' => 'AC1', 'services.twilio.auth_token' => 'tok', 'services.telnyx.api_key' => 'KEY']);
        Http::fake(['*' => Http::response(['sid' => 'SMx', 'status' => 'queued', 'data' => ['id' => 'txid']], 200)]);

        app(TwilioService::class)->sendSms('+15005550006', '+2348012345678', 'hi');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'Messages.json') && ($r['StatusCallback'] ?? null) === route('webhooks.sms-delivery', 'twilio'));

        app(TelnyxService::class)->sendSms('+15005550006', '+2348012345678', 'hi');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages') && ($r['webhook_url'] ?? null) === route('webhooks.sms-delivery', 'telnyx'));
    }
}
