<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when no eSIM provider could fulfil an order profitably. By the time
 * this is thrown the ProviderRouter has already refunded the user's wallet
 * and alerted admins (blueprint Section 6).
 */
class EsimProviderException extends RuntimeException
{
}
