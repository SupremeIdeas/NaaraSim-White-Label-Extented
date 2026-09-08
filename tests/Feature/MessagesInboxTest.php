<?php

namespace Tests\Feature;

use App\Livewire\Messages;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\VirtualNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Numbers overhaul §1 — the conversation inbox: inbound SMS webhook (verify →
 * queue → record), the MessageThread summary, and the two-pane Messages inbox.
 */
class MessagesInboxTest extends TestCase
{
    use RefreshDatabase;

    private function line(User $user, string $number = '+15551230000'): VirtualNumber
    {
        return VirtualNumber::create([
            'user_id' => $user->id, 'phone_number' => $number, 'status' => 'active',
            'provider' => 'twilio', 'type' => 'permanent',
        ]);
    }

    public function test_inbound_webhook_rejects_a_bad_token(): void
    {
        config(['services.twilio.webhook_token' => 'secret']);
        $this->postJson('/webhooks/sms-inbound/twilio', ['To' => '+15551230000', 'From' => '+15559999999', 'Body' => 'hi'])
            ->assertStatus(401);
    }

    public function test_inbound_webhook_queues_a_record_job_for_a_matching_line(): void
    {
        Queue::fake();
        config(['services.twilio.webhook_token' => 'secret']);
        $user = User::factory()->create();
        $this->line($user);

        $this->postJson('/webhooks/sms-inbound/twilio?token=secret', [
            'To' => '+15551230000', 'From' => '+1 (555) 999-9999', 'Body' => 'hello', 'MessageSid' => 'SM1',
        ])->assertOk();

        Queue::assertPushed(\App\Jobs\RecordInboundMessageJob::class);
    }

    public function test_unknown_number_is_a_noop_not_an_error(): void
    {
        config(['services.twilio.webhook_token' => 'secret']);
        $this->postJson('/webhooks/sms-inbound/twilio?token=secret', [
            'To' => '+10000000000', 'From' => '+15559999999', 'Body' => 'x',
        ])->assertOk()->assertJson(['matched' => false]);
    }

    public function test_recording_creates_a_thread_with_unread_and_is_idempotent(): void
    {
        $user = $this->userWithLine();
        $job = new \App\Jobs\RecordInboundMessageJob(
            userId: $user->id, virtualNumberId: null, fromNumber: '+15559999999',
            body: 'hey there', attachmentUrl: null, provider: 'twilio', providerRef: 'SM1',
        );
        $job->handle();
        $job->handle(); // re-delivery → no duplicate

        $this->assertSame(1, InboundMessage::count());
        $thread = MessageThread::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('+15559999999', $thread->counterpart_number);
        $this->assertSame(1, $thread->unread_count);
        $this->assertSame('in', $thread->last_direction);
    }

    public function test_an_outbound_reply_clears_unread_and_updates_the_thread(): void
    {
        $user = User::factory()->create();
        $line = $this->line($user);
        (new \App\Jobs\RecordInboundMessageJob($user->id, null, '+15559999999', 'hi', null, 'twilio', 'SM1'))->handle();

        OutboundMessage::create([
            'user_id' => $user->id, 'virtual_number_id' => $line->id,
            'to_number' => '+15559999999', 'body' => 'reply', 'status' => 'sent',
            'provider' => 'twilio', 'reference' => 'out-1',
        ]);

        $thread = MessageThread::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(0, $thread->unread_count);   // replying clears unread
        $this->assertSame('out', $thread->last_direction);
        $this->assertSame('reply', $thread->last_body);
    }

    public function test_inbox_lists_threads_and_opening_marks_read(): void
    {
        $user = $this->userWithLine();
        (new \App\Jobs\RecordInboundMessageJob($user->id, null, '+15559999999', 'hi', null, 'twilio', 'SM1'))->handle();

        Livewire::actingAs($user)->test(Messages::class)
            ->assertSee('+15559999999')
            ->call('openThread', '+15559999999')
            ->assertSet('active', '+15559999999');

        $this->assertSame(0, MessageThread::where('user_id', $user->id)->first()->unread_count);
        $this->assertNotNull(InboundMessage::first()->read_at);
    }

    private function userWithLine(): User
    {
        $user = User::factory()->create();
        $this->line($user);

        return $user;
    }
}
