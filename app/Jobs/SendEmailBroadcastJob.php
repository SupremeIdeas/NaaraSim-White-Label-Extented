<?php

namespace App\Jobs;

use App\Models\EmailBroadcast;
use App\Notifications\BroadcastEmailNotification;
use App\Support\BroadcastAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Sends an admin email broadcast in batches (Email Studio §3.2). Never a single
 * giant synchronous loop: recipients are chunked and each chunk's per-user
 * notification is itself queued, so the mail server is never hammered. Idempotent
 * on status — a re-run of an already-sent broadcast is a no-op.
 */
class SendEmailBroadcastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // never blind-retry a mass send

    public function __construct(public int $broadcastId) {}

    public function handle(): void
    {
        $broadcast = EmailBroadcast::find($this->broadcastId);
        if (! $broadcast || $broadcast->status === 'sent') {
            return;
        }

        $note = new BroadcastEmailNotification($broadcast->subject, $broadcast->body_html);

        BroadcastAudience::query($broadcast->audience_type, $broadcast->audience_value)
            ->select('users.*')
            ->chunkById(100, function ($users) use ($note) {
                Notification::send($users, $note); // each is ShouldQueue -> queued per user
            });

        $broadcast->update(['status' => 'sent']);
    }
}
