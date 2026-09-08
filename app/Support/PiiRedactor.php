<?php

namespace App\Support;

/**
 * NAARA-BUILD-19 §9 — strips accidental PII out of provider error text before it
 * is stored (provider_outcomes.error_code) or shown in the Operations Center.
 * Some provider error responses inline a customer's phone number or email; this
 * applies the same masking discipline used for Sentry/log output so raw
 * identifying detail is never persisted unfiltered.
 */
class PiiRedactor
{
    public static function redact(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // Emails → local***@domain.
        $text = preg_replace_callback('/[\w.+-]+@[\w.-]+\.\w+/', fn ($m) => '[email]', $text);
        // Long digit runs (phone numbers, ICCIDs, tokens) — 7+ digits → [redacted].
        $text = preg_replace('/\+?\d[\d\s().-]{6,}\d/', '[redacted]', (string) $text);

        return $text;
    }
}
