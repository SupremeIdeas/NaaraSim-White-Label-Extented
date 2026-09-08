<?php

namespace App\Exceptions;

/**
 * A provider has no stock for the requested country+service. The router tries
 * the next provider in the SAME lane (never crosses lanes).
 */
class OutOfStockException extends SmsException
{
}
