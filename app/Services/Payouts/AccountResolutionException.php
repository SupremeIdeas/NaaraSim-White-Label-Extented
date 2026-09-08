<?php

namespace App\Services\Payouts;

use RuntimeException;

/**
 * Thrown when a payout account can't be confirmed with the PSP (unsupported
 * country, or the account name couldn't be resolved). Carries a user-safe
 * message — never a provider detail.
 */
class AccountResolutionException extends RuntimeException
{
}
