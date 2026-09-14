<?php

namespace App\Jobs;

use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Transcribes a voicemail already stored by RecordVoicemailJob (Prompt 11),
 * reusing the existing speech-to-text capability (VoiceSynthesizer::transcribe,
 * ElevenLabs in production). A separate job from the download/store step so
 * the audio is playable immediately and transcription never blocks it.
 *
 * $tries = 1: never blind-retry a paid external call (money-safety rule 7) —
 * on failure the voicemail simply keeps its "tap to listen" placeholder body,
 * never a fabricated transcript.
 */
class TranscribeVoicemailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(VoiceSynthesizer $voice): void
    {
        $message = InboundMessage::find($this->messageId);
        if ($message === null || $message->voicemail_path === null) {
            return;
        }

        $disk = Storage::disk(MediaStorage::privateDisk());
        if (! $disk->exists($message->voicemail_path)) {
            return;
        }

        $transcript = $voice->transcribe($disk->get($message->voicemail_path), 'audio/mpeg');

        $body = $transcript !== null && trim($transcript) !== ''
            ? '🎙️ Voicemail ('.$message->voicemail_duration_seconds.'s): "'.trim($transcript).'"'
            : '🎙️ Voicemail ('.$message->voicemail_duration_seconds.'s) — could not be transcribed automatically. Tap play to listen.';

        $message->update(['body' => $body]);

        // Refresh the thread's preview text to the transcript — but ONLY if
        // this voicemail is still the thread's most recent message. A newer
        // message could have arrived while transcription was in flight; never
        // clobber its preview with a stale voicemail transcript.
        $thread = MessageThread::where('user_id', $message->user_id)
            ->where('counterpart_number', $message->from_number)
            ->first();
        $messageAt = $message->received_at ?? $message->created_at;
        if ($thread && $thread->last_at?->equalTo($messageAt)) {
            $thread->update(['last_body' => mb_substr($body, 0, 480)]);
        }
    }
}
