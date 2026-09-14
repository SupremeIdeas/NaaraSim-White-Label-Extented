<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Scheduled tasks (blueprint Section 20). A single cron entry drives all of
 | these on the server:
 |   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
 */

// Shared-hosting queue drain (blueprint Section 20). On shared cPanel there is
// no Redis and no long-running worker, so every external API job (money paths
// included, money rule 8) sits in the `jobs` table until we drain it. The one
// schedule:run cron above triggers this each minute; --stop-when-empty keeps it
// short-lived so it never becomes a runaway process. On a VPS the queue is
// Redis + Horizon, so this drain is skipped entirely.
if (config('queue.default') === 'database') {
    // --tries=1 is the safe floor: money jobs never blind-retry (money rule 7).
    // Jobs that DO want retries opt in via their own $tries (e.g. the catalogue
    // sync), which takes precedence over this worker default.
    Schedule::command('queue:work --stop-when-empty --tries=1 --max-time=50')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// Ping provider wallets and alert on low balance (Section 17.2).
Schedule::command('providers:health-check')->everyFifteenMinutes()->withoutOverlapping();

// Worker-layer watchdog (Platform Health): alerts admins if Redis/Horizon has
// stopped processing jobs. Runs off the cron (independent of the queue it
// watches) so it can still raise the alarm when the workers themselves are down.
Schedule::command('ops:worker-health')->everyFiveMinutes()->withoutOverlapping();

// NCI (NAARA-BUILD-16 §3.2): daily full recompute of every provider's score,
// confidence and risk over the trailing window. Observational only.
Schedule::command('nci:recompute')->dailyAt('02:15')->withoutOverlapping()->runInBackground();

// BUILD-19 §4 — keep provider_outcomes bounded: prune rows past the 90-day
// retention horizon weekly (NCI scores over 30d, the breaker over 24h, so
// anything older is dead weight). Off-peak, withoutOverlapping, background.
Schedule::command('nci:prune-outcomes')->weekly()->sundays()->at('02:40')->withoutOverlapping()->runInBackground();

// Charge permanent-number (Naara Line) monthly subscriptions + release lapsed ones.
Schedule::command('virtual:renew')->dailyAt('04:00')->withoutOverlapping();

// Refresh eSIM catalogues + recompute retail via the PricingEngine.
Schedule::command('esim:sync')->dailyAt('03:00')->withoutOverlapping();

// Connectivity Analytics (Part A): poll active eSIMs for a usage reading —
// the interval is config-driven so it's tunable per environment without a
// code change (config('esim.usage_sync_interval_minutes'), default 15).
Schedule::command('esim:sync-usage')
    ->everyMinute()
    ->when(fn () => now()->minute % max(1, (int) config('esim.usage_sync_interval_minutes', 15)) === 0)
    ->withoutOverlapping()
    ->runInBackground();

// Keep esim_usage_snapshots bounded — prune past the retention window weekly.
Schedule::command('esim:prune-usage-snapshots')->weekly()->sundays()->at('02:50')->withoutOverlapping();

// Refresh the number country + service catalogue from the providers so the
// storefront always lists everything they support (blueprint Section 12).
Schedule::command('numbers:catalogue-sync')->weekly()->sundays()->at('03:30')->withoutOverlapping();

// Nightly encrypted database backup + cleanup of old archives (Section 28).
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping();

// Auto-promote eligible users to Merchant V1 — no-op unless the admin enabled it
// (BUILD-4 §4.3; the default is the manual "Ready to promote" queue).
Schedule::command('merchants:auto-promote')->dailyAt('05:00')->withoutOverlapping();

// Recompute the volume-based payout-gateway ranking (BUILD-4 §8) — cached daily.
Schedule::command('payouts:rank')->dailyAt('05:30')->withoutOverlapping();

// Refresh live currency-display FX rates (localized pricing) — display only.
Schedule::command('fx:sync')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('backup:run --only-db')->dailyAt('02:45')->withoutOverlapping();

// Promote local platform media to Wasabi once cloud keys go live (no-ops
// without keys, so it's safe to run hourly — the migration is automatic).
Schedule::command('media:migrate-to-wasabi')->hourly()->withoutOverlapping();

// Partner profit-share payouts — daily, but each partner is only paid when a
// full weekly/monthly period has elapsed (idempotent per period).
Schedule::command('partners:payout-run')->dailyAt('04:30')->withoutOverlapping();

// Automatic recurring merchant + referral earnings payouts (BUILD-22 §6) —
// staggered after the partner run. Each earner is paid their available balance
// when a verified account exists and they haven't passed the free-payout KYC
// threshold; idempotent (holds + unique reference).
Schedule::command('payouts:earnings-run')->dailyAt('04:45')->withoutOverlapping();

// Staff profit-share monthly close (BUILD-23 §3) — 1st of each month, quiet slot.
// Computes the prior month's platform profit once and accrues each active staff
// member's share; idempotent per profile+period.
Schedule::command('staff:compensation-close')->monthlyOn(1, '03:15')->withoutOverlapping();

// Merchant V2 client eSIM control: settle due auto-renewals, expire lapsed
// subscriptions, and alert merchants about upcoming renewals (money-safe).
Schedule::command('merchant:client-subscriptions')->dailyAt('05:35')->withoutOverlapping();

// Naara Gift: sync the gift-card catalogue from every registered provider.
Schedule::command('giftcards:sync')->dailyAt('03:15')->withoutOverlapping();

// Naara Gift: recover an order stuck 'processing' because its provider's
// async-delivery webhook never arrived — polls the real order-status
// endpoint (Reloadly/Bitrefill/Tillo) rather than leaving the buyer's
// receipt screen stuck forever on a dropped webhook.
Schedule::command('giftcards:reconcile-processing')->everyFifteenMinutes()->withoutOverlapping();

// Brand Directory (BUILD-9 §5.2/§6): charge due brand-listing subscriptions,
// pause short ones, and update follower-guarantee priority scores. runInBackground
// because this scales with subscriber count (real per-subscription computation)
// and must never block the per-minute queue:work tick. The command itself processes
// in chunks so its runtime stays flat. 05:45 sits just after the payout ranking
// (05:30) and the merchant sweep (05:35), clear of every other slot in the
// staggered window.
Schedule::command('brand-subscriptions:bill')->dailyAt('05:45')->withoutOverlapping()->runInBackground();

// Prompt 21-EXT2 §6: complete an in_progress white-label project intake once
// its admin-set deploy timeline has elapsed, and email the merchant.
Schedule::command('whitelabel:intake-deploy-check')->dailyAt('06:00')->withoutOverlapping();
