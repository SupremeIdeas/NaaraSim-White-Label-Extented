<?php

namespace App\Exceptions;

/**
 * A provider is temporarily under maintenance. The router falls back within
 * the same lane.
 */
class MaintenanceException extends SmsException
{
}
