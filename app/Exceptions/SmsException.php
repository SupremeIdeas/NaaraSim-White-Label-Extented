<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base exception for the number/SMS layer. Thrown to the caller when a whole
 * lane is exhausted (after the SmsNumberRouter has refunded + alerted).
 */
class SmsException extends RuntimeException
{
}
