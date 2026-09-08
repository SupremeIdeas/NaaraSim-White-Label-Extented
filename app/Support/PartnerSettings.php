<?php

namespace App\Support;

use App\Models\Partner;
use App\Models\Setting;

/**
 * Global, admin-configurable defaults for the Partner program. Per-partner
 * values (profit-share %, cadence, mode) live on the Partner row; these are the
 * platform-wide defaults + the master on/off flag. Setting-backed, no redeploy.
 */
class PartnerSettings
{
    public const FLAG = 'partners.enabled';

    public const DEFAULT_CADENCE = 'partners.default_cadence';

    public const DEFAULT_MODE = 'partners.default_payout_mode';

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    public static function defaultCadence(): string
    {
        $v = (string) Setting::getValue(self::DEFAULT_CADENCE, Partner::CADENCE_MONTHLY);

        return in_array($v, [Partner::CADENCE_WEEKLY, Partner::CADENCE_MONTHLY], true) ? $v : Partner::CADENCE_MONTHLY;
    }

    public static function defaultMode(): string
    {
        $v = (string) Setting::getValue(self::DEFAULT_MODE, Partner::MODE_MANUAL);

        return in_array($v, [Partner::MODE_MANUAL, Partner::MODE_AUTO], true) ? $v : Partner::MODE_MANUAL;
    }
}
