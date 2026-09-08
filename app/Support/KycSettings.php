<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-chosen KYC configuration (ROADMAP §Layer 0.3). The active provider
 * defaults to "manual" (admin review) so identity verification works out of the
 * box; the owner switches to Smile ID / Dojah once keys are saved.
 */
class KycSettings
{
    public const PROVIDER = 'kyc.provider';

    public static function provider(): string
    {
        $p = (string) Setting::getValue(self::PROVIDER, 'manual');

        return $p !== '' ? $p : 'manual';
    }
}
