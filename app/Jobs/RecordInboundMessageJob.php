<?php

namespace App\Jobs;

use App\Models\InboundMessage;
use App\Models\User;
use App\Notifications\InboundSmsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Records an inbound SMS off the webhook request cycle (Numbers overhaul §1).
 * Idempotent per (provider, provider_ref) so a re-delivered webhook never creates
 * a duplicate. Creating the InboundMessage bumps the MessageThread summary (via
 * the model observer); this job then notifies the recipient.
 */
class RecordInboundMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public ?int $virtualNumberId,
        public string $fromNumber,
        public ?string $body,
        public ?string $attachmentUrl,
        public string $provider,
        public ?string $providerRef,
    ) {}

    public function handle(): void
    {
        // Idempotency: a stable ref dedupes; a null ref falls back to a
        // content+time key so a retried webhook without an id can't double-insert.
        $ref = $this->providerRef ?: 'auto:'.substr(sha1($this->userId.$this->fromNumber.($this->body ?? '')), 0, 24);

        $existing = InboundMessage::where('provider', $this->provider)->where('provider_ref', $ref)->first();
        if ($existing) {
            return;
        }

        $message = InboundMessage::create([
            'user_id' => $this->userId,
            'virtual_number_id' => $this->virtualNumberId,
            'from_number' => $this->fromNumber,
            'body' => $this->body,
            'attachment_url' => $this->attachmentUrl,
            'provider' => $this->provider,
            'provider_ref' => $ref,
            'received_at' => now(),
        ]);

        try {
            User::find($this->userId)?->notify(new InboundSmsNotification($message));
        } catch (\Throwable) {
            // best-effort — a notification failure must not fail message recording
        }
    }
}
