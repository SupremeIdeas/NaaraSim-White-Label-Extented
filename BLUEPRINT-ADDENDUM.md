# Blueprint Addendum — Rules & Enhancements Beyond v5 (Sections 0–32)

> Everything here was built **on top of** the original NaaraSim Master Build
> Blueprint v5. It is written as **rules to fold into every future blueprint**
> so we never re-discover these the hard way. Each item is an imperative rule +
> a one-line reason. Grouped A–H.
>
> Original date range: 2026‑07‑12 → 2026‑07‑14. Framework baseline: **Laravel 12**.

---

## A. Framework & dependency baseline

**A1. Ship on a security-supported framework release, and upgrade off EOL immediately.**
We built on Laravel 11, which hit security-EOL on 2026‑03‑12 and carried 3
framework advisories; we upgraded to **Laravel 12** (12.63). *Rule: pin the
current supported major; treat "framework past security-EOL" as a release
blocker, not a backlog item.*

**A2. `composer audit` is the real dependency-security CI gate; keep its allow-list empty.**
A custom wrapper (`bin/security-audit.php`) fails the build on any advisory not
explicitly accepted. *Rule: every blueprint includes an audit gate; an accepted
advisory needs a written justification + mitigation, and gets removed the moment
its fix lands.*

**A3. Static analysis (Larastan/PHPStan) is a developer aid, NOT a CI gate and NEVER on the live platform.**
It produced false-alarm noise for a non-technical owner. *Rule: keep
`phpstan.neon` for optional local use; do not gate CI or block a release on it.*

**A4. Pin Livewire to `^3.0`.**
Composer's default pulled Livewire 4, which breaks the TALL setup. *Rule:
explicitly pin Livewire 3 (it bundles Alpine — never also import Alpine).*

---

## B. Hosting: shared cPanel AND VPS from one codebase

**B1. The installer asks Hosting Type and writes the matching driver profile.**
*Shared/cPanel → `database` for cache/session/queue (no Redis, no daemon).
VPS/Cloud → `redis` for all three (+ Horizon).* *Rule: any "runs on shared +
VPS" product must make the driver choice an install-time decision, not a manual
`.env` edit.*

**B2. Ship the `sessions` table migration whenever `SESSION_DRIVER=database` is possible.**
Laravel scaffolds `cache` + `jobs` tables but NOT `sessions`; database sessions
silently fail without it. *Rule: include a `create_sessions_table` migration.*

**B3. On the database queue, drain it from the scheduler — one cron runs everything.**
When `queue.default === 'database'`, schedule
`queue:work --stop-when-empty --tries=1 --max-time=50` every minute, gated so it
does NOT run on Redis/Horizon hosts. *Rule: shared hosting gets ONE cron
(`schedule:run`); the scheduler drains the queue. Never require a second worker
cron or Supervisor on shared hosting.*

**B4. Money jobs never blind-retry: the shared-hosting worker floor is `--tries=1`.**
Jobs that legitimately want retries set their own `$tries` (which wins).
*Rule: the default worker retry count is 1; retries are opt-in per job.*

**B5. `.env.example` defaults to the most-constrained profile (database drivers).**
So a fresh clone runs on the widest range of hosts. *Rule: default the shipped
env to the lowest-common-denominator host; document the VPS upgrade inline.*

**B6. Document a lossless shared→VPS migration.**
Flip 3 env vars to `redis`, start Horizon; the database-queue drain stops itself.
Admin-saved API keys live in the DB, so they migrate with it — no re-entry.

**B7. Deployment guide is use-case-first.**
A comparison table + separate step-by-step cPanel and VPS sections (document
root, PHP extensions, the **exact single cron line with the full PHP-binary
path**, Supervisor/Horizon conf). *Rule: give the exact cron and full php path —
non-technical operators can't infer them.*

---

## C. API keys & secrets managed in the admin panel

**C1. Every provider/gateway/integration key is pasteable in the admin panel — not just `.env`.**
`Support\ProviderKeys` stores all credentials in ONE encrypted settings row and
`applyToConfig()` overlays them on `config('services.*')` at boot, so services
read config unchanged. *Rule: a non-technical owner must be able to set every key
from the UI; `.env` remains a valid fallback.*

**C2. Admin-saved key overrides `.env`; a blank field keeps the existing key.**
*Rule: precedence is admin-UI > `.env`; blank = no-op, never an erase.*

**C3. Keys are encrypted at rest and never echoed back to the browser.**
Show a masked preview (`••••••ABCD`); never re-send the raw secret to the client.
*Rule: mask on display, audit WHICH keys changed (never the values).*

**C4. Live status reacts to saved keys.**
`ProviderStatus` flips a product **Coming Soon → Active** the moment its key is
saved. *Rule: gate product availability on real config so operators launch one
product at a time.*

**C5. Boot must degrade gracefully when cache/DB is unreachable.**
The boot-time key overlay wraps the whole cache/DB call in try/catch and falls
back to `.env` — an unreachable Redis/DB (pre-install, or a blip) must never
white-screen the app. *Rule: nothing in `AppServiceProvider::boot()` may hard-
depend on cache or DB being reachable.*

---

## D. Admin operability for a zero-code owner

**D1. Expose only SAFE runtime security toggles; never dangerous ones.**
Content-Security-Policy and HSTS get plain-language admin toggles (on by default,
applied live, super-admin only) in case they clash on an unknown host. SSRF
guard, session encryption, and rate limits are deliberately NOT toggleable.
*Rule: give the operator escape hatches for host-clash-prone headers only; keep
foot-guns out of the UI.*

**D2. A CodeCanyon-style web installer, re-runnable by deleting a lock file.**
Wizard (requirements → environment+hosting → database → done), generates
`APP_KEY`, migrates+seeds, seeds a default super-admin shown on the done screen,
caches config/routes/views/icons, links storage. *Rule: installation is a UI
flow, never a shell checklist.*

**D3. Admin path is env-driven and 404s (never redirects) for non-admins.**
Already in-spirit with S25 but reinforced: a guessable path returns a plain 404.

---

## E. Storage & backup resilience

**E1. Storage falls back to the server disk until Wasabi is configured.**
`Support\MediaStorage`: Wasabi when key+secret+bucket are ALL set, else the
`public` (or `local` for private) disk — a fresh install works before any Wasabi
keys. *Rule: never hard-require object storage at install time; degrade to local
and document it.* (This intentionally softens the blueprint's "never local disk"
for the pre-config window only.)

**E2. Private artifacts (data exports) use a never-web-accessible disk + gated controller.**
Served only through an authenticated controller that always serves the CURRENT
user's file. *Rule: user-data exports never get a public URL.*

**E3. Research and enforce upload limits.**
2 MB raster / 512 KB SVG; `mimes:png,jpg,jpeg,webp,gif,svg`. Feed the same list
to the input's `accept`.

**E4. Database backup must survive a host with no `mysqldump`/`sqlite3` binary.**
Detect the binary; when absent (typical shared cPanel) fall back to a pure-PHP
dumper (`ifsnop/mysqldump-php`, needs only SELECT + SHOW VIEW); always use a PDO
dumper for SQLite. Restore is snapshot-first and always brings the app back up;
dataset import is dry-run-first, transaction-wrapped, idempotent, allow-listed
tables only. *(Section 28 anticipated the ifsnop fallback; the PDO-sqlite dumper,
snapshot-first restore, and dry-run import are the additions.)*

---

## F. UI/UX beyond the base spec

**F1. One premium responsive app shell shared by customer + admin.**
Apple-inspired floating desktop side menu + mobile bottom nav with a raised
centre "More" sheet; role-scoped; Storefront↔Admin cross-links. *Rule: customer
and admin share one shell component; don't build two.*

**F2. One user-facing `/login`; everyone lands on `/dashboard`.**
Customers, staff, and admins all sign in at the same route and reach their panel
from the app. *Rule: no separate admin login page; fix Fortify `home` to a real
route.*

**F3. Staff are promoted from existing active users (by email); they keep end-user access.**
*Rule: staff are a role layered on a normal account, not a separate user type.*

---

## G. Money & schema pragmatics (documented deviations = rules)

**G1. No first-class payments table — top-ups are metadata-driven.**
`initialize()` embeds `user_id` + a `NAARA-{uuid}` reference in provider
metadata; after signature verification the webhook amount is authoritative and
lands as a `wallet_transactions` row. *Rule: if a payments ledger is wanted,
add it as its own table+migration; don't retrofit silently.*

**G2. Integrate providers via Laravel `Http` (fake-testable), not vendor SDKs, unless the SDK earns its dependency.**
Airalo was integrated over `Http` (OAuth2, token cached ~23h) instead of the
official SDK — testable, no unpinned dependency, swap isolated to one service.
*Rule: prefer `Http::fake`-able transport; money-critical field mapping is
unaffected by the choice.*

**G3. Document every deviation from the literal schema at the point of deviation.**
Examples we recorded: `two_factor_secret` (Fortify) instead of a custom
`twofa_secret`; `settings.value` as `longText` + `encrypted:array` (can't be a
native JSON column when encrypted-at-rest); provider stored as string not enum
(lane routing). *Rule: a deviation without a written reason is a bug; with one
it's a decision.*

---

## H. Process rules that paid off

**H1. Build one module at a time, update `PROGRESS.md` after every task, never start a module whose dependency isn't passing.**
(From CLAUDE.md — restated because it worked: 21 modules, zero rework spirals.)

**H2. Test money paths on sandbox keys only; real keys go in last, via the admin panel, after hardening.**

**H3. Keep the model identifier and any internal session/model IDs out of committed artifacts.**
Model config is env-driven (`ANTHROPIC_MODEL`, e.g. `claude-sonnet-5`) — no
hard-coded model string in the repo.

**H4. Every external API call is a queued job; every webhook is HMAC-verified + idempotent; wallet moves are atomic with balance_before/after.**
(Core money rules — restated as the non-negotiable floor for any new money
surface, including the future NaaraCredits loyalty ledger.)

---

## Still deferred (not built — track in the next blueprint)

- **Section 32 phase 2:** NaaraCredits loyalty ledger (money-touching — build with
  WalletService rigor), product reviews (reuse the star-rating kit), Claude
  first-line live-chat, full i18n coverage, multi-currency beyond USD/NGN.
- **Referral profit-share ENGINE** (claim-before-pay reward on first purchase) —
  only the referral display is built, not the reward listener.
- **Fortify `verified` gate** on customer routes (currently only `auth`).
