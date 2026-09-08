<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a support message's evidence attachment (owner request) from the
 * PRIVATE disk. Same access rule as the voice clip: served ONLY to the customer
 * who owns the conversation, or to a staff member who may work tickets — never
 * publicly, and never to another customer.
 */
class SupportAttachmentController extends Controller
{
    public function __invoke(SupportMessage $message): StreamedResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $conversation = $message->conversation;
        $isOwner = $conversation && $conversation->user_id === $user->id;
        $isAgent = $user->hasAnyRole(['super_admin', 'admin']) || $user->can('tickets.manage');

        abort_unless($isOwner || $isAgent, 403);
        abort_if(! $message->attachment_path, 404);

        $disk = Storage::disk(MediaStorage::privateDisk());
        abort_unless($disk->exists($message->attachment_path), 404);

        return $disk->response(
            $message->attachment_path,
            $message->attachment_name ?: null,
            ['Content-Type' => $message->attachment_mime ?: 'application/octet-stream'],
        );
    }
}
