<?php

namespace App\Jobs;

use App\Services\SMS\Numbers\TelnyxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Puts a freshly bought Telnyx number on the Messaging Profile so it can send and receive SMS. Queued with backoff because a new order can be pending for a
 * moment; safe to run more than once. Never touches money: the number is already bought, this only finishes its setup. A permanent failure is logged and shows
 * up in `naara:verify messages` (a Telnyx line with no profile assigned is flagged there).
 */
class AssignTelnyxMessagingProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public readonly string $e164)
    {
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800];
    }

    public function handle(TelnyxService $telnyx): void
    {
        if ($telnyx->assignMessagingProfile($this->e164)) {
            return;
        }
        throw new \RuntimeException('Telnyx messaging profile not assigned yet for '.$this->e164);   // retried with backoff
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Telnyx number could not be assigned to the messaging profile; SMS on it will not work until it is.', ['number' => $this->e164, 'error' => $e->getMessage()]);
    }
}
