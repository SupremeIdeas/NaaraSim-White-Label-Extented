<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a support message's voice clip (Module 25) from the PRIVATE disk. The
 * only way to reach the audio: it is served solely to the customer who owns the
 * conversation, or to a staff member who may work tickets — never publicly.
 */
class SupportVoiceController extends Controller
{
    public function __invoke(SupportMessage $message): StreamedResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $conversation = $message->conversation;
        $isOwner = $conversation && $conversation->user_id === $user->id;
        $isAgent = $user->hasAnyRole(['super_admin', 'admin']) || $user->can('tickets.manage');

        abort_unless($isOwner || $isAgent, 403);
        abort_if(! $message->voice_path, 404);

        $disk = Storage::disk(MediaStorage::privateDisk());
        abort_unless($disk->exists($message->voice_path), 404);

        return $disk->response($message->voice_path, null, ['Content-Type' => 'audio/mpeg']);
    }
}
