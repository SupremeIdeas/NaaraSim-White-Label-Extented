<?php

namespace App\Services\GiftCards;

use RuntimeException;

/** A user-facing gift-card error (fraud gate, validation, insufficient funds). */
class GiftCardException extends RuntimeException {}
