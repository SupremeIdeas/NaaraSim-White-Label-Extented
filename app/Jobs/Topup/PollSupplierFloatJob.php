<?php

namespace App\Jobs\Topup;

use App\Services\Topup\TopupOps;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads ONE supplier's balance in the background (the scheduler queues one of these per supplier every five minutes, `topup:run float`). One slow or refused
 * supplier can no longer hold up the others or the scheduler, a failure is recorded on the supplier (`last_error`) for the admin to see, and the job never
 * throws into the queue. Unique per supplier so overlapping runs cannot pile up.
 */
class PollSupplierFloatJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 240;

    public function __construct(public string $code)
    {
    }

    public function uniqueId(): string
    {
        return 'topup-float-'.$this->code;
    }

    public function backoff(): array
    {
        return [20];
    }

    public function handle(TopupOps $ops): void
    {
        $ops->pollOne($this->code);
    }
}
