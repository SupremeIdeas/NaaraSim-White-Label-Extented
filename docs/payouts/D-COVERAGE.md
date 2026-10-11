# Addendum D — what is built, where, and what is deliberately open

Status of each gap in `blueprints/D-Gap-Closure-Addendum.md` (Part 2). "Where" is the code a reviewer can read.
Settings named below are all editable in **Admin → Payouts → All payout settings** (super admin).

| ID | Sev | Status | Where / how it is proven |
|---|---|---|---|
| G-01 timeout then double pay | S1 | Done (earlier) | `PayoutService::send` intent log + `PayoutReconciler`; `PayoutEngineSafetyTest` |
| G-02 inline send in a transaction | S1 | Done (earlier) | No provider call inside a DB transaction; test asserts transaction level 0 |
| G-03 DB restore re-sends | S1 | Done | `payouts:post-restore-check`, `PostRestoreCheck`, `RUNBOOKS.md` §Restore; `PayoutHardeningTest` |
| G-04 stuck unique job lock | S1 | Done | `SendPayoutJob::$uniqueFor=900`, `$timeout=40`; provider HTTP timeouts 25s/5s (settings) |
| G-05 destination snapshot | S1 | Done | `DestinationSnapshot`; a changed/deleted account is **not sent**, goes to manual review; accounts with open requests cannot be removed |
| G-06 free-payout threshold | S1 | Done (earlier) | `committedCount` + `PayoutAdmission` |
| G-07 money invariants | S1 | Done | `payouts:invariants-check` (nightly) + Admin → Payout health; 8 checks incl. accounting balance |
| G-08 webhook dedupe | S1 | Done | `payout_webhook_events` unique(provider, event id); duplicate = 200 no-op; raw payload encrypted, pruned after N days |
| G-09 returned after paid | S1 | Done | status `returned`; `PayoutService::markReturned` (webhook event `returned` or admin with evidence); re-credit once; destination flagged, locked on 2nd return |
| G-10 account takeover | S2 | Done (default OFF) | Step-up (authenticator or emailed code) for add/change account + global-rail withdrawal; "This wasn't me" freeze; cancel window + send delay for new destinations; rate limits |
| G-11 clawback / maturity | S2 | **Partly open** | Maturity window exists (`payouts.maturity_days`, default 3; the addendum suggests 7 for card-funded earnings — your decision). **Clawback is NOT built**: the five earnings ledgers refuse a negative balance and no code reverses earnings when the source order is refunded. Needs an owner decision on debt handling, then a ledger change. See "Open items". |
| G-12 erasure with money owed | S2 | Done | `PayoutErasureGuard`: erasure refused while a payout is open or a balance remains; destinations reduced to masked data + fingerprint |
| G-13 provider outage | S2 | Done | Guardian defers (`provider_unhealthy`), never redirects; outage alert after N minutes (setting) |
| G-14 accounting trail | S2 | Done | `payout_accounting_entries` (append-only, balanced per group, idempotent); month-end CSV |
| G-15 settlement reconciliation | S2 | Done for CSV; **API pulls open** | `SettlementReconciler`, `payouts:reconcile-settlement`, Admin → Payout health. No provider statement API is called until each endpoint is confirmed from real docs |
| G-16 precision + FX | S2 | Done | `PayoutMoney` (currency decimals, half-even), `FxGuard` sanity band, quotes refused when the rate jumps; the locked local amount is what is sent |
| G-17 reference registry | S2 | Done | `PayoutReference`: `ns{env}{id}-{random}` lowercase, <=32 chars, no `:`; gateways send `wireReference()`; webhooks match either reference. **Verify each provider's real charset/length in sandbox** (see GO-LIVE) |
| G-18 corridor economics | S2 | Done | `payout_corridors.fixed_fee_usd_est` (admin only) + `payouts.max_fee_ratio_pct`; plain-words minimum shown to the user |
| G-19 secrets / env | S2 | Done | `PayoutEnvGuard` + `PAYOUT_ENV`; live key refused outside live mode, test key refused inside; `.env.example` |
| G-20 PII | S2 | Done | Encrypted account number/details/snapshots/raw webhooks; blind index with dedicated rotatable `PAYOUT_FP_KEY` + `payouts:reindex-fingerprints`; masked exports |
| G-21 tax hooks | S2 | Done (hooks only) | `payee_tax_profiles`, optional gate (OFF), `payouts:annual-summary`. Not tax advice; needs your CPA |
| G-22 roles | S2 | Done | Staff scopes `payouts.review` / `payouts.finance`; settings, trust overrides, kill switches stay super-admin; optional dual control |
| G-23 fail-closed heartbeat | S2 | Done | Guardian refuses to auto-approve if its sweeper/metrics jobs stalled (setting, default ON). Queue-lag guard: **not built** (needs queue-age metric; low risk because sends are idempotent) |
| G-24 rate limits | S2 | Done | add-account/hour, withdraw/minute and /day (settings; 0 = off) |
| G-25 observability | S3 | Mostly | Alerts for every S1/S2 condition; `RUNBOOKS.md`; correlation-id log context **not** added |
| G-26 deploy safety | S3 | Done | Additive migrations only, flags default OFF, runbook notes |
| G-27 plan B | S3 | Done | `manual_external` rail + `PROVIDER-PLAN-B.md` |
| G-28 white-label | S3 | See PROGRESS.md | Ported at the owner's explicit request on 2026-10-02 |
| G-29 tests | S3 | Done | Four hardening test files + settings/health tests; gateway capability test |
| G-30 owner checklist | S3 | Done | `OWNER-CHECKLIST.md` |

## Open items (need you, not code)
1. **Clawback policy (G-11)** — decide: negative balance as debt offsetting future earnings (recommended by D) vs. block withdrawals until the dispute window closes. Then the five earnings ledgers need a `clawback` entry type and a hook from the refund/chargeback flow.
2. **Provider facts (Part 7)** — researched on 2026-10-02, results in `PROVIDER-RESEARCH.md`: Paystack reference/verify/balance/country now confirmed from its docs (one sandbox body-shape call left); Stripe, Payoneer and Grey stay blocked for the reasons listed there. Nothing was guessed. Paystack: confirm `GET /transfer/verify/{reference}`, `GET /balance` units, `GET /country`, and the allowed reference charset/length. Flutterwave/PayPal/Cryptomus/Stripe: no verified status lookup exists, so an unknown outcome there always goes to a human (by design).
3. **Payoneer / Grey / Stripe corridors** — still blocked on your account access and the real docs.
4. Decisions listed in `OWNER-CHECKLIST.md`.
