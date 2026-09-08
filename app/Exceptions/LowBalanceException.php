<?php

namespace App\Exceptions;

/**
 * NaaraSim's prepaid wallet with a number provider is empty. The router skips
 * the provider and an AlertAdminJob fires so it can be topped up.
 */
class LowBalanceException extends SmsException
{
}
