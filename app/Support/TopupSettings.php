<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-tunable knobs for the Top-Up layer (Admin > Top-ups). Everything has a safe default so the feature works the moment a
 * supplier key is saved. Reads are guarded so a missing settings table (fresh install, tests) never throws.
 */
class TopupSettings
{
    public const DEFAULTS = [
        'markup_pct' => 6,                // global markup over our cost
        'min_profit_usd' => 0.05,         // MarginGuard floor over cost
        'fx_buffer_pct' => 1.5,           // protects against FX moving between sale and float top-up
        'quote_ttl_seconds' => 300,       // price lock
        'offer_cache_seconds' => 120,     // live supplier offers are cached this long, then re-fetched
        'min_order_usd' => 0.5,
        'max_order_usd' => 200,
        'daily_cap_usd' => 150,           // per user per day
        'weekly_cap_usd' => 500,
        'new_account_hours' => 48,
        'new_account_daily_cap_usd' => 20,
        'max_numbers_per_day' => 8,       // distinct recipients per user per day
        'max_orders_per_hour' => 10,
        'escalate_after_minutes' => 30,   // a pending order older than this goes to the support queue (never auto-refunded)
        'catalogue_drop_alert_pct' => 30,
        'log_retention_days' => 90,
        'failover' => true,
        'default_country' => 'NG',
        'notify_receipts' => true,
        'max_autoreloads_per_user' => 5,
        'autoreload_enabled' => true,
        // Credit discount on DATA bundles only. OFF until switched on; always a small slice of OUR profit (see TopupCredit).
        'credit_discount_enabled' => true,
        'credit_profit_share_pct' => 20,   // at most this % of our profit on the sale may be given back as credit discount
        'credit_max_discount_usd' => 0.5,  // and never more than this per order
        'credit_min_profit_usd' => 0.05,   // profit that must remain after the discount
        // The offers strip on the top-up page (real data bundles, one tap to buy). Prices come from the same engine; the markup below, when set, replaces the standard markup for offers only.
        'offers_enabled' => true,
        'offers_max' => 8,
        'offers_markup_pct' => '',         // blank = standard pricing
        'offers_hidden' => '',             // comma-separated product ids the admin removed from the strip
        'offers_pinned' => '',             // comma-separated product ids shown first
    ];

    public static function get(string $key): mixed
    {
        try {
            return Setting::getValue('topup.'.$key, self::DEFAULTS[$key] ?? null);
        } catch (\Throwable) {
            return self::DEFAULTS[$key] ?? null;
        }
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::setValue('topup.'.$key, $value, 'topup');
    }
}
