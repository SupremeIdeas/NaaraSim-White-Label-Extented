<?php

namespace App\Jobs;

use App\Models\OutboundMessage;
use App\Services\SMS\MessageSenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hands one already-charged SMS to its provider (money-safety rule 8: an external call is a queued job, never part of the web request). The wallet debit and the
 * queued row were written by MessageSenderService::send; this job only completes or refunds that one row. It runs exactly once: a provider call is never blindly
 * retried (rule 7), a row that is no longer queued is skipped, and a failure refunds in full and tells the sender.
 */
class SendOutboundSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public int $messageId)
    {
    }

    public function handle(MessageSenderService $sender): void
    {
        $sender->deliver($this->messageId);
    }

    /** The worker died or timed out around the provider call. We cannot tell whether it left, so nothing is refunded here: an admin alert, and the stale-queued sweep decides. */
    public function failed(?\Throwable $e): void
    {
        $msg = OutboundMessage::query()->find($this->messageId);
        if ($msg && $msg->status === 'queued') {
            AlertAdminJob::dispatch(code: 'sms_send_interrupted', message: "SMS {$msg->reference} was interrupted around the provider call; check whether it left before refunding.", context: ['message_id' => $msg->id]);
        }
    }
}
