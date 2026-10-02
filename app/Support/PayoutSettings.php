<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-toggled settings for the payout engine (ROADMAP §Layer 0). The whole
 * money-OUT feature is OFF by default — nothing changes for existing users until
 * the owner switches it on. Kept tiny and Setting-backed like every other
 * NaaraSim feature flag.
 */
class PayoutSettings
{
    public const FLAG = 'payouts.enabled';

    public const MODE = 'payouts.mode';               // manual | autopilot

    public const MIN = 'payouts.min_withdrawal_usd';

    public const FREE_COUNT = 'payouts.free_payout_count'; // free payouts before KYC (BUILD-22 §3)

    // Payout Guardian + engine safety (Addendum C §7). Every value is admin-editable
    // and audited; the defaults are the owner-safe ones from the blueprint (§11).
    public const AUTO_APPROVAL = 'payouts.auto_approval.enabled';      // default OFF
    public const AUTO_SHADOW = 'payouts.auto_approval.shadow';         // default ON
    public const MAX_OPEN = 'payouts.max_open_requests_per_user';      // default 3
    public const COOLING_OFF_HOURS = 'payouts.cooling_off_hours';      // default 48
    public const FX_TOLERANCE_PCT = 'payouts.fx_tolerance_pct';        // default 3
    public const MATURITY_DAYS = 'payouts.maturity_days';              // default 3
    public const NEW_ACCOUNT_DAYS = 'payouts.new_account_days';        // default 7
    public const DAILY_CAP_USD = 'payouts.auto_approval.daily_cap_usd';// default 5000
    public const QA_SAMPLE_PCT = 'payouts.auto_approval.qa_sample_pct';// default 3
    public const NAME_MATCH = 'payouts.name_match_threshold';          // default 0.85
    public const LOOKUP_GRACE_MIN = 'payouts.lookup_grace_minutes';    // default 30
    public const QUOTE_HOURS = 'payouts.quote_lock_hours';             // default 24

    // Guardian gates, scoring bands and tier limits (Addendum C §4). 0 = "off" for caps.
    public const DENIED_COUNTRIES = 'payouts.denied_countries';        // csv of ISO-2, default none
    public const TIER_LIMIT_PREFIX = 'payouts.auto_approval.tier_limit.'; // .new/.trusted/.vip  (100/500/2000)
    public const BAND_APPROVE = 'payouts.guardian.band_approve';       // default 30
    public const BAND_HOLD = 'payouts.guardian.band_hold';             // default 60
    public const WEIGHTS = 'payouts.guardian.weights';                 // json override of the signal weights
    public const USER_CAP_PREFIX = 'payouts.user_cap_usd.';            // .daily/.weekly/.monthly (0 = off)
    public const LARGEST_MULT = 'payouts.largest_previous_multiple';   // default 5
    public const PROVIDER_AUTO_PREFIX = 'payouts.provider.';           // .{name}.auto_approve (default OFF)
    public const BREAKER_HOURLY_FLOOR = 'payouts.auto_approval.hourly_floor'; // default 5

    // Addendum D hardening knobs (all admin-editable; defaults are the blueprint's).
    public const HTTP_TIMEOUT = 'payouts.http_timeout_seconds';                // default 25
    public const HTTP_CONNECT_TIMEOUT = 'payouts.http_connect_timeout_seconds';// default 5
    public const WEBHOOK_PAYLOAD_DAYS = 'payouts.webhook_payload_retention_days'; // default 90
    public const WEBHOOK_TOLERANCE = 'payouts.webhook_timestamp_tolerance_seconds'; // default 300

    public const MAX_FEE_RATIO = 'payouts.max_fee_ratio_pct';                  // default 5 (%)
    public const FX_BAND = 'payouts.fx_sanity_band_pct';                       // default 15 (%)

    public const STEP_UP_REQUIRED = 'payouts.step_up.required';                // default OFF
    public const STEP_UP_MINUTES = 'payouts.step_up.valid_minutes';            // default 15
    public const SEND_DELAY_GLOBAL = 'payouts.send_delay_minutes.global';      // default 10
    public const SEND_DELAY_LOCAL = 'payouts.send_delay_minutes.local';        // default 0
    public const NOTIFY_ON_REQUEST = 'payouts.notify_on_request';              // default ON
    public const OUTAGE_ALERT_MINUTES = 'payouts.outage_alert_minutes';        // default 60

    /** Adding/changing a payout account and every global-rail withdrawal needs a fresh 2FA / email-code check. */
    public static function stepUpRequired(): bool
    {
        return (bool) Setting::getValue(self::STEP_UP_REQUIRED, false);
    }

    public static function stepUpValidMinutes(): int
    {
        return min(120, max(1, (int) Setting::getValue(self::STEP_UP_MINUTES, 15)));
    }

    /** Minutes between approval and send for a NEW destination — the user's cancel window. */
    public static function sendDelayMinutes(bool $global): int
    {
        return min(1440, max(0, (int) Setting::getValue($global ? self::SEND_DELAY_GLOBAL : self::SEND_DELAY_LOCAL, $global ? 10 : 0)));
    }

    public static function notifyOnRequest(): bool
    {
        return (bool) Setting::getValue(self::NOTIFY_ON_REQUEST, true);
    }

    public static function outageAlertMinutes(): int
    {
        return max(5, (int) Setting::getValue(self::OUTAGE_ALERT_MINUTES, 60));
    }

    public const FAIL_CLOSED_HEARTBEAT = 'payouts.guardian.fail_closed_on_stale_heartbeat'; // default ON

    /** When ON (default), a stalled Guardian sweeper/metrics job stops auto-approvals (everything goes to manual review). */
    public static function failClosedOnStaleHeartbeat(): bool
    {
        return (bool) Setting::getValue(self::FAIL_CLOSED_HEARTBEAT, true);
    }

    public const ADD_ACCOUNT_PER_HOUR = 'payouts.limits.add_account_per_hour';  // default 5 (0 = off)
    public const WITHDRAW_PER_MINUTE = 'payouts.limits.withdraw_per_minute';   // default 3  (0 = off)
    public const WITHDRAW_PER_DAY = 'payouts.limits.withdraw_per_day';         // default 10 (0 = off)

    public static function addAccountPerHour(): int
    {
        return max(0, (int) Setting::getValue(self::ADD_ACCOUNT_PER_HOUR, 5));
    }

    public static function withdrawPerMinute(): int
    {
        return max(0, (int) Setting::getValue(self::WITHDRAW_PER_MINUTE, 3));
    }

    public static function withdrawPerDay(): int
    {
        return max(0, (int) Setting::getValue(self::WITHDRAW_PER_DAY, 10));
    }

    public const MANUAL_EXTERNAL = 'payouts.manual_external.enabled';          // default OFF

    /** Plan-B rail: an admin pays outside the platform and records proof. */
    public static function manualExternalEnabled(): bool
    {
        return (bool) Setting::getValue(self::MANUAL_EXTERNAL, false);
    }

    public const DUAL_CONTROL_USD = 'payouts.dual_control_usd';                // default 0 (off)
    public const TAX_FORM_OVER_USD = 'payouts.tax_form_required_over_usd';     // default 0 (off)

    public static function dualControlUsd(): float
    {
        return max(0.0, (float) Setting::getValue(self::DUAL_CONTROL_USD, 0));
    }

    /** Lifetime-in-year USD paid above which a payee must have tax details on file (0 = never required). */
    public static function taxFormOverUsd(): float
    {
        return max(0.0, (float) Setting::getValue(self::TAX_FORM_OVER_USD, 0));
    }

    public const STRIPE_GLOBAL = 'payouts.stripe_global_payouts';             // default OFF

    /** Owner confirms the Stripe account can use Global Payouts (US/UK business + Treasury) — lifts the region limit. */
    public static function stripeGlobalPayouts(): bool
    {
        return (bool) Setting::getValue(self::STRIPE_GLOBAL, false);
    }

    /** A corridor's fixed provider fee may not exceed this share of the payout (0 = guard off). Stored as %. */
    public static function maxFeeRatio(): float
    {
        return max(0.0, (float) Setting::getValue(self::MAX_FEE_RATIO, 5)) / 100;
    }

    /** A new FX rate further than this from the last accepted one is refused (0 = guard off). */
    public static function fxSanityBandPct(): float
    {
        return max(0.0, (float) Setting::getValue(self::FX_BAND, 15));
    }

    /** Total seconds a provider call may take. Must stay below SendPayoutJob::$timeout (40). */
    public static function httpTimeout(): int
    {
        return min(35, max(5, (int) Setting::getValue(self::HTTP_TIMEOUT, 25)));
    }

    public static function httpConnectTimeout(): int
    {
        return min(15, max(1, (int) Setting::getValue(self::HTTP_CONNECT_TIMEOUT, 5)));
    }

    public static function webhookPayloadRetentionDays(): int
    {
        return max(1, (int) Setting::getValue(self::WEBHOOK_PAYLOAD_DAYS, 90));
    }

    public static function webhookToleranceSeconds(): int
    {
        return max(30, (int) Setting::getValue(self::WEBHOOK_TOLERANCE, 300));
    }

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    /**
     * Settlement mode: `manual` (the Guardian evaluates but NEVER approves — every
     * request lands in the admin queue) or `auto` (the Guardian may approve). The
     * legacy stored value `autopilot` maps to `auto`; the old synchronous
     * "send inside the withdrawal transaction" behaviour behind it is gone.
     */
    public static function mode(): string
    {
        return in_array(Setting::getValue(self::MODE, 'manual'), ['auto', 'autopilot'], true) ? 'auto' : 'manual';
    }

    /** True when the Guardian is allowed to approve (kept under its old name for callers/tests). */
    public static function autopilot(): bool
    {
        return self::mode() === 'auto';
    }

    public static function autoApprovalEnabled(): bool
    {
        return (bool) Setting::getValue(self::AUTO_APPROVAL, false);
    }

    /** Shadow mode: evaluate and log decisions but change nothing. ON until an admin graduates it. */
    public static function shadowMode(): bool
    {
        return (bool) Setting::getValue(self::AUTO_SHADOW, true);
    }

    public static function maxOpenRequests(): int
    {
        return max(1, (int) Setting::getValue(self::MAX_OPEN, 3));
    }

    public static function coolingOffHours(): int
    {
        return max(0, (int) Setting::getValue(self::COOLING_OFF_HOURS, 48));
    }

    public static function fxTolerancePct(): float
    {
        return max(0.0, (float) Setting::getValue(self::FX_TOLERANCE_PCT, 3));
    }

    public static function maturityDays(): int
    {
        return max(0, (int) Setting::getValue(self::MATURITY_DAYS, 3));
    }

    public static function newAccountDays(): int
    {
        return max(0, (int) Setting::getValue(self::NEW_ACCOUNT_DAYS, 7));
    }

    public static function dailyAutoCapUsd(): float
    {
        return max(0.0, (float) Setting::getValue(self::DAILY_CAP_USD, 5000));
    }

    public static function qaSamplePct(): float
    {
        return min(100.0, max(0.0, (float) Setting::getValue(self::QA_SAMPLE_PCT, 3)));
    }

    public static function nameMatchThreshold(): float
    {
        return min(1.0, max(0.0, (float) Setting::getValue(self::NAME_MATCH, 0.85)));
    }

    public static function lookupGraceMinutes(): int
    {
        return max(1, (int) Setting::getValue(self::LOOKUP_GRACE_MIN, 30));
    }

    public static function quoteLockHours(): int
    {
        return max(1, (int) Setting::getValue(self::QUOTE_HOURS, 24));
    }

    /** @return list<string> upper-case ISO-2 codes payouts are never sent to */
    public static function deniedCountries(): array
    {
        return array_values(array_filter(array_map(
            fn ($c) => strtoupper(trim($c)),
            explode(',', (string) Setting::getValue(self::DENIED_COUNTRIES, '')),
        )));
    }

    /** Largest USD a Guardian may auto-approve for a trust tier (new / trusted / vip). */
    public static function tierLimitUsd(string $tier): float
    {
        $defaults = ['new' => 100.0, 'trusted' => 500.0, 'vip' => 2000.0];

        return max(0.0, (float) Setting::getValue(self::TIER_LIMIT_PREFIX.$tier, $defaults[$tier] ?? 100.0));
    }

    public static function bandApprove(): int
    {
        return max(0, (int) Setting::getValue(self::BAND_APPROVE, 30));
    }

    public static function bandHold(): int
    {
        return max(self::bandApprove(), (int) Setting::getValue(self::BAND_HOLD, 60));
    }

    /** Soft-signal weights (points added to the 0–100 risk score); admin may override any of them. */
    public static function weights(): array
    {
        $defaults = [
            'new_account' => 15, 'first_withdrawal' => 10, 'fresh_earnings' => 25, 'referral_concentration' => 20,
            'velocity' => 15, 'recent_failures' => 15, 'trusted_tier' => -10, 'vip_tier' => -20, 'recent_incident' => 15,
        ];
        $override = json_decode((string) Setting::getValue(self::WEIGHTS, '{}'), true);

        return is_array($override) ? array_replace($defaults, array_intersect_key($override, $defaults)) : $defaults;
    }

    /** Per-user USD cap for a window (daily/weekly/monthly); 0 = no cap. */
    public static function userCapUsd(string $window): float
    {
        return max(0.0, (float) Setting::getValue(self::USER_CAP_PREFIX.$window, 0));
    }

    /** A payout above this multiple of the user's largest previous one needs KYC-L2. */
    public static function largestPreviousMultiple(): float
    {
        return max(1.0, (float) Setting::getValue(self::LARGEST_MULT, 5));
    }

    /** Per-provider switch for system approval. OFF until an admin enables the rail. */
    public static function providerAutoApprove(?string $provider): bool
    {
        return $provider !== null
            && (bool) Setting::getValue(self::PROVIDER_AUTO_PREFIX.$provider.'.auto_approve', false);
    }

    public static function breakerHourlyFloor(): int
    {
        return max(1, (int) Setting::getValue(self::BREAKER_HOURLY_FLOOR, 5));
    }

    /** Minimum a single withdrawal may request (USD). */
    public static function minWithdrawal(): float
    {
        return (float) Setting::getValue(self::MIN, 5.0);
    }

    /**
     * How many successful payouts a user may take before KYC-L2 is required
     * (BUILD-22 §3, Frank's request). Combined across every earner type. Default 5.
     */
    public static function freePayoutCount(): int
    {
        return max(0, (int) Setting::getValue(self::FREE_COUNT, 5));
    }
}
