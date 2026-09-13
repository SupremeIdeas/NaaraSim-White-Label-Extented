<?php

namespace App\Services\SMS;

/**
 * Port-in eligibility (Prompt 11). Two honest gates, cheapest first:
 *   1. The audit boundary — only US/Canada (+1) numbers are portable into our
 *      providers at all; anything else is refused up front.
 *   2. A real provider PROBE (Twilio Portability API) — we only tell a user
 *      "yes, bring it in" when the carrier confirms the specific number is
 *      portable. If we can't prove it (probe says no, or can't run), the user
 *      gets an honest "sorry" — never a promise we can't keep. Numbers our
 *      providers DO support still sail straight through.
 */
class PortabilityChecker
{
    /**
     * @return array{eligible: bool, pin_required: bool, reason: ?string}
     */
    public function checkPortIn(string $number): array
    {
        $digits = (string) preg_replace('/\D/', '', $number);

        // Gate 1 — US/Canada only (audit finding).
        if (! str_starts_with('+'.$digits, '+1') || strlen($digits) !== 11) {
            return [
                'eligible' => false,
                'pin_required' => true,
                'reason' => 'Right now we can only bring in US and Canada numbers. We\'re sorry — yours isn\'t eligible yet.',
            ];
        }

        // Gate 2 — real carrier probe via the primary port-in provider (Twilio).
        $probe = app('number.twilio')->portabilityProbe('+'.$digits);

        if (! empty($probe['portable'])) {
            return [
                'eligible' => true,
                'pin_required' => (bool) ($probe['pin_required'] ?? true),
                'reason' => null,
            ];
        }

        // Not proven — an honest sorry, tailored to whether we simply couldn't
        // verify (retryable) vs. the carrier said it isn't portable.
        $unverified = ($probe['reason'] ?? null) === 'unverified';

        return [
            'eligible' => false,
            'pin_required' => true,
            'reason' => $unverified
                ? 'We couldn\'t confirm this number with the carrier just now. Please try again shortly, or contact support.'
                : 'Sorry — this number can\'t be brought in. Its current carrier doesn\'t allow it to be ported to us.',
        ];
    }
}
