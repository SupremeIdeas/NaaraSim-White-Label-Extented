<?php

namespace App\Http\Controllers;

use App\Models\InboundMessage;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a voicemail's audio (Prompt 11) from the PRIVATE disk. Owner-only —
 * a voicemail is personal, unlike a shared support ticket — mirrors
 * SupportVoiceController's streaming pattern.
 */
class VoicemailAudioController extends Controller
{
    public function __invoke(InboundMessage $message): StreamedResponse
    {
        abort_unless($message->user_id === request()->user()?->id, 403);
        abort_if(! $message->voicemail_path, 404);

        $disk = Storage::disk(MediaStorage::privateDisk());
        abort_unless($disk->exists($message->voicemail_path), 404);

        return $disk->response($message->voicemail_path, null, ['Content-Type' => 'audio/mpeg']);
    }
}
