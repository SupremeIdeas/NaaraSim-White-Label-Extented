<?php

namespace App\Exceptions;

/**
 * Getatext rejected the API key ("Wrong Api Key or User is restricted").
 * Alert admin — check GETATEXT_API_KEY.
 */
class GetatextAuthException extends SmsException
{
}
