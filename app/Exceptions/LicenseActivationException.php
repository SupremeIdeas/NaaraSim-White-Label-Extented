<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a deployed white-label fork tries to exchange a license key for an
 * API token and the key is not currently usable (unknown, revoked, or on a
 * non-active instance). Carries a machine `reason` for server-side logging while
 * the message stays deliberately generic — the public activate endpoint must not
 * leak WHICH of those a probing caller hit (an existing-but-revoked key and a
 * key that was never issued look identical from outside).
 */
class LicenseActivationException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Invalid or inactive license key.');
    }
}
