<?php

namespace App\Exceptions;

/**
 * 5sim returned an error payload. Where the error is a known stock/maintenance
 * condition the service translates it to the shared OutOfStock/Maintenance
 * exceptions so the router can fall back in-lane.
 */
class FiveSimException extends SmsException
{
}
