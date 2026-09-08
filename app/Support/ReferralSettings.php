<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-set referral configuration (NAARA-BUILD-22 §1). The margin-share percent
 * is the share of Naara's OWN margin on a referred user's first successful
 * transaction that becomes the referrer's real, withdrawable earning. Separate
 * from the NaaraCredit referral bonus, which is unaffected by this value.
 */
class ReferralSettings
{
    public const MARGIN_SHARE_PCT = 'referral.margin_share_pct';

    public const DEFAULT_PCT = 10.0;

    public static function isCacheKey(string $key): bool
    {
        return $key === self::MARGIN_SHARE_PCT;
    }

    /** The referral margin-share percentage (0–100). */
    public static function marginSharePct(): float
    {
        try {
            return Cache::rememberForever(self::MARGIN_SHARE_PCT, function () {
                $v = Setting::getValue(self::MARGIN_SHARE_PCT, self::DEFAULT_PCT);

                return is_numeric($v) ? (float) $v : self::DEFAULT_PCT;
            });
        } catch (\Throwable) {
            return self::DEFAULT_PCT;
        }
    }

    public static function setMarginSharePct(float $pct): void
    {
        $pct = max(0.0, min(100.0, $pct));
        Setting::setValue(self::MARGIN_SHARE_PCT, $pct, 'referral');
        Cache::forget(self::MARGIN_SHARE_PCT);
    }
}
