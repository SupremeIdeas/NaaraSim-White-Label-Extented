<?php

namespace App\Services\Payments;

use RuntimeException;

/** A refund could not be completed; the message is safe to show an admin. */
class RefundException extends RuntimeException {}
