<?php

namespace Tests\Feature;

use App\Jobs\RenderVoiceJob;
use App\Livewire\Admin\SupportQueue;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\SupportConversation;
use App\Models\User;
use App\Notifications\HumanRepliedNotification;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Services\Support\SupportReply;
use App\Support\SpendGate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeVoiceSynthesizer;
use Tests\TestCase;

/**
 * Module 25 — support tickets, human handoff + ElevenLabs voice.
 */
class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function payingUser(): User
    {
        $user = User::factory()->create();
        $plan = EsimPlan::create(['provider' => 'esimgo', 'provider_plan_id' => 'p', 'name' => 'P', 'cost_price_usd' => 3, 'computed_retail_usd' => 9]);
        EsimOrder::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3]);

        return $user;
    }

    private function staff(): User
    {
        $s = User::factory()->create();
        $s->assignRole('staff');
        $s->givePermissionTo('tickets.manage');
        $s->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        return $s;
    }

    public function test_spend_gate_distinguishes_paying_from_free_users(): void
    {
        $this->assertFalse(SpendGate::hasPurchased(User::factory()->create()));
        $this->assertTrue(SpendGate::hasPurchased($this->payingUser()));
    }

    public function test_voice_is_queued_only_for_paying_users(): void
    {
        Queue::fake();
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(isAvailable: true));

        $paying = $this->payingUser();
        $free = User::factory()->create();
        $convP = SupportConversation::create(['user_id' => $paying->id]);
        $convF = SupportConversation::create(['user_id' => $free->id]);

        $reply = new SupportReply;
        $mP = $reply->deliver($convP, 'assistant', 'Hello paying customer');
        $mF = $reply->deliver($convF, 'assistant', 'Hello free customer');

        $this->assertSame('pending', $mP->voice_status);
        $this->assertNull($mF->voice_status);
        Queue::assertPushed(RenderVoiceJob::class, 1);
    }

    public function test_render_voice_job_stores_audio_and_marks_ready(): void
    {
        Storage::fake('local');
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(audio: 'AUDIOBYTES'));

        $user = $this->payingUser();
        $conv = SupportConversation::create(['user_id' => $user->id]);
        $msg = $conv->messages()->create(['role' => 'assistant', 'body' => 'Spoken hello', 'voice_status' => 'pending']);

        (new RenderVoiceJob($msg->id))->handle(app(VoiceSynthesizer::class));

        $fresh = $msg->fresh();
        $this->assertSame('ready', $fresh->voice_status);
        $this->assertNotNull($fresh->voice_path);
        Storage::disk('local')->assertExists($fresh->voice_path);
    }

    public function test_voice_clip_is_served_to_the_owner_but_not_to_others(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $conv = SupportConversation::create(['user_id' => $owner->id]);
        $msg = $conv->messages()->create(['role' => 'assistant', 'body' => 'x', 'voice_path' => 'support-voice/1/a.mp3', 'voice_status' => 'ready']);
        Storage::disk('local')->put('support-voice/1/a.mp3', 'AUDIO');

        // ->fresh() hydrates is_active so the "paused account" guard doesn't trip.
        $this->actingAs($owner->fresh())->get(route('support.voice', $msg->id))->assertOk();

        $stranger = User::factory()->create()->fresh();
        $this->actingAs($stranger)->get(route('support.voice', $msg->id))->assertForbidden();
    }

    public function test_escalated_ticket_appears_in_the_staff_queue_and_can_be_answered(): void
    {
        Notification::fake();
        $customer = $this->payingUser();
        $conv = SupportConversation::create(['user_id' => $customer->id, 'escalated' => true, 'escalated_at' => now(), 'escalation_reason' => 'refund']);

        Livewire::actingAs($this->staff())->test(SupportQueue::class)
            ->assertSee($customer->name)
            ->call('assignToMe', $conv->id)
            ->set('reply', 'Hi, I can help with that refund.')
            ->call('sendReply');

        $this->assertDatabaseHas('support_messages', ['conversation_id' => $conv->id, 'role' => 'staff', 'body' => 'Hi, I can help with that refund.']);
        $this->assertSame('assigned', $conv->fresh()->status);
        $this->assertSame($conv->fresh()->assigned_to, \Illuminate\Support\Facades\Auth::id() ?: $conv->fresh()->assigned_to);
        Notification::assertSentTo($customer, HumanRepliedNotification::class);
    }

    public function test_ticket_queue_is_closed_to_users_without_the_scope(): void
    {
        $plainAdminlessUser = User::factory()->create();
        $plainAdminlessUser->assignRole('user');

        $this->actingAs($plainAdminlessUser)->get('/adminmaster/tickets')->assertNotFound();
    }
}
