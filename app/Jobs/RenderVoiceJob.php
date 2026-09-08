<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders a support reply to speech (Module 25) and attaches the audio to the
 * message. Queued (voice is not on the request path). Fails soft: on any error
 * the message just keeps its text (voice_status = failed) — the customer always
 * has the written reply regardless.
 *
 * $tries = 1: never blind-retry a paid external call (money-safety rule 7).
 */
class RenderVoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(VoiceSynthesizer $voice): void
    {
        $message = SupportMessage::find($this->messageId);
        if (! $message || $message->voice_status === 'ready') {
            return;
        }

        $audio = $voice->synthesize($this->plain($message->body));
        if ($audio === null) {
            $message->forceFill(['voice_status' => 'failed'])->save();

            return;
        }

        $path = 'support-voice/'.$message->conversation_id.'/'.Str::uuid()->toString().'.mp3';
        Storage::disk(MediaStorage::privateDisk())->put($path, $audio);

        $message->forceFill(['voice_path' => $path, 'voice_status' => 'ready'])->save();
    }

    /** Strip any leftover audio tags/markdown before speaking. */
    private function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
    }
}
