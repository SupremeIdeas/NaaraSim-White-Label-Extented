<?php

namespace App\Support;

use App\Models\PayoutRequest;

/**
 * The ONE mapping from a payout's (status, review_state, hold_reason) to what the
 * user is told (Addendum C §5-D) — used by the withdraw page, the payout dashboard,
 * emails and the API. Never exposes a rule name, a score or a provider cost.
 */
class PayoutStatusText
{
    /** Translation key under `payouts.status.*`. */
    public static function key(PayoutRequest $r): string
    {
        return match ($r->status) {
            PayoutRequest::PENDING => match ($r->review_state) {
                PayoutRequest::REVIEW_DEFERRED => in_array($r->hold_reason, ['float_short', 'provider_unhealthy', 'provider_unavailable'], true) ? 'queued' : 'security_hold',
                PayoutRequest::REVIEW_MANUAL => 'extra_check',
                default => 'being_checked',
            },
            PayoutRequest::AWAITING_FUNDS => 'queued',
            PayoutRequest::APPROVED, PayoutRequest::PROCESSING => 'sent',
            PayoutRequest::PAID => 'paid',
            PayoutRequest::RETURNED => 'bounced',
            default => 'returned', // failed | reversed
        };
    }

    public static function text(PayoutRequest $r): string
    {
        return (string) __('payouts.status.'.self::key($r), [
            'time' => $r->next_check_at?->translatedFormat('M j, H:i') ?? '',
            'provider' => ucfirst((string) $r->provider),
            'days' => self::days($r->provider),
            'reason' => self::safeReason($r),
        ]);
    }

    /** Tone for the badge: success | warn | danger | neutral. */
    public static function tone(PayoutRequest $r): string
    {
        return match (self::key($r)) {
            'paid' => 'success',
            'sent', 'security_hold', 'queued', 'extra_check' => 'warn',
            'returned', 'bounced' => 'danger',
            default => 'neutral',
        };
    }

    private static function days(?string $provider): int
    {
        return in_array($provider, ['paystack', 'flutterwave'], true) ? 1 : 5;
    }

    /** A failure reason is only shown if it is already a user-safe sentence; otherwise a generic one. */
    private static function safeReason(PayoutRequest $r): string
    {
        $reason = trim((string) $r->failure_reason);

        return $reason !== '' && ! preg_match('/provider|http|exception|token|secret|\d{3}\b/i', $reason)
            ? $reason
            : (string) __('payouts.generic_reason');
    }
}
