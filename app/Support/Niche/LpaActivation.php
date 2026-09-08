<?php

namespace App\Support\Niche;

/**
 * Manual LPA activation fallback (blueprint Section 32). The QR code an eSIM
 * order carries simply encodes an "LPA string" (LPA:1$smdp-address$matching-id).
 * We surface that string beside every QR so a user who can't scan can add the
 * eSIM manually. This normalises whatever the provider returned into a usable
 * LPA string.
 */
class LpaActivation
{
    /**
     * Extract/normalise an LPA string from a provider order payload, or return
     * null if not derivable.
     */
    public static function fromPayload(array $payload): ?string
    {
        // A ready-made LPA string under any of the common keys.
        foreach (['lpa', 'lpa_string', 'activation_code', 'activationCode', 'matching_id_full'] as $key) {
            $value = data_get($payload, $key);
            if (is_string($value) && str_starts_with(strtoupper($value), 'LPA:')) {
                return $value;
            }
        }

        // Or build it from the SM-DP+ address + matching id.
        $smdp = data_get($payload, 'smdp_address') ?? data_get($payload, 'smdpAddress') ?? data_get($payload, 'smdp');
        $matching = data_get($payload, 'matching_id') ?? data_get($payload, 'matchingId');
        if (filled($smdp) && filled($matching)) {
            return self::build($smdp, $matching);
        }

        return null;
    }

    public static function build(string $smdp, string $matchingId, ?string $confirmationCode = null): string
    {
        $smdp = preg_replace('#^https?://#', '', trim($smdp));

        return rtrim('LPA:1$'.$smdp.'$'.$matchingId.($confirmationCode ? '$'.$confirmationCode : ''), '$');
    }

    /**
     * Apple one-tap install link (iOS 17.4+). Tapping it on an iPhone opens the
     * "Add eSIM" flow pre-filled — no scanning. Android falls back to the manual
     * code (the link simply won't resolve there), so we always show both.
     */
    public static function universalLink(string $lpa): ?string
    {
        if (! str_starts_with(strtoupper(trim($lpa)), 'LPA:')) {
            return null;
        }

        return 'https://esimsetup.apple.com/esim_qrcode_provisioning?carddata='.rawurlencode(trim($lpa));
    }

    /** Step-by-step manual install text shown beside the QR. */
    public static function steps(): array
    {
        return [
            'iPhone: Settings → Cellular/Mobile → Add eSIM → “Enter details manually”, then paste the code below.',
            'Android: Settings → Network → SIMs → Add eSIM → “Enter it manually” / scan, then paste the code below.',
            'Keep this code private — it activates your plan only once.',
        ];
    }
}
