<?php

namespace App\Services\Crypto\Support;

/**
 * The deposit state machine (blueprint section 5). ONE whitelist decides every transition; anything else throws, so no code path can skip a check or
 * reopen a finished deposit by accident. `credited`, `rejected` and `reorged` are terminal: an admin "reopen" is a separate audited action that writes
 * a new event, it never edits history.
 */
enum DepositStatus: string
{
    case Detected = 'detected';
    case Confirming = 'confirming';
    case Verified = 'verified';
    case CreditPending = 'credit_pending';
    case Credited = 'credited';
    case Unclaimed = 'unclaimed';
    case HeldReview = 'held_review';
    case PausedHold = 'paused_hold';
    case Rejected = 'rejected';
    case Reorged = 'reorged';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Detected => [self::Confirming, self::Verified, self::Reorged, self::Rejected, self::PausedHold],
            self::Confirming => [self::Confirming, self::Verified, self::Reorged, self::Rejected, self::HeldReview, self::PausedHold],
            self::Verified => [self::CreditPending, self::Unclaimed, self::HeldReview, self::PausedHold, self::Rejected],
            self::CreditPending => [self::Credited, self::HeldReview],
            self::Unclaimed => [self::Verified, self::CreditPending, self::HeldReview, self::Rejected, self::PausedHold],
            self::HeldReview => [self::Verified, self::CreditPending, self::Rejected, self::PausedHold],
            self::PausedHold => [self::Confirming, self::Verified, self::CreditPending],
            self::Credited, self::Rejected, self::Reorged => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function isTerminal(): bool
    {
        return $this->next() === [];
    }

    /** From here on the attributed user can no longer change (invariant I3). */
    public function freezesAttribution(): bool
    {
        return in_array($this, [self::CreditPending, self::Credited], true);
    }
}
