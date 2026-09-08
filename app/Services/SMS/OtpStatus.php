<?php

namespace App\Services\SMS;

/**
 * Normalised OTP/rental order statuses. Each provider maps its own vocabulary
 * (5sim PENDING/RECEIVED/TIMEOUT/CANCELED/FINISHED, Getatext waiting/active…)
 * onto these so the router and jobs are provider-agnostic.
 */
final class OtpStatus
{
    public const PENDING = 'pending';   // bought, awaiting SMS

    public const RECEIVED = 'received'; // code arrived

    public const TIMEOUT = 'timeout';   // no SMS in window

    public const CANCELED = 'canceled'; // cancelled/banned

    public const FINISHED = 'finished'; // completed & closed
}
