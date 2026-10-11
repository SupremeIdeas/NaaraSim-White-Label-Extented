# NAARA-PAYOUT-GLOBAL — ADDENDUM A: Global-Rail Exposure & Funding Radar
Extends `NaaraSim-Global-Payout-Layer-Blueprint.md`. Build AFTER its Phase 1 (corridors) and alongside/after Phase 4 (float). One phase at a time, sandbox only, local PHPUnit, push with `[skip ci]`. Master-only by default (CLAUDE.md white-label rule) unless the global rail itself ships to a child.

## 0. What the owner asked for (plain terms)
1. Identify users who are NOT eligible for Stripe Connect payouts and who choose the Global Payout rail (Payoneer / Grey / Stripe Global Payouts) as their payout rail.
2. In the admin panel, show HOW MANY such users there are, sortable, with each user's total withdrawable balance, and the TOTAL across all of them = the maximum money we may need to fund.
3. Show REAL-TIME withdrawal analytics as people withdraw, and from that, how much we need to fund NEXT.

## 1. What is trackable vs. what is only an estimate (be honest in the UI)
| Item | Trackable? | Source |
|---|---|---|
| Users who chose the global rail, and their country/currency/status | Exact | `payout_rail_enrollments` (new) |
| Each user's withdrawable balance per bucket (credits, referral, merchant, partner, staff) | Exact, USD | existing `balance()` / `withdrawableBalance()` services |
| Sum of all balances on the rail = **Maximum Exposure** (worst case if everyone withdrew now) | Exact | resolver + snapshot |
| Requests already made but not yet sent (pending/approved/awaiting_funds) = **Committed Unsent** | Exact | `payout_requests` |
| Requests sent, waiting for provider confirmation = **In Flight** | Exact | `payout_requests` |
| Balances that the next scheduled auto-run WILL sweep = **Due at Next Sweep** | Exact (rules are deterministic) | same rules as `payouts:earnings-run` / `partners:payout-run` |
| Money that will be withdrawn manually over the next 7 days | ESTIMATE (p50 / p90 from history) | forecast job |
| Provider float actually available | Exact only where the provider API exposes balance; else admin-entered | Phase 4 `payout_float_balances` |
State this on screen: exact numbers are labelled "Exact", forecasts "Estimate (p50/p90)".

## 2. Definitions (implement as code constants + docblocks)
- **Global rail** = providers `payoneer`, `grey`, `stripe_global` (config list `payouts.global_rail_providers`).
- **Stripe-ineligible** = the user's payout country has no ENABLED `stripe_connect` row in `payout_corridors`, OR their Stripe Connect onboarding was declined/incapable. Store the reason: `country_not_supported | onboarding_declined | recipient_agreement_unsupported | user_choice`.
- **Enrolled** = user has a row in `payout_rail_enrollments` for a global-rail provider. States: `selected` (chose rail, provider onboarding not done) → `onboarding` → `active` (payee approved, account verified) → `paused` | `declined`.
- **Withdrawable balance (USD)** = sum of the five buckets (see §4.2). Never includes deposit-funded wallet money (the no-deposit-funded-balance guard test from Phase 5 also protects this screen).

## 3. Data model (all additive)
```
payout_rail_enrollments
  id, user_id fk, provider string, country char(2), currency char(3),
  status enum(selected,onboarding,active,paused,declined),
  stripe_connect_eligible bool, ineligible_reason string null,
  payout_account_id nullable fk, selected_at, activated_at null, last_status_at,
  timestamps; unique(user_id, provider); index(provider,status), index(country)

payout_exposure_snapshots            -- time series, written every 5 min + daily rollup
  id, captured_at, provider, country char(2) null (null = all), currency char(3) null,
  users_selected, users_onboarding, users_active,
  balance_credits_usd, balance_referral_usd, balance_merchant_usd,
  balance_partner_usd, balance_staff_usd, max_exposure_usd,
  committed_unsent_usd, in_flight_usd, due_next_sweep_usd,
  forecast_7d_p50_usd, forecast_7d_p90_usd, float_available_usd null,
  recommended_topup_p50_usd, recommended_topup_p90_usd,
  index(captured_at), index(provider,captured_at)

payout_withdrawal_stats_hourly       -- pre-aggregated for fast charts
  id, hour_start, provider, country, requests_count, requested_usd, paid_count, paid_usd,
  failed_count, failed_usd, avg_time_to_paid_sec null, unique(hour_start,provider,country)
```
No changes to `payout_requests` beyond the columns already added by the main blueprint (`usd_amount`, `corridor_id`, `fx_rate`). Reuse `payout_float_balances/movements` for actual funding history.

## 4. Services
### 4.1 `RailEnrollmentService`
- `select(User, provider, country)`: called when the user picks a global rail in `Withdraw`/onboarding. Computes `stripe_connect_eligible` via `StripeEligibility::check($country)` (reads corridor table + any declined Stripe Connect account) and stores the reason. Upserts the enrollment (`selected`).
- `sync(PayoutAccount)`: called from the provider webhook handlers (Payoneer payee approved/declined, Grey verified) → moves status `onboarding → active | declined`. Keep enrollment and `payout_accounts.is_verified` consistent; enrollment is the tracking record, `payout_accounts` stays the money-path record.
- `pause/resume(User)` admin action (audited).
- Backfill command `payouts:rail-backfill` creates enrollments for existing users whose `payout_accounts.provider` is a global-rail provider.
- Only users who CHOSE the rail are enrolled. Users who merely live in unserved countries are tracked separately as **Unserved demand** (§4.4), never mixed into enrolled totals.

### 4.2 `WithdrawableBalanceResolver`
`forUser(User): array{credits,referral,merchant,partner,staff,total}` in USD (decimal strings), using:
- credits: `CreditService::withdrawableBalance()` → `CreditSettings::creditsToUsd()`
- referral: `ReferralEarningsService::balance()`
- merchant: `MerchantEarningsService::balance()` for the user's active `Merchant`
- partner: `PartnerEarningsService::balance()` for the user's `Partner`
- staff: `StaffEarningsService::balance()`
Rules: subtract nothing already held (holds are already spent from the bucket); count only balances >= 0; a user can hold several buckets — sum, and keep the breakdown. Batch-optimised `forUsers(Collection)` to avoid N+1; it only runs over ENROLLED users (small set), never a 1M-user scan.
Read-through cache per user 60s; invalidated on hold/release/accrue events (reuse the existing `PayoutReversed/PayoutSettled` listeners plus earnings accrue hooks — do not add a second ledger).

### 4.3 `FundingRadar`
Pure calculation service, unit-tested with fixed clocks.
```
Committed Unsent  C = Σ usd(payout_requests status in pending,approved,awaiting_funds, provider in rail)
In Flight         I = Σ usd(status = processing)
Due at Next Sweep S = Σ balances of enrolled ACTIVE users that the next run WILL pay:
                      autopilot on AND balance >= payouts.min_withdrawal_usd AND payout account verified
                      AND (free-payout allowance left OR KYC-L2) AND not in cooling-off/paused/denied country
                      (import the SAME eligibility predicates used by EarningsPayoutRunCommand/PartnerPayoutRunCommand — extract them into a shared `PayoutEligibility` class, do NOT copy the logic)
                      In manual mode S = 0.
Remaining         R = MaxExposure − S
Forecast 7d       F = R × w, where w = EWMA (8 weeks) of (withdrawn in week / opening balance of week), per bucket & provider.
                      p50 = F ; p90 = F × spikeFactor (default 1.6, or measured 90th-percentile weekly ratio once >= 8 weeks of data)
                      If < 4 weeks of history → use configurable defaults (payouts.radar.default_weekly_withdraw_ratio = 0.25) and label "low confidence".
FX buffer         fx = payouts.radar.fx_buffer_pct (default 3%) applied to non-USD payout currencies.
Recommended top-up (p50) = max(0, C + S + F_p50) × (1+fx) − float_available
Recommended top-up (p90) = max(0, C + S + F_p90) × (1+fx) − float_available
```
Also returns the same per currency (converted with current FX) because Payoneer/Grey may be funded in specific currencies. In-flight (I) is shown but excluded from the top-up sum on providers that debit float at submit; make this a per-provider flag `debits_float_at_submit`.
If `float_available` is unknown (no balance API) show top-up as "before your recorded float" and require the admin to enter the current float (writes `payout_float_movements` adjustment).

### 4.4 `UnservedDemandReport` (optional but cheap, high value)
Per country: users with a signed-up country and a positive withdrawable balance whose country has NO enabled corridor at all, count + total USD. Tells the owner which corridor to open next. SQL aggregate, cached 1h. Clearly labelled "not enrolled, not part of funding".

### 4.5 Snapshot + stats jobs (Horizon, `withoutOverlapping`)
- `payouts:radar-snapshot` every 5 minutes: compute per provider and per country, write `payout_exposure_snapshots`, refresh Redis keys `payout:radar:{provider}` (TTL 120s). Keep raw 5-min rows 14 days, then roll up hourly, then daily forever (a `payouts:radar-prune` job). Register in `routes/console.php`, add heartbeat via `SchedulerHealth`.
- `payouts:stats-hourly` every 5 minutes: upserts the current hour bucket from `payout_requests` (idempotent recompute of the last 2 hours to catch late webhooks).
- Event-driven bump: on `PayoutRequested`(new event if absent), `PayoutSettled`, `PayoutReversed`, dispatch `RadarBumpJob` that updates the Redis counters immediately so the dashboard moves within seconds; the 5-minute snapshot is the source of truth and corrects any drift.

## 5. Admin UI — `Admin\GlobalPayoutRail` (Livewire, admin/super_admin only, route under existing admin group, nav entry via `AdminHotMenu`)
Header: provider filter (All/Payoneer/Grey/Stripe Global), country filter, currency filter, date range, "Live" indicator with last-refreshed time. Poll 15s (`wire:poll.15s`) reading Redis, not the DB.

**Tab 1 — Overview (KPI cards)**
Enrolled users (selected / onboarding / active) · **Max Exposure** · Committed Unsent · In Flight · Due at Next Sweep · Forecast 7d (p50 / p90, with confidence label) · Float available · **Recommended top-up now (p50 / p90)** with a red banner when float < Committed Unsent + Due at Next Sweep (= "payouts will stall").

**Tab 2 — Users (the sortable list)**
Columns: user (name, id, link), country, currency, provider, enrollment status, Stripe-ineligible reason, balance per bucket, **total withdrawable USD**, in-flight/committed for that user, lifetime paid, last withdrawal, KYC level, cooling-off flag. Default sort: total balance desc. Sort on every numeric column; filters: status, country, provider, balance range, "will be swept next run", "KYC blocked". Footer row = totals for the FILTERED set (this is "the total amount we will fund"). CSV export (audited via `Auditor`, masked account numbers, no full IBAN/account numbers).
Row actions: pause/resume enrollment, open user payout account (masked), open payout requests.

**Tab 3 — Live analytics**
- Now feed: latest withdrawals (time, user, country, amount USD/local, provider, status) — polled, newest first.
- Charts (hourly + daily): requested vs paid USD, count by provider, by country (top 10), success/failure rate, average time-to-paid, failure reasons, average and median withdrawal size, new enrollments per day.
- Concentration: top 10 users by balance and their % of Max Exposure (alert if any one user > 20% of Max Exposure — configurable).
- Velocity: withdrawals in last 1h / 24h vs trailing 7-day average (flag > 2×).

**Tab 4 — Funding planner**
Table by provider × currency: recommended top-up p50/p90, current float, last top-up (from `payout_float_movements`), coverage in days = float ÷ average daily paid. Button "Record top-up" writes an audited `payout_float_movements` row and re-runs the radar. History chart: recommended vs actual funding over time. "What if" input: admin types a hypothetical % of users withdrawing today → shows required funding (simple multiplier over Max Exposure).

**Tab 5 — Unserved demand** (§4.4).

Never show provider cost to non-admin users (money rule 2). This page is admin-only so est. provider cost may appear in the funding planner.

## 6. Alerts (reuse `AlertAdminJob`, codes namespaced `rail_*`, throttled so one condition alerts once per hour)
- `rail_float_short`: float < Committed Unsent + Due at Next Sweep.
- `rail_float_low_p90`: float < p90 recommended requirement.
- `rail_concentration`: single-user share of exposure above threshold.
- `rail_velocity_spike`: 1h withdrawals > 3× trailing average.
- `rail_stale_snapshot`: no snapshot in 15 minutes.
- `rail_failure_rate`: failure rate > 10% over last 20 payouts for a provider (also flips the provider's health flag used by `CorridorRouter`).

## 7. Interaction with the payout engine (no changes to money-path semantics)
- In MANUAL mode the admin approval step becomes the funding gate: the Approve button shows the funding coverage for that request and warns when float is insufficient; a request that cannot be funded moves to `awaiting_funds` (Phase 4) instead of failing.
- In AUTOPILOT mode the shared `PayoutEligibility` class keeps the sweep and the radar's "Due at Next Sweep" in perfect agreement — one predicate, two consumers. If either changes, both change.
- The radar READS money tables and WRITES only to its own tables/Redis. It must never mutate wallets, earnings, or `payout_requests`.

## 8. Build phases for Claude Code (acceptance checks)
- **R1 Enrollment**: migrations for `payout_rail_enrollments`, `RailEnrollmentService`, `StripeEligibility`, hooks in the account-selection UI + provider webhooks, backfill command. *Accept:* choosing a global rail creates/updates an enrollment with correct eligibility + reason; webhook approvals/declines move status; backfill is idempotent.
- **R2 Balances + shared eligibility**: `WithdrawableBalanceResolver` (with batch mode) and extraction of `PayoutEligibility` from the two run-commands with NO behaviour change. *Accept:* existing payout/partner/earnings run tests stay green; resolver matches the sum of the five services for a seeded multi-bucket user; no N+1 (query-count test); deposit-funded balances are never counted.
- **R3 FundingRadar + snapshots**: service, snapshot/stats tables, jobs, Redis keys, prune job, heartbeats. *Accept:* fixed-clock unit tests for C, I, S, F, top-up math (manual vs autopilot, float unknown, FX buffer, <4 weeks history low-confidence); snapshot job idempotent; drift correction test (bump then recompute).
- **R4 Admin UI**: `Admin\GlobalPayoutRail` tabs 1–4 with polling, sorting, filters, footer totals, CSV export, record-top-up. *Accept:* only admin roles reach it (403 otherwise); filtered footer total equals sum of visible rows; export contains no full account numbers; page renders from Redis when DB slow (cache test).
- **R5 Alerts + unserved demand + polish**: alert codes, throttling, tab 5, docs `docs/payouts/RADAR-RUNBOOK.md` (how to read each card, what to do when a banner is red). *Accept:* each alert fires once per condition per hour; runbook exists.

## 9. Performance & safety notes
- Only enrolled users are iterated; unserved demand uses aggregate SQL. Target: full snapshot < 2s at 50k enrolled users (chunked, `chunkById(500)`).
- All money as decimal strings/`bcmath` or integer minor units; no float arithmetic in the radar.
- Snapshots are append-only; never edit historical rows.
- Every export, pause/resume and top-up entry goes through `Auditor::log`.
- Timezone: store UTC, display in admin's timezone; hourly buckets in UTC.
- Do not port to white-label children without running the CLAUDE.md porting test (this page reads only this install's data, but the global rail itself is master-first).

## 10. Defaults if the owner does not decide
Spike factor 1.6 · FX buffer 3% · default weekly withdraw ratio 25% until 4 weeks of data · concentration alert 20% · poll 15s · snapshot every 5 min.
