<?php

namespace App\Livewire;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\AudioTranscoder;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Services\Support\NaaraCareAgent;
use App\Services\Support\SupportReply;
use App\Support\MediaStorage;
use App\Support\SupportAttachment;
use App\Support\SupportSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * NaaraCare chat (Modules 24–25). A signed-in customer chats with the AI agent
 * (which sees only their own data) or, once escalated, with a human staff member
 * in the same thread. Paying customers also receive spoken (ElevenLabs) replies,
 * and can send voice notes. Rate-limited; fails soft.
 */
#[Layout('components.layouts.customer')]
class SupportChat extends Component
{
    use WithFileUploads;

    public ?SupportConversation $conversation = null;

    public string $draft = '';

    public $voiceNote = null; // uploaded audio

    public $evidence = null; // uploaded screenshot / photo / PDF

    /** @var array<int, array<string, mixed>> */
    public array $messages = [];

    // Nia 3-phase human-conversation pacing (BUILD-3 §5). The reply is persisted
    // synchronously by the server; the CLIENT reveals the newest one (id in
    // $streamMessageId) with a reading delay → typing indicator → char-by-char
    // stream, and queues any that pile up. These properties back that behaviour.
    public bool $isNiaTyping = false;

    /** @var array<int, mixed> */
    public array $messageQueue = [];

    public string $displayedText = '';

    /** The newest assistant message the client should animate in (null = none). */
    public ?int $streamMessageId = null;

    public function mount(): void
    {
        // Resume the customer's most recent still-active thread (including one a
        // human has taken over); only start a fresh one when nothing is open.
        $this->conversation = SupportConversation::where('user_id', Auth::id())
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('updated_at')
            ->first()
            ?? SupportConversation::create([
                'user_id' => Auth::id(),
                'status' => 'open',
                'title' => 'Support chat',
            ]);

        $this->loadMessages();
        $this->applyWarmContext();
    }

    /**
     * Warm hand-off from the Wizard (roadmap §10). When a user taps "talk to
     * NaaraCare" mid-flow we arrive with ?from=wizard&topic=<model>; on a fresh
     * thread we pre-fill (never auto-send) a friendly starter so the agent has
     * context the moment the user hits send. Only the public Model is passed —
     * never a supplier. Any other topic falls back to a generic opener.
     */
    private function applyWarmContext(): void
    {
        if ($this->messages !== [] || request()->query('from') !== 'wizard') {
            return;
        }
        $starters = [
            'naara_line' => 'I was setting up a permanent number (Naara Line) in the Helper and need a hand.',
            'naara_verify' => 'I was getting a verification code in the Helper and need a hand.',
            'naara_rent' => 'I was renting a number in the Helper and need a hand.',
            'naara_data' => 'I was getting eSIM data in the Helper and need a hand.',
        ];
        $topic = (string) request()->query('topic', '');
        $this->draft = $starters[$topic] ?? 'I was using the NaaraSim Helper and need a hand with my order.';
    }

    private function loadMessages(): void
    {
        $this->messages = $this->conversation->messages()
            ->orderBy('id')->get()
            ->map(fn (SupportMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'nav' => $m->meta['nav'] ?? null,
                'voice' => $m->voice_status === 'ready' ? route('support.voice', $m->id) : null,
                'voice_pending' => $m->voice_status === 'pending',
                'attachment' => $m->attachment_path ? route('support.attachment', $m->id) : null,
                'attachment_name' => $m->attachment_name,
                'attachment_image' => SupportAttachment::isImage($m->attachment_mime),
            ])->all();
    }

    /** Whether a human has taken over this thread (AI stops auto-replying). */
    private function handledByHuman(): bool
    {
        return ! is_null($this->conversation->assigned_to)
            || in_array($this->conversation->status, ['assigned'], true);
    }

    public function send(NaaraCareAgent $agent): void
    {
        $text = trim($this->draft);
        $hasEvidence = $this->evidence !== null;

        // Need either words or a file — but not nothing.
        if ($text === '' && ! $hasEvidence) {
            return;
        }
        if ($hasEvidence) {
            $this->validate(['evidence' => SupportAttachment::uploadRules()]);
        }
        if (! $this->throttleOk()) {
            return;
        }

        $attachmentBlock = null;
        $attributes = ['role' => 'user', 'body' => $text ?: '[Attached evidence]'];

        if ($hasEvidence) {
            $bytes = file_get_contents($this->evidence->getRealPath());
            $mime = $this->evidence->getMimeType();
            $path = 'support-evidence/'.$this->conversation->id.'/'.Str::uuid()->toString().'.'.$this->evidence->extension();
            Storage::disk(MediaStorage::privateDisk())->put($path, $bytes);

            $attributes += [
                'attachment_path' => $path,
                'attachment_mime' => $mime,
                'attachment_name' => Str::limit($this->evidence->getClientOriginalName(), 120, ''),
            ];
            // Build the content block for the model to SEE the evidence this turn.
            $attachmentBlock = SupportAttachment::toContentBlock($bytes, $mime);
        }

        $this->conversation->messages()->create($attributes);
        $this->draft = '';
        $this->evidence = null;
        $this->loadMessages();

        $this->respondTo($agent, $text ?: 'I have attached a file as evidence — please take a look and help.', $attachmentBlock);
    }

    /**
     * Send a recorded/uploaded voice note. Stored privately; transcribed
     * best-effort so the AI can read it; the audio stays attached for staff.
     */
    public function sendVoice(NaaraCareAgent $agent, VoiceSynthesizer $voice, AudioTranscoder $transcoder): void
    {
        $this->validate([
            // Broadened past the originally-tested formats (Marketing/Chat
            // blueprint Phase A) — real Android/iOS devices and browsers
            // commonly produce audio/3gpp, audio/amr, audio/aac, and a
            // video/webm container wrapping an audio-only recording, none
            // of which the narrower original list covered.
            'voiceNote' => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/aac,audio/3gpp,audio/amr,video/webm', 'max:10240'],
        ]);
        if (! $this->throttleOk()) {
            return;
        }

        $rawBytes = file_get_contents($this->voiceNote->getRealPath());
        $rawMime = $this->voiceNote->getMimeType();
        // Normalize to one canonical format before storage/AI processing —
        // a device format nobody's tested against yet degrades gracefully
        // (ffmpeg unavailable = stored/transcribed as-is) instead of ever
        // silently failing the upload again the next time a new phone model
        // ships a MIME type this list doesn't cover.
        [$bytes, $mime] = $transcoder->toCanonical($rawBytes, $rawMime, $this->voiceNote->extension());
        $extension = $mime === 'audio/mpeg' ? $transcoder->canonicalExtension() : $this->voiceNote->extension();
        $path = 'support-voice/'.$this->conversation->id.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk(MediaStorage::privateDisk())->put($path, $bytes);

        $transcript = $voice->transcribe($bytes, $mime);

        $message = $this->conversation->messages()->create([
            'role' => 'user',
            'body' => $transcript ?: '[Voice note]',
            'voice_path' => $path,
            'voice_status' => 'ready',
        ]);

        $this->voiceNote = null;
        $this->loadMessages();

        // Only let the AI answer if we could read the note and no human owns it.
        if ($transcript) {
            $this->respondTo($agent, $transcript);
        }
    }

    private function respondTo(NaaraCareAgent $agent, string $text, ?array $attachment = null): void
    {
        if ($this->handledByHuman()) {
            return; // a human will reply
        }

        if (! $agent->available()) {
            $msg = $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => 'Our AI assistant is not available right now. You can reach us on WhatsApp or by email from the Help menu, and a human will get back to you.',
            ]);
            $this->streamMessageId = $msg->id;
            $this->loadMessages();

            return;
        }

        try {
            $result = $agent->respond(Auth::user(), $this->conversation->fresh(), $text, $attachment);
            $msg = app(SupportReply::class)->deliver(
                $this->conversation->fresh(),
                'assistant',
                $result['reply'],
                $result['nav'] ? ['nav' => $result['nav']] : null,
            );
            $this->streamMessageId = $msg->id;
        } catch (\Throwable $e) {
            report($e);
            $msg = $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => "Sorry — I hit a snag answering that. If it's urgent, reach us on WhatsApp or email from the Help menu and a human will help.",
            ]);
            $this->streamMessageId = $msg->id;
        }

        $this->loadMessages();
    }

    private function throttleOk(): bool
    {
        $key = 'support-chat:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('draft', 'You are sending messages very fast — please wait a moment.');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    public function render()
    {
        return view('livewire.support-chat', [
            'agentName' => SupportSettings::name(),
            'humanHandling' => $this->handledByHuman(),
        ]);
    }
}
