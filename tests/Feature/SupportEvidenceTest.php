<?php

namespace Tests\Feature;

use App\Livewire\SupportChat;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\Contracts\ChatModel;
use App\Support\SupportAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeChatModel;
use Tests\TestCase;

/**
 * NaaraCare evidence attachments (owner request). A customer can attach a
 * screenshot / photo / PDF; it is stored privately, the AI is shown it as a
 * vision/document block so it can diagnose from what it sees, and the file is
 * reachable only by the owner or ticket staff.
 */
class SupportEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attaching_evidence_stores_it_and_shows_it_to_the_model(): void
    {
        Storage::fake('local');
        $fake = new FakeChatModel([FakeChatModel::text('I can see the error in your screenshot — here is the fix.')]);
        $this->app->instance(ChatModel::class, $fake);

        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportChat::class)
            ->set('draft', 'My install failed, see attached')
            ->set('evidence', UploadedFile::fake()->image('error.png', 400, 300))
            ->call('send')
            ->assertSee('I can see the error');

        // A user message carrying the attachment was persisted…
        $msg = SupportMessage::where('role', 'user')->whereNotNull('attachment_path')->first();
        $this->assertNotNull($msg);
        $this->assertSame('image/png', $msg->attachment_mime);
        $this->assertSame('error.png', $msg->attachment_name);
        Storage::disk('local')->assertExists($msg->attachment_path);

        // …and the model was actually shown the image as a content block.
        $lastTurn = end($fake->calls[0]); // messages passed to reply(): last is the user turn
        $blocks = $lastTurn['content'];
        $this->assertIsArray($blocks);
        $types = array_column($blocks, 'type');
        $this->assertContains('image', $types, 'the evidence image must be sent to the model');
        $this->assertContains('text', $types);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $this->app->instance(ChatModel::class, new FakeChatModel([FakeChatModel::text('ok')]));
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportChat::class)
            ->set('evidence', UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'))
            ->call('send')
            ->assertHasErrors('evidence');

        $this->assertSame(0, SupportMessage::whereNotNull('attachment_path')->count());
    }

    public function test_evidence_is_served_to_the_owner_but_not_to_a_stranger(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $conversation = SupportConversation::create(['user_id' => $owner->id, 'status' => 'open', 'title' => 't']);
        Storage::disk('local')->put('support-evidence/x.png', 'PNGBYTES');
        $msg = $conversation->messages()->create([
            'role' => 'user', 'body' => '[Attached evidence]',
            'attachment_path' => 'support-evidence/x.png',
            'attachment_mime' => 'image/png', 'attachment_name' => 'x.png',
        ]);

        // ->fresh() hydrates is_active so the "paused account" guard doesn't trip.
        $this->actingAs($owner->fresh())->get(route('support.attachment', $msg->id))->assertOk();
        $this->actingAs(User::factory()->create()->fresh())->get(route('support.attachment', $msg->id))->assertForbidden();
    }

    public function test_the_composer_uses_the_in_page_recorder_not_a_native_audio_picker(): void
    {
        // BUILD-3 §3: the mic records in-page (getUserMedia/MediaRecorder) and
        // explains itself before the OS prompt — it is no longer a hidden
        // <input type=file accept="audio/*"> that opens the device picker.
        $html = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)
            ->html();

        $this->assertStringContainsString('voiceRecorder()', $html);
        $this->assertStringContainsString('needs microphone access', $html);
        $this->assertStringNotContainsString('accept="audio/*"', $html);
    }

    public function test_content_block_builder_only_accepts_images_and_pdfs(): void
    {
        $this->assertSame('image', SupportAttachment::toContentBlock('bytes', 'image/png')['type']);
        $this->assertSame('document', SupportAttachment::toContentBlock('bytes', 'application/pdf')['type']);
        $this->assertNull(SupportAttachment::toContentBlock('bytes', 'application/x-msdownload'));
        $this->assertNull(SupportAttachment::toContentBlock('', 'image/png'));
    }
}
