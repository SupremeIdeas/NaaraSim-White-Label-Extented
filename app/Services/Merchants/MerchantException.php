<?php

namespace App\Services\Merchants;

use RuntimeException;

/** A merchant action could not proceed (programme off, not KYB-verified, etc.). */
class MerchantException extends RuntimeException
{
}
