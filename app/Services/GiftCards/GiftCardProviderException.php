<?php

namespace App\Services\GiftCards;

use RuntimeException;

/** A gift-card provider could not fulfil an order (wallet is refunded by caller). */
class GiftCardProviderException extends RuntimeException {}
