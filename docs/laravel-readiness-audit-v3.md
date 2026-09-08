# Laravel Readiness Audit — Round 3 (2026-09-07)

> Synthesis of the fresh 6-agent parallel audit run against blueprints v1–v4
> (32 domains), cross-checked against `laravel-readiness-audit.md` (Part 1)
> and `laravel-readiness-audit-v2.md` (Part 2) to filter out anything already
> known. This doc covers only what changed since those two: items already
> fixed here, and NEW findings not previously logged. It does not repeat the
> full 32-domain table — see the two prior docs for that.

## Shipped this round (PR #38, merged/mergeable to `main`)

1. **Fail-open → fail-closed webhook verification.** `GetatextWebhookController`,
   `WhatsAppWebhookController`, `AppBuildWebhookController` all accepted
   unsigned requests when their secret was unconfigured — confirmed
   live-exploitable (`GETATEXT_WEBHOOK_TOKEN`, `WHATSAPP_APP_SECRET`,
   `APPEXPORT_CI_SECRET` are all blank in `.env`). Now `abort_if($secret ===
   '', 401)` before the signature check, matching the existing
   `SmsInboundWebhookController` reference pattern.
2. **`POST /register` / `POST /forgot-password` had no rate limit.** Fortify's
   own `limiters` config only wires `login`/`two-factor`/`passkeys`. New
   `ThrottleUnprotectedAuthRoutes` global middleware: 5/hour/IP on register,
   5/hour per email+IP on password reset.
3. **Stale storefront pricing cache.** `EsimCatalogue::flush()` was missing
   from `RecomputePlanPricingJob` and `Admin\Pricing::savePlan()` — a global
   markup change or manual price override could leave the "from $X" teaser
   grid stale indefinitely. Both now flush it.

## Confirmed duplicates (already logged, not re-fixed here)

- Data-retention layer (no `retained_records`/`retain_until`) — `-v2.md` §5.
- Activation/retention nudge job (no `activated_at` + scheduled job) — `-v2.md` §4.
- `gitleaks` missing from CI secrets-scanning — `-v2.md` §3.
- Enterprise SAML — deliberately deferred, `-v2.md` §2.

## New findings — not yet fixed, prioritized

### 1. Admin 2FA is opt-in, not mandatory-by-default — RESOLVED, no action
`config('admin.require_2fa')` = `(bool) env('ADMIN_REQUIRE_2FA', false)` —
confirmed the shipped default is OFF. A fresh admin (including the seeded
`DefaultAdminSeeder` super_admin) can use the full admin panel with zero
2FA enrolment unless the owner explicitly sets `ADMIN_REQUIRE_2FA=true`.
**Owner decision (2026-09-07): keep it opt-in as-is.** No code change.

### 2. Provider purchase calls run synchronously, not queued — RESOLVED, docs corrected
`Checkout::purchase()` and `GetNumber::order()`/`getLine()` call the eSIM/
number provider's HTTP API directly inside the Livewire action, not as a
queued Horizon job — this contradicted `laravel-readiness-audit-v2.md` §1's
claim that "every external/provider call is a queued job." The sync path
is wrapped in the circuit breaker + orphan-charge/refund guard, so it's not
unsafe. **Owner decision (2026-09-07): the sync+breaker+refund pattern is
intentional — correct the doc, don't refactor to async.**
`laravel-readiness-audit-v2.md` §1 has been corrected accordingly.

### 3. Sentry DSN is blank — error tracking not actually wired
`.env.example` has `SENTRY_LARAVEL_DSN=` and `ErrorLogger` mentions Sentry
as a parallel capture path, but no DSN is configured anywhere in this
environment, so production exceptions are only ever visible in the
in-app `ErrorLogViewer`, never in an external alerting tool.
**Action**: get a Sentry (or equivalent) DSN from the owner and set it —
no code change needed, this is a config/ops gap, not a build task.

### 4. No output-side screening on the AI support agent's replies
`NaaraCareAgent::respond()` scopes what the model can SEE (via
`SupportTools`, user-scoped, no cost/profit/secrets) but nothing screens
what it SAYS before `extractText($content)` reaches the user — no check
against promising something the system didn't actually grant (e.g. "I've
refunded you" when no refund tool ran), no profanity/safety filter, no
length/format guard. Input-side guarding is solid; output-side is absent.
**Recommended fix** (small, scoped): a lightweight `SupportReplyGuard` that
runs after `extractText()` — strip/flag replies claiming an action verb
("refunded", "cancelled", "credited") unless a matching tool actually ran
this turn, and cap reply length. Not started.

### 5. Capacitor app is configured but the Capacitor packages aren't installed
`capacitor.config.json` exists and is fully configured (appId, splash
screen, Android/iOS settings), but `package.json` has no `@capacitor/core`,
`@capacitor/cli`, or platform packages at all — `npx cap sync` would fail
today. Either the mobile-app-wrapper track hasn't started yet, or the
dependency install step was missed.
**Needs an owner decision**: is the Capacitor mobile wrapper an active
track right now? If yes, `npm install @capacitor/core @capacitor/cli
@capacitor/android @capacitor/ios` + `npx cap add android/ios` is the
actual next step; if it's intentionally on hold, no action needed.

### 6. Dead `users.timezone` column
Confirmed: the `timezone` column exists (migration
`2026_07_21_210000_add_profile_fields_to_users.php`) and is `$fillable` on
`User`, but nothing reads it anywhere — no profile UI field to set it, no
date-formatting code that applies it. It's inert.
**Low priority.** Either wire it into the profile page + apply it to
user-facing date/time rendering (real feature), or drop the column
(cleanup) — not worth doing until one of those is actually requested.

## Status

1. ~~**#1 admin 2FA default**~~ — resolved, owner kept it opt-in, no action.
2. ~~**#2 sync-vs-queued provider calls**~~ — resolved, docs corrected.
3. ~~**#4 output-side AI reply guard**~~ — shipped (`SupportReplyGuard`).
4. **#3 Sentry DSN** — zero code, just needs the owner to hand over a key.
5. **#5 Capacitor packages** — only matters if the mobile wrapper track is
   currently active.
6. **#6 dead timezone column** — cosmetic, defer indefinitely.
