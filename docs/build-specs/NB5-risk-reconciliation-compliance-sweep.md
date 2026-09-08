# NAARA BUILD 5 of 5: RISK, RECONCILIATION, APP STORE COMPLIANCE, FINAL SWEEP
**Run this last, after NAARA-BUILD-1 through -4 are all verified working.** Give this whole
file to Claude Code as one message.

---

## 0. HOW TO WORK
- Inspect the actual file/component before assuming its contents or editing it.
- Do not create duplicate components, tables, or systems. Reuse `AlertAdminJob` and the existing
  provider-health/alerting patterns already in place — don't build a second alert mechanism.
- Work through §1–§6 in order, then run §7 (the final regression sweep) only once everything
  else in this file is complete — §7 is a single, deliberate last pass across the whole
  platform, not another per-section checklist.
- This file is self-contained — do not open or reference any other file for context.

---

## 1. DO NOT RE-VERIFY THESE — CONFIRMED ALREADY SOLID

The following are genuinely well-built in the existing codebase and need no further work in
this build. Do not spend time re-auditing them; if you touch adjacent code, avoid disturbing
these mechanisms:

- **Wallet debit/credit concurrency:** `WalletService` already wraps every balance change in a
  `DB::transaction` with `lockForUpdate()` pessimistic row locking — double-spend and
  race-condition negative-balance risk from concurrent requests is already guarded against.
- **Idempotency:** already implemented in `WalletService`, `PaymentEvent`, the API
  `OrderController`, and the offerwall postback controller.
- **Backups:** `spatie/laravel-backup` is properly integrated and scheduled (`backup:clean`
  nightly). This is a real, working system — the only open item is operational, not code:
  confirm with Frank that someone has actually test-restored from a backup at least once, since
  a backup nobody has ever restored from is unverified, not working. Note this as an open
  operational item in `docs/PLATFORM-STATE.md` (§6 below) rather than building anything for it.
- **eSIM/SMS provider abstraction:** the multi-provider interfaces (`EsimProviderInterface`,
  `ProviderRouter`, `SmsNumberRouter`) are a sound design. §2 below asks you to confirm the
  failover *paths* actually trigger in practice — that is the right remaining scrutiny; the
  architecture itself does not need rework.

---

## 2. PROVIDER-DOWNTIME ALERTING — EXTEND COVERAGE, CONFIRM FAILOVER

`app/Console/Commands/ProvidersHealthCheckCommand.php` (scheduled every 15 minutes) currently
only monitors wallet balance for three providers — `esimgo`, `getatext`, `fivesim` — and only
alerts on low balance, not on the provider being down or erroring. The codebase has eight
distinct eSIM provider integrations (`AiraloService`, `EsimGoService`, `MontyMobileService`,
`OneGlobalService`, `QuibityService`, `ZenditService`, `GigsService`, plus the eSIM
`ProviderRouter`) and multiple SMS/number providers (Twilio, Telnyx, FiveSim) behind
`SmsNumberRouter`. If any of the five currently-unmonitored eSIM providers goes down, nothing
alerts anyone — a customer gets a failed purchase and admin only finds out from a support
ticket.

1. Extend health-checking to every wallet/API-based provider in both the eSIM and SMS/number
   stacks, not just the three wallet-funded ones — check API reachability/error-rate, not only
   balance where a balance concept even applies.
2. Confirm `ProviderRouter` (eSIM) and `SmsNumberRouter` (numbers) actually fail over to the
   next provider automatically on error, rather than just having the interface shape to do so —
   trace an actual failed-provider code path in each router and confirm a customer's purchase
   succeeds via provider #2 when provider #1 errors. Don't assume the interface implies the
   behavior; confirm it directly.
3. Surface current provider health (up/degraded/down, last successful call) on an admin
   dashboard widget — this data, if collected at all today, isn't visible anywhere except by
   reading logs.

---

## 3. ALERTING DELIVERY — CONFIRM IT ACTUALLY REACHES SOMEONE

`AlertAdminJob` is dispatched for low-balance alerts today, and §2 above extends that same
pattern to broader provider-health alerts. Before relying on it further, confirm where it
actually delivers to — email, in-app notification only (which an admin might not see for
hours), or something else. Make sure at least one delivery channel is something an admin will
see in near-real-time: push notification or WhatsApp (via the Autopilot channels built in a
prior batch), or email at minimum. An alerting system nobody sees promptly is a false sense of
security.

Separately: `SENTRY_LARAVEL_DSN` exists in `.env.example` but is blank by default, with no
evidence it's required or enforced. Recommend getting a real Sentry (or equivalent) DSN
configured in production, so uncaught exceptions — not just the specific things this codebase
remembered to build alerts for — surface somewhere admin will actually see them.

---

## 4. FINANCIAL RECONCILIATION — CONFIRM OR BUILD

Confirm first whether an admin financial-reconciliation view already exists somewhere in the
admin Livewire components — inspect thoroughly before assuming it doesn't. With nine payment
gateways, wallet ledgers, merchant reseller margins, partner payouts, and provider costs all in
play, this platform needs a single view: total money in (by gateway) vs. total wallet balances
outstanding vs. total paid out vs. provider costs incurred, over a given period — the kind of
report that catches "a webhook silently failed and this many dollars are unaccounted for"
before it becomes a support-ticket flood or an accounting problem.

If it doesn't already exist, build it against the `wallet_transactions` ledger (already real and
queryable from a prior build) rather than against any single gateway's own transaction log.

---

## 5. HOSTING CEILING — DOCUMENT THE SCALING TRIGGERS

`config/horizon.php` and `HorizonServiceProvider` are already present — a migration path off
shared cPanel hosting to a VPS with Redis and Horizon is already anticipated and half-built into
this codebase. The current setup (cron-drained database queue on shared hosting) is fine for
today's traffic, but every job waits up to ~60 seconds for the next cron tick, and every
`--max-time=50` drain window is a hard ceiling on how much queued work can clear per minute.
Neither of these is a bug — they're a real, known limit of shared hosting.

Write `docs/SCALING-TRIGGERS.md`: concrete signals (e.g. queue backlog consistently above N
jobs, WhatsApp send volume above X/day, provider polling frequency needs) that mean "move to the
VPS + Horizon path this app already supports" — so that decision gets made deliberately, ahead
of time, rather than being forced by an outage.

---

## 6. APP STORE / PLAY STORE PAYMENT POLICY

The platform sells wallet top-ups (via the nine payment gateways) which are then spent on
eSIMs, virtual numbers, merchant upgrades, and per-use wizard billing. Apple's App Store Review
Guideline 3.1.1 generally requires digital goods/services consumed inside an iOS app to go
through Apple's own In-App Purchase system rather than a third-party payment processor — with a
real, established exception for apps primarily selling real-world goods or services
(telecom/utility-style apps commonly qualify). NaaraSim sits close to that line: eSIM data and
phone numbers are plausibly "real-world telecom services," but a general-purpose wallet top-up
that can also fund the wizard's per-use fee or a merchant upgrade fee looks more like generic
digital credit — a pattern Apple's review has rejected apps for before. This has not been
addressed anywhere else in this build, and getting it wrong isn't a bug fix — it's an app
rejected or pulled after developer enrollment and a build pipeline are already paid for.

This section requires a live doc check against Apple's *current* guidelines before the iOS
build (from a prior batch) is ever submitted for review — guideline text and enforcement
patterns change periodically, so confirm current wording rather than relying on general
familiarity with 3.1.1/3.1.3:

1. Confirm Apple's current App Store Review Guideline 3.1.1 and 3.1.3 (real-world goods/services
   exceptions) directly from Apple's own current developer documentation.
2. Assess, product-line by product-line, which of NaaraSim's purchasable items plausibly qualify
   for the real-world-service exception (eSIM data, virtual numbers) versus which look like
   generic digital credit that could draw scrutiny (uncommitted wallet top-up with no immediate
   service tied to it, wizard per-use billing, merchant upgrade fees).
3. If there's meaningful risk, the safest structural fix is usually to make wallet top-up flows
   visibly and immediately tied to a specific real-world service purchase in the iOS build
   specifically (not necessarily on web/Android), rather than a free-floating "add funds" wallet
   screen — confirm this pattern against Apple's current guidance and the actual product before
   building it.
4. Do the equivalent, lighter-weight check for Google Play's Payments Policy — generally more
   permissive for this category, but confirm current policy rather than assuming.
5. Write findings and recommendation into `docs/APP-STORE-PAYMENTS-COMPLIANCE.md` before the
   iOS build is ever submitted to Apple for review. This is a one-time research cost that's far
   cheaper than a rejected submission discovered after everything else is finished.

---

## 7. FINAL END-TO-END REGRESSION SWEEP — RUN ONCE, LAST

With every prior build applied, run one coherent pass touching every money and identity path in
the platform, in a single sitting, rather than trusting each earlier build's own isolated
checklist in isolation:

1. **Money in:** a real sandbox transaction through every one of the nine payment gateways,
   confirmed via webhook (not just the redirect), wallet balance updates, ledger row written, no
   duplicate credit on a simulated webhook retry.
2. **Money out:** a payout request through at least one payout-capable gateway per supported
   region, confirming the KYC gate fires at the right point in the redesigned merchant flow, and
   that the "Recommended — fast payout" badge reflects real volume.
3. **Identity:** a full individual KYC flow and a full business KYB flow, each completed once
   end-to-end, including a deliberately-failed verification, confirming the fast-fail-to-
   manual-review path actually reaches a human queue.
4. **Uploads:** every upload surface in the app (app icon, KYC document, chat attachment, voice
   note, avatar) tested with zero Wasabi credentials configured.
5. **Native app:** if the Android build pipeline is live by this point, a real device install
   from a freshly generated build, confirming icon/splash/version reflect current admin
   settings, not a stale manual `cap sync`.
6. **Admin alerting:** deliberately trigger one low-balance provider alert and one chargeback
   (or a simulated one) and confirm both actually reach an admin promptly.
7. Log the results of this sweep in `docs/REGRESSION-SWEEP-LOG.md` with a date, so the next
   round of changes has a real baseline to compare against instead of starting from zero trust.
8. Once the sweep passes, package the corrected, fully-built application as the new baseline
   installer zip for future white-label client installs (this closes out the packaging step
   from the very first build — do it here, once, against the finished platform, not earlier
   against a partially-built one).

---

## 8. WHEN THIS PROMPT IS DONE
- [ ] `docs/PLATFORM-STATE.md` given a final pass: confirm it accurately reflects every prior build's "Done" items, that the backup test-restore is logged as a confirmed operational item (not a code task), and that any remaining "Flagged but not yet built" items are still accurate
- [ ] Provider health-checking extended beyond the 3 wallet-based providers; router failover paths traced and confirmed, not assumed; admin health widget live
- [ ] `AlertAdminJob`'s real delivery channel confirmed and made near-real-time; Sentry (or equivalent) DSN recommended for production
- [ ] Confirmed whether a financial reconciliation admin view already existed; built if it didn't
- [ ] `docs/SCALING-TRIGGERS.md` written with concrete VPS/Horizon migration triggers
- [ ] `docs/APP-STORE-PAYMENTS-COMPLIANCE.md` written and reviewed, confirmed against Apple's and Google's current guidelines, before any iOS submission
- [ ] Full end-to-end regression sweep run and logged in `docs/REGRESSION-SWEEP-LOG.md`
- [ ] Final corrected baseline installer zip packaged for future white-label client installs
