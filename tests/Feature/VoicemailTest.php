<?php

namespace Tests\Feature;

use App\Jobs\RecordVoicemailJob;
use App\Jobs\TranscribeVoicemailJob;
use App\Models\CallForwardingRule;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Support\Contracts\VoiceSynthesizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeVoiceSynthesizer;
use Tests\TestCase;

/**
 * Voicemail + transcription (Prompt 11): an unanswered forwarded call records
 * a voicemail, stored as a normal InboundMessage — reusing the existing
 * Messages inbox with no separate UI — then transcribed via the existing
 * speech-to-text capability (VoiceSynthesizer).
 */
class VoicemailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
    }

    /** Twilio's X-Twilio-Signature over the URL + sorted POST params. */
    private function sign(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return base64_encode(hash_hmac('sha1', $data, 'secret_token', true));
    }

    private function rule(User $user): CallForwardingRule
    {
        return CallForwardingRule::create([
            'user_id' => $user->id, 'twilio_number' => '+15005550006',
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);
    }

    public function test_forwarded_call_twiml_falls_through_to_a_recording(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->rule($user);

        $url = route('webhooks.twilio.voice');
        $params = ['To' => '+15005550006', 'From' => '+15551112222', 'CallSid' => 'CA1', 'CallStatus' => 'ringing'];

        $res = $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params);

        $res->assertOk();
        $res->assertSee('<Record', false);
        $res->assertSee('action="'.route('webhooks.twilio.recording').'"', false);
    }

    public function test_the_recording_webhook_queues_the_voicemail_job_when_a_rule_exists(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->rule($user);

        $url = route('webhooks.twilio.recording');
        $params = [
            'To' => '+15005550006', 'From' => '+15551112222',
            'RecordingUrl' => 'https://api.twilio.com/recordings/RE123', 'RecordingSid' => 'RE123',
            'RecordingDuration' => '12',
        ];

        $res = $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params);

        $res->assertOk();
        Queue::assertPushed(RecordVoicemailJob::class, fn ($job) => $job->userId === $user->id
            && $job->recordingSid === 'RE123' && $job->durationSeconds === 12);
    }

    public function test_the_recording_webhook_rejects_an_invalid_signature(): void
    {
        $url = route('webhooks.twilio.recording');
        $params = ['To' => '+15005550006', 'RecordingSid' => 'RE123'];

        $this->withHeaders(['X-Twilio-Signature' => 'wrong'])->post($url, $params)
            ->assertStatus(403);
    }

    public function test_the_recording_webhook_with_no_active_rule_queues_nothing(): void
    {
        Queue::fake();
        $url = route('webhooks.twilio.recording');
        $params = ['To' => '+15005559999', 'From' => '+15551112222', 'RecordingUrl' => 'https://x/RE1', 'RecordingSid' => 'RE1'];

        $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params)
            ->assertOk();

        Queue::assertNotPushed(RecordVoicemailJob::class);
    }

    public function test_the_job_downloads_stores_and_creates_an_inbound_message(): void
    {
        Storage::fake('local');
        Queue::fake();
        Http::fake(['*/RE123.mp3' => Http::response('AUDIOBYTES', 200)]);
        $user = User::factory()->create();

        (new RecordVoicemailJob(
            userId: $user->id, virtualNumberId: null, fromNumber: '+15551112222',
            toNumber: '+15005550006', recordingUrl: 'https://api.twilio.com/recordings/RE123',
            recordingSid: 'RE123', durationSeconds: 12,
        ))->handle();

        $message = InboundMessage::where('provider_ref', 'voicemail:RE123')->first();
        $this->assertNotNull($message);
        $this->assertSame('+15551112222', $message->from_number);
        $this->assertSame(12, $message->voicemail_duration_seconds);
        $this->assertStringContainsString('Voicemail', $message->body);
        Storage::disk('local')->assertExists($message->voicemail_path);
        Queue::assertPushed(TranscribeVoicemailJob::class, fn ($job) => $job->messageId === $message->id);

        // Bumps the Messages inbox thread — no separate UI needed.
        $this->assertDatabaseHas('message_threads', [
            'user_id' => $user->id, 'counterpart_number' => '+15551112222', 'unread_count' => 1,
        ]);
    }

    public function test_the_job_is_idempotent_on_the_same_recording_sid(): void
    {
        Storage::fake('local');
        Queue::fake();
        Http::fake(['*/RE123.mp3' => Http::response('AUDIOBYTES', 200)]);
        $user = User::factory()->create();

        $job = fn () => (new RecordVoicemailJob(
            userId: $user->id, virtualNumberId: null, fromNumber: '+15551112222',
            toNumber: '+15005550006', recordingUrl: 'https://api.twilio.com/recordings/RE123',
            recordingSid: 'RE123', durationSeconds: 12,
        ))->handle();

        $job();
        $job();

        $this->assertSame(1, InboundMessage::where('provider_ref', 'voicemail:RE123')->count());
    }

    public function test_transcription_updates_the_message_body_and_thread_preview(): void
    {
        Storage::fake('local');
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(transcript: 'call me back please'));
        $user = User::factory()->create();
        Storage::disk('local')->put('voicemail/1/a.mp3', 'AUDIO');

        $message = InboundMessage::create([
            'user_id' => $user->id, 'from_number' => '+15551112222', 'body' => '🎙️ Voicemail (12s)',
            'voicemail_path' => 'voicemail/1/a.mp3', 'voicemail_duration_seconds' => 12,
            'provider' => 'twilio', 'provider_ref' => 'voicemail:RE123', 'received_at' => now(),
        ]);

        (new TranscribeVoicemailJob($message->id))->handle(app(VoiceSynthesizer::class));

        $message->refresh();
        $this->assertStringContainsString('call me back please', $message->body);

        $thread = MessageThread::where('user_id', $user->id)->where('counterpart_number', '+15551112222')->first();
        $this->assertStringContainsString('call me back please', $thread->last_body);
    }

    public function test_a_failed_transcription_leaves_an_honest_placeholder(): void
    {
        Storage::fake('local');
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(transcript: null));
        $user = User::factory()->create();
        Storage::disk('local')->put('voicemail/1/a.mp3', 'AUDIO');

        $message = InboundMessage::create([
            'user_id' => $user->id, 'from_number' => '+15551112222', 'body' => '🎙️ Voicemail (12s)',
            'voicemail_path' => 'voicemail/1/a.mp3', 'voicemail_duration_seconds' => 12,
            'provider' => 'twilio', 'provider_ref' => 'voicemail:RE123', 'received_at' => now(),
        ]);

        (new TranscribeVoicemailJob($message->id))->handle(app(VoiceSynthesizer::class));

        $this->assertStringContainsString('could not be transcribed', $message->fresh()->body);
    }

    public function test_transcription_never_overwrites_a_newer_messages_thread_preview(): void
    {
        Storage::fake('local');
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(transcript: 'old voicemail text'));
        $user = User::factory()->create();
        Storage::disk('local')->put('voicemail/1/a.mp3', 'AUDIO');

        $message = InboundMessage::create([
            'user_id' => $user->id, 'from_number' => '+15551112222', 'body' => '🎙️ Voicemail (12s)',
            'voicemail_path' => 'voicemail/1/a.mp3', 'voicemail_duration_seconds' => 12,
            'provider' => 'twilio', 'provider_ref' => 'voicemail:RE123', 'received_at' => now()->subMinute(),
        ]);
        // A newer SMS from the same counterpart arrives after the voicemail.
        InboundMessage::create([
            'user_id' => $user->id, 'from_number' => '+15551112222', 'body' => 'a newer text message',
            'provider' => 'twilio', 'provider_ref' => 'sms:1', 'received_at' => now(),
        ]);

        (new TranscribeVoicemailJob($message->id))->handle(app(VoiceSynthesizer::class));

        $thread = MessageThread::where('user_id', $user->id)->where('counterpart_number', '+15551112222')->first();
        $this->assertSame('a newer text message', $thread->last_body);
    }

    public function test_only_the_owner_can_stream_the_voicemail_audio(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $stranger = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Storage::disk('local')->put('voicemail/1/a.mp3', 'AUDIO');
        $message = InboundMessage::create([
            'user_id' => $owner->id, 'from_number' => '+15551112222', 'body' => 'x',
            'voicemail_path' => 'voicemail/1/a.mp3', 'provider' => 'twilio', 'provider_ref' => 'voicemail:RE1',
        ]);

        $this->actingAs($owner)->get(route('numbers.voicemail-audio', $message))->assertOk();
        $this->actingAs($stranger)->get(route('numbers.voicemail-audio', $message))->assertForbidden();
    }

    public function test_the_messages_inbox_renders_a_voicemail_as_audio_not_an_image(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $message = InboundMessage::create([
            'user_id' => $user->id, 'from_number' => '+15551112222', 'body' => '🎙️ Voicemail (12s)',
            'voicemail_path' => 'voicemail/1/a.mp3', 'provider' => 'twilio', 'provider_ref' => 'voicemail:RE1',
            'received_at' => now(),
        ]);
        $message->update(['attachment_url' => route('numbers.voicemail-audio', $message)]);

        Livewire::actingAs($user)->test(\App\Livewire\Messages::class, ['active' => '+15551112222'])
            ->assertSee('<audio', false)
            ->assertDontSee('<img src="'.$message->attachment_url.'"', false);
    }
}
