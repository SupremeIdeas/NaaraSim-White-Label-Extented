<?php

namespace App\Services\Platform;

use RuntimeException;

/** A platform-earnings ledger action could not proceed (e.g. an over-withdrawal). */
class PlatformEarningsException extends RuntimeException
{
}
