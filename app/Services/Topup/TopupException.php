<?php

namespace App\Services\Topup;

use RuntimeException;

/** A user-presentable top-up error (the message is safe to show). */
class TopupException extends RuntimeException {}
