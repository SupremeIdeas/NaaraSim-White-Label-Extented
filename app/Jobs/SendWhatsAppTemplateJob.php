<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppCloudClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deliver one WhatsApp Autopilot template message out-of-band (BUILD-4 §7 —
 * every external API call is a queued job, never synchronous in the request
 * cycle). This is a NOTIFICATION, not a money movement, so a few gentle retries
 * are fine; the client itself already fails safe (returns null, never throws).
 *
 * @param  list<array<string,mixed>>  $components
 */
class SendWhatsAppTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $toE164,
        public string $template,
        public ?string $lang = null,
        public array $components = [],
    ) {}

    public function handle(WhatsAppCloudClient $client): void
    {
        $client->sendTemplate($this->toE164, $this->template, $this->lang, $this->components);
    }
}
