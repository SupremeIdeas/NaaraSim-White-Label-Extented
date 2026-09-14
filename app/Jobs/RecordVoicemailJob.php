<?php

namespace App\Jobs;

use App\Models\InboundMessage;
use App\Models\User;
use App\Notifications\InboundVoicemailNotification;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads a completed voicemail recording and stores it as a normal
 * InboundMessage (Prompt 11) — reuses the existing Messages inbox, no
 * separate UI. Idempotent per RecordingSid so a re-delivered webhook can't
 * create a duplicate voicemail. The audio is downloaded once here (Twilio
 * requires authenticated access) and stored on the PRIVATE disk, never local
 * disk, never a raw Twilio URL handed to the browser — served back through
 * an owner-scoped streaming controller (VoicemailAudioController).
 */
class RecordVoicemailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public ?int $virtualNumberId,
        public string $fromNumber,
        public string $toNumber,
        public string $recordingUrl,
        public string $recordingSid,
        public int $durationSeconds,
    ) {}

    public function handle(): void
    {
        $ref = 'voicemail:'.$this->recordingSid;
        if (InboundMessage::where('provider', 'twilio')->where('provider_ref', $ref)->exists()) {
            return;
        }

        $audio = $this->download();
        if ($audio === null) {
            return; // best-effort — a failed download just means no voicemail recorded
        }

        $path = 'voicemail/'.$this->userId.'/'.Str::uuid()->toString().'.mp3';
        Storage::disk(MediaStorage::privateDisk())->put($path, $audio);

        $message = InboundMessage::create([
            'user_id' => $this->userId,
            'virtual_number_id' => $this->virtualNumberId,
            'from_number' => $this->fromNumber,
            'body' => '🎙️ Voicemail ('.$this->durationSeconds.'s)',
            'voicemail_path' => $path,
            'voicemail_duration_seconds' => $this->durationSeconds,
            'provider' => 'twilio',
            'provider_ref' => $ref,
            'received_at' => now(),
        ]);
        $message->update(['attachment_url' => route('numbers.voicemail-audio', $message)]);

        try {
            User::find($this->userId)?->notify(new InboundVoicemailNotification($message));
        } catch (\Throwable) {
            // best-effort — a notification failure must not fail recording
        }

        TranscribeVoicemailJob::dispatch($message->id);
    }

    /** Twilio recording media requires account auth to fetch. */
    private function download(): ?string
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if ($sid === '' || $token === '') {
            return null;
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->connectTimeout(3)->timeout(30)
                ->get($this->recordingUrl.'.mp3');

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
