<?php

namespace App\Services\Payouts;

use RuntimeException;

/** A payout could not be created or sent (validation / no provider). */
class PayoutException extends RuntimeException
{
}
