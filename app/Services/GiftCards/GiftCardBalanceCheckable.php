<?php

namespace App\Services\GiftCards;

/**
 * Optional capability: the provider lets us check an ALREADY-ISSUED card's
 * remaining balance (Tillo's documented Balance Check endpoint). Reloadly and
 * Zendit gift cards are single-use, full-value redemption codes — neither
 * provider exposes a post-issuance balance lookup, so neither implements
 * this. Bitrefill's catalogue is likewise full-value redemption codes for the
 * products we sync, so it doesn't implement this either.
 *
 * Drives the "Check balance" action on the receipt / My Gift Cards page —
 * shown ONLY for orders whose provider implements this, never fabricated for
 * a card type that doesn't actually support it.
 */
interface GiftCardBalanceCheckable
{
    /**
     * @return array{balance: float, currency: string, checked_at: string}
     */
    public function checkBalance(array $receipt): array;
}
