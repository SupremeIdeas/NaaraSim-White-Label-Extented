# NAARA-PAYOUT-GLOBAL — ADDENDUM D: Gap-Closure & Hardening Pack
Final addendum. Closes cross-document conflicts and every gap found in a full review of the main blueprint + Addenda A (Funding Radar), B (Rail Guide), C (Payout Guardian) against the 2026-09-22 codebase. Read `00-START-HERE.md` first for build order. Claude Code: where documents disagree, THIS document wins, then C, then B, then A, then the main blueprint.

---

## PART 1 — Conflicts between the documents (resolved)

| # | Conflict | Resolution |
|---|---|---|
| C1 | Main Phase 5 (cooling-off, name-match, caps, KYC-first-global) overlaps Guardian gates G4–G7 | Guardian gates are the ONLY implementation. Main Phase 5 is reduced to: country deny list (shared with Guardian G1), `SanctionsScreeningInterface` null stub, settings. Do not build any of these twice. |
| C2 | Three places define "would this person be paid / passes eligibility": `EarningsPayoutRunCommand`, Radar "Due at Next Sweep" (A), Guardian gates (C) | One class `App\Services\Payouts\PayoutEligibility` (extracted, no behaviour change) exposing `check(User|Earner, amount): EligibilityResult` with the shared predicates. Earnings-run, Radar and Guardian all call it. Guardian adds its own extra gates on top, never re-implements these. |
| C3 | Radar (A) treats "Committed Unsent" as pending/approved/awaiting_funds; Guardian (C) adds `review_state` (deferred, manual_review) | Committed Unsent = status in `pending, approved, awaiting_funds` regardless of `review_state`. Radar adds a sub-breakdown by `review_state` (auto_pending / deferred / manual_review) so the owner sees how much is waiting on humans vs time vs float. |
| C4 | "Due at Next Sweep" (A) assumed one daily sweep; with Guardian, user-initiated requests are evaluated within about a minute and the earnings-run only CREATES requests at 04:45 | Radar: "Due at Next Earnings Run" (requests not yet created) + "Expected Auto-Approvals" (Committed Unsent whose `review_state` is `auto_pending`). Rename in UI and docs. |
| C5 | Main Phase 4 introduces status `awaiting_funds`; Guardian G9 also defers to float | Single owner: Guardian G9 sets `status=awaiting_funds` + `hold_reason=float_short`. `PayoutService::send()` keeps a defensive float check too (belt and braces) but never fails the user for it. |
| C6 | Addendum B enforces "global rail only when no fast rail" in the UI + `PayoutAccountService`; Guardian G2 checks it again | Keep both on purpose: B blocks at account creation (server-side), G2 re-checks at payout time (corridor availability can change after the account was added). If the situation changed (fast rail now exists), Guardian does NOT reject — it approves normally and sends the user a "faster option now available" notification. |
| C7 | Main blueprint says "never mark paid on submit"; existing Stripe gateway returns synchronous paid for Transfers | Stays as is: Stripe Transfer success = final `paid` (it is synchronous). Payoneer/Grey = `processing` until webhook/lookup. Encode as a gateway capability flag `confirms_synchronously` (true for Stripe) and test it. |
| C8 | Main doc lists provider `payoneer`/`grey` in the webhook allow-list only; Addendum C adds lookup | Each new gateway must ship: webhook, `lookupTransfer`, `cancel` (if supported), signature verification, corridor seed, sandbox fixtures. A gateway missing any of these cannot be enabled (enforced by a `GatewayCompletenessTest`). |

---

## PART 2 — Gap register (every gap found, with the fix and where it is built)

Severity: **S1** money-loss or double-pay risk · **S2** compliance/fraud/serious operational · **S3** quality/maintainability.

| ID | Sev | Gap | Fix (spec in Part 3 unless noted) | Build in |
|---|---|---|---|---|
| G-01 | S1 | Timeout after provider accepted → refund + payout (double pay) | Addendum C §5-A (intent log, unknown state, lookup) | C-A2 |
| G-02 | S1 | Inline `send()` inside DB transaction in referral/staff autopilot | Addendum C §5-F | C-A1 |
| G-03 | S1 | DB restored from backup after payouts were sent → requests look unsent → re-send | D-3.9 post-restore protocol | D-H1 |
| G-04 | S1 | `SendPayoutJob` is `ShouldBeUnique` with no `uniqueFor`: a killed worker leaves the unique lock behind and later dispatches for that request are silently dropped (stuck payout) | D-3.2 job hardening | C-A1 |
| G-05 | S1 | No destination snapshot: user edits/deletes payout account between request and send, so money could go to a different destination than the one evaluated | D-3.1 destination snapshot | C-A1 |
| G-06 | S1 | Free-payout threshold counts only paid payouts | Addendum C §5-B | C-A3 |
| G-07 | S1 | No automated proof that ledgers stay consistent (paid ⇒ hold consumed once; failed ⇒ hold released once) | D-3.8 money-invariants checker | D-H1 |
| G-08 | S1 | Webhooks deduped only by status transitions; `webhook_logs` has no unique provider event id | D-3.3 webhook hardening (event-id dedupe table, raw payload retention, replay tolerance) | D-H1 |
| G-09 | S1 | Money returned by the bank/provider AFTER we marked `paid` (returned/bounced payout) has no policy or state | D-3.4 post-paid return handling | D-H2 |
| G-10 | S2 | Account takeover: attacker with a session adds an account and withdraws | D-3.5 step-up auth + user-cancel window | C-A4 |
| G-11 | S2 | Earnings later clawed back (refund/chargeback of the source purchase) after payout | D-3.6 clawback + maturity policy | C-A4 |
| G-12 | S2 | Account erasure with open payouts or balance | D-3.10 erasure interplay | D-H2 |
| G-13 | S2 | Provider outage: no rule on failover with funds in flight | D-3.7 failover policy | D-H2 |
| G-14 | S2 | No double-entry/accounting trail for payout expense, FX gain/loss, provider fees, float movements | D-3.11 payout accounting ledger + exports | D-H3 |
| G-15 | S2 | No daily reconciliation against provider statements | D-3.12 settlement reconciliation | D-H3 |
| G-16 | S2 | FX: rounding/minor-unit rules undefined, stale FX rate not guarded | D-3.13 money precision + FX freshness guard | C-A1 |
| G-17 | S2 | Reference format/length limits per provider unknown; cross-provider collision | D-3.14 reference registry | main P2/P3 |
| G-18 | S2 | Provider minimums/fixed fees can make small payouts uneconomic or fail | D-3.15 corridor economics guard | main P1 |
| G-19 | S2 | Secrets, key rotation, IP allow-listing, sandbox/live separation | D-3.16 | main P2 |
| G-20 | S2 | PII: bank details, fingerprints, retention vs erasure, masking in exports | D-3.17 | main P1 + D-H2 |
| G-21 | S2 | US-LLC tax reporting for payees (forms, withholding, annual export) unaddressed | D-3.18 hooks + owner action | D-H3 |
| G-22 | S2 | RBAC: who may approve, fund, change settings, export | D-3.19 roles + segregation | C-A6 |
| G-23 | S2 | Guardian breakers/metrics rely on cron; stale cron could let bursts through | D-3.20 fail-closed heartbeat rule | C-A5 |
| G-24 | S2 | Rate limiting / abuse on withdraw, add-account, guide endpoints | D-3.21 | G3/C-A6 |
| G-25 | S3 | Observability: no correlation id, no alert severity matrix, no runbooks list | D-3.22 | D-H3 |
| G-26 | S3 | Deploy safety: migrations, in-flight jobs, horizon termination, HTTP timeouts vs worker timeout | D-3.23 | D-H1 |
| G-27 | S3 | Provider onboarding risk (Payoneer approval not guaranteed) → no Plan B | D-3.24 Plan-B matrix | Phase 0 |
| G-28 | S3 | White-label children: avoid breaking shared migrations/config | D-3.25 | every phase |
| G-29 | S3 | No contract/chaos test matrix or definition of done | D-4, D-5 | D-H4 |
| G-30 | S3 | Legal/terms/privacy/owner checklist not captured | D-6 | Phase 0 |

---

## PART 3 — Specifications

### D-3.1 Destination snapshot (G-05)
Add to `payout_requests`: `destination_snapshot json` = `{provider, rail, method, country, currency, account_name, identifier_masked, recipient_ref, fingerprint, payout_account_id, captured_at}` written at creation inside the same transaction as the hold. Rules: `PayoutService::send()`, Guardian G2/G6/G7 and every gateway `sendTransfer` read the SNAPSHOT recipient ref, never the live `payout_accounts` row. If the live account was changed/deleted after the snapshot, Guardian logs `account_changed_after_request` and, if the user changed the destination, sends the request to `manual_review` (could be takeover). Block hard-deleting a `payout_accounts` row that is referenced by any non-final request (soft-delete only).

### D-3.2 Job hardening (G-04)
`SendPayoutJob`: add `uniqueFor` (e.g. 900s) so a lock cannot outlive a dead worker, `$timeout` slightly above the provider HTTP timeout, `failed(Throwable)` that marks the open `payout_provider_calls` row `unknown` and alerts. `EvaluatePayoutRequestJob`: same pattern (`uniqueFor` 120s). Provider HTTP clients: connect timeout 5s, total timeout 25s (configurable), no automatic HTTP retries on money-moving POSTs; retries allowed only on GET/lookup calls. Horizon `payouts` supervisor `timeout` > 30s, `tries` 1. On shared hosting the drain command `--timeout` must also exceed 30s.

### D-3.3 Webhook hardening (G-08)
- New table `payout_webhook_events`: `id, provider, provider_event_id, request_reference null, event_type, received_at, processed_at null, outcome string null, payload_hash, raw_payload (encrypted, TTL 90 days)`, **unique(provider, provider_event_id)**. Duplicate delivery → return 200 and do nothing. If a provider supplies no event id, derive `sha256(provider|reference|status|amount|provider_timestamp)`.
- Signature verification with constant-time compare; timestamp tolerance (default 5 min) when the provider signs a timestamp; reject unsigned in production.
- Out-of-order handling: a late `processing` after `paid` is ignored; `paid` after `failed` or `failed` after `paid` → existing `payout_confirm_conflict` flow (freeze + alert); never auto-resolve money in either direction.
- Respond fast (< 2s): verify, store event, enqueue processing, return 200.
- Optional IP allow-list per provider (setting), secret rotation supports two active secrets during rotation.
- Redact PII in logs; keep `WebhookLog` for backward compatibility.

### D-3.4 Post-paid returns (G-09)
Providers/banks can return money after a payout was reported delivered (wrong details, closed account, compliance reject). Add terminal-adjacent state handling:
- New status `returned` (final) reached only from `paid`, via provider webhook/lookup/admin action with evidence.
- Policy default (owner can change): returned funds are re-credited to the user's ORIGINAL earnings bucket as a ledger entry `payout_returned:{reference}` (idempotent), the payout account is flagged `needs_attention`, the user is notified, and the payout counts against the free-payout threshold only once (don't punish). Repeated returns (≥ 2) on one destination → account locked pending review.
- Radar and invariants checker must know `returned` (paid ⇒ returned nets to zero).
- Never auto-resubmit a returned payout.

### D-3.5 Step-up auth and user cancel window (G-10)
- Adding or changing a payout account, and every global-rail withdrawal request, requires step-up: 2FA (Fortify) if enabled, else email OTP. Reuse existing OTP/notification infrastructure.
- Email + in-app notification on every payout request and on account changes with a "This wasn't me" link that: freezes payouts for the user, cancels cancellable requests, forces password reset, alerts admin.
- **Cancel window**: while a request is `pending`/`approved` and no provider call exists (`payout_provider_calls` empty), the user can cancel (hold released, idempotent). Guardian adds a configurable `payouts.send_delay_minutes` (default 10 for global rail, 0 for local) between approval and send for NEW destinations, implemented as `SendPayoutJob::dispatch()->delay()`. Cancel after `submitted` is impossible (use provider cancel API via admin only).

### D-3.6 Clawbacks and maturity (G-11)
- Verify how each earnings ledger handles refunds/chargebacks of the originating order. Add ledger entry type `clawback` (negative) per bucket. A bucket balance may go negative (debt) and offsets future earnings; no cash recovery from users; the user sees "adjustment" with a reason. If a ledger forbids negatives today, extend it with a separate `debt` column instead of letting `balance()` go negative silently.
- Maturity window `payouts.maturity_days` default 7 for earnings sourced from card/Stripe payments (chargeback exposure is longer than any practical delay; the owner accepts residual risk). Earnings from an order with an OPEN dispute are excluded from withdrawable balance until resolved. Show "available" vs "maturing" amounts to the user.
- Guardian soft signal uses the same maturity data (Addendum C), not a second calculation.

### D-3.7 Provider failover policy (G-13)
- Never move an in-flight request to another provider automatically (double-pay risk).
- New requests: if a provider's circuit is open, the user's request is held in `deferred` (`provider_unhealthy`) and retried every 5 min, NOT redirected, because the user's destination is bound to that rail. Show "temporary provider issue — you're queued, no action needed".
- If an outage exceeds `payouts.outage_alert_minutes` (default 60): admin alert; admin may bulk-notify affected users.
- Switching rails is always a user action via the guide (new destination → new cooling-off).
- Provider health flips closed→open on failure-rate (Addendum A `rail_failure_rate`) and closes after N consecutive successes on a canary lookup (read-only), not on a real payout.

### D-3.8 Money-invariants checker (G-07)
`payouts:invariants-check` nightly (plus on-demand button), read-only, writes `payout_invariant_runs` and alerts on any violation (`invariant_violation`, S1). Invariants:
1. Every `paid` request has exactly one consumed hold for its reference in the source ledger and no release.
2. Every `failed`/`reversed`/rejected request has its hold released exactly once and no consumption.
3. No request has both a consumption and a release.
4. Σ(held amounts for non-final requests) == Σ(current hold entries unconsumed) per bucket.
5. No two `payout_requests` share a `reference`, and no two share a provider reference per provider.
6. For each `payout_provider_calls` with state `confirmed`, the request is `processing/paid/returned`; none in `intent/submitted/unknown` older than the grace window.
7. `returned` requests net to zero against their `paid` payout.
8. Float ledger: `payout_float_balances` equals opening + Σ movements (± recorded sync variance).
Each check lists offending ids and links to the request. Zero tolerance for 1–3 and 5.

### D-3.9 Backup/restore protocol (G-03)
After ANY database restore or rollback: (1) put the app in maintenance mode, (2) set `payouts.auto_approval.enabled=false` and `payouts.enabled=false`, (3) run `payouts:post-restore-check` which, for every request not in a final state AND every request finalised since the backup time, looks up the provider by reference and reports mismatches, (4) apply results through the normal idempotent `confirm()/fail()`, (5) only then re-enable. Provider-side idempotency is the second safety net: Payoneer uses our `client_reference_id`; Stripe idempotency keys expire after about 24 hours so Stripe checks must use the reference stored as transfer metadata/`transfer_group`, never keys alone. Document in `docs/payouts/RUNBOOK-restore.md`. Add a quarterly restore drill to the go-live checklist.

### D-3.10 Erasure interplay (G-12)
`AccountService::erase()` must refuse (or queue for later) when the user has any non-final `payout_request` or a non-zero earnings balance, telling the user what to resolve. Financial records follow the existing retention window (already stated in the code comment); payout destination details beyond the masked identifier and fingerprint are purged at erasure, snapshots keep only masked data. Fingerprint HMAC rows are deleted at purge, not at erasure, to keep duplicate-destination protection during the retention period.

### D-3.11 Payout accounting ledger (G-14)
Table `payout_accounting_entries` (append-only, double-entry style): `id, entry_group (uuid), payout_request_id null, float_movement_id null, account_code, direction (debit|credit), amount_usd decimal(18,4), currency, amount_local decimal(18,4), fx_rate decimal(18,8), occurred_at, memo`. Accounts: `earnings_liability`, `payout_in_transit`, `provider_float_{provider}`, `payout_expense_rewards`, `fx_difference`, `provider_fees`, `platform_fee_income`. Posting rules: on hold→(Dr earnings_liability / Cr payout_in_transit); on paid→(Dr payout_in_transit / Cr provider_float, plus fee/FX lines); on failure/reversal→ reverse the group; on returned→ reverse the paid group. Owner-facing monthly export (CSV) per provider and per corridor; admin-only. Checker invariant 4/8 consume this table.

### D-3.12 Settlement reconciliation (G-15)
Daily job `payouts:reconcile-settlement`: import provider statement/transaction lists via API (Payoneer, Grey, Stripe balance transactions) or admin-uploaded CSV where no API exists; match by our reference/provider reference/amount/date; produce `payout_reconciliation_runs` + `payout_reconciliation_items` (matched, missing_at_provider, missing_in_system, amount_mismatch, fee_unrecorded). Mismatches alert (S2). Auto-fix is never allowed; items resolved by an admin with a note. Provider fees discovered here update `payout_accounting_entries`.

### D-3.13 Money precision and FX freshness (G-16)
- Money in `decimal` columns only; arithmetic with `bcmath` (scale 8) and a `Money` value object; USD stored to 4 dp internally, 2 dp for user-visible USD.
- Currency minor-unit table in config (`payouts.currency_decimals`): default 2; JPY/KRW/UGX/RWF-style zero-decimal and 3-decimal currencies listed explicitly; amounts rounded half-even to the CURRENCY's decimals at the moment the local amount is locked; the local amount, not a re-derived value, is what is sent.
- FX freshness guard: if the newest rate for a currency is older than `payouts.fx_max_age_hours` (default 24) the corridor refuses new quotes (user sees "rates updating — try again soon") and Guardian defers existing requests whose quote expired; an `fx_stale` alert fires. Rates from a single source get a sanity band vs the previous rate (default ±15%); outside → quote refused + alert.
- Display and send use the same rounding function (test).

### D-3.14 Reference registry (G-17)
`PayoutReference::make(PayoutRequest, provider)` is the only generator. Format `NS{env}{base36 id}-{random4}` (≤ 32 chars, `[A-Z0-9-]` only) unless a provider requires otherwise (confirm each provider's max length/charset from its docs at build and encode in the gateway class constants). Existing prefixed references (`mwd:uuid`, etc.) stay for legacy rows; the provider-facing reference is derived and stored in `provider_reference` (unique per provider). Never reuse a reference after cancel/fail; a retry-by-admin creates a new request.

### D-3.15 Corridor economics guard (G-18)
Seed `payout_corridors.min_usd` from each provider's real minimum payout (from docs/sandbox — never guess). Add `fixed_fee_usd_est` (admin-only) and refuse corridor payouts where `fixed_fee_usd_est / amount > payouts.max_fee_ratio` (default 5%). The guide shows the resulting minimum to the user in plain words. Guardian re-checks at evaluation.

### D-3.16 Secrets and environments (G-19)
Keys only in env/`ProviderKeys`, never in settings tables or logs; separate sandbox and live credential sets with a `PAYOUT_ENV` guard that refuses to start a live gateway while `APP_ENV=local/staging` and vice versa; `EnvCheckExampleCommand` + `.env.example` entries; Payoneer/Grey IP allow-listing documented in the runbook (VPS/Cloudways static IP); rotation runbook with dual-secret webhook window; admin screen shows only "configured/not configured" and last-verified time.

### D-3.17 PII and data protection (G-20)
`payout_accounts.details` and snapshots use Laravel `encrypted` casts; searchable lookups via HMAC blind index (key = dedicated `PAYOUT_FP_KEY`, rotatable with a re-index command); admin lists and CSV show masked identifiers only; full identifiers are never logged; webhook raw payloads encrypted with 90-day TTL; decision/evidence JSON must not contain full account numbers. Privacy policy and terms updated (D-6). Data export (GDPR access request) includes payout history without other users' data.

### D-3.18 Tax and reporting hooks (G-21) — owner needs a CPA; engineering only builds hooks
The platform is a US LLC paying people worldwide. US information-reporting and withholding rules can apply to payments to US persons and to non-US persons, and thresholds change, so this is a question for a US tax professional, not code. Build only: `payee_tax_profiles` (user_id, tax_country, form_type null [W-9/W-8BEN/other], form_status, collected_at, provider_collected bool), a nullable gate `payouts.tax_form_required_over_usd` (default OFF), a yearly export `payouts:annual-summary {year}` (per payee: total USD paid, country, provider) as CSV for the accountant, and note that Payoneer/Stripe may collect their own tax information from payees. Do not claim compliance in product copy.

### D-3.19 Roles and segregation (G-22)
Spatie roles: `payout_reviewer` (review queue approve/reject, view decisions), `finance` (record float top-ups, view radar/reconciliation/accounting exports), `super_admin` (settings, kill switches, trust overrides, provider keys status). Rules: reviewers cannot change settings; the user who records a top-up cannot also approve payouts above the dual-control USD threshold (default off, setting) ; every settings change writes `Auditor::log` with old/new values and notifies all super_admins; CSV exports require `finance` or `super_admin` and are audited.

### D-3.20 Fail-closed heartbeat rule (G-23)
Before any auto-approval Guardian checks `JobHeartbeats` for `payouts:guard-metrics` and `payouts:guard-sweep`: if either is overdue by more than 3× its interval, breakers are treated as TRIPPED (everything routes to `manual_review`) and `rail_stale_snapshot`/`guardian_stale` alerts fire. Queue lag guard: if the `payouts` queue backlog age exceeds `payouts.max_queue_lag_seconds` (default 300) Guardian defers approvals (no new sends piling onto a stalled worker).

### D-3.21 Rate limiting and abuse (G-24)
Throttle: withdraw submit (3/min/user, 10/day/user soft cap), add/change payout account (5/hour/user), guide country change (30/min), OTP requests per existing rules; per-IP limits for unauthenticated guide/country-list endpoints; Livewire actions server-validated (never trust UI disabling); CAPTCHA/turnstile only if abuse is observed (setting). Log throttle hits as a Guardian soft signal.

### D-3.22 Observability and runbooks (G-25)
- Correlation: every log line, job, decision, provider call and webhook for a payout carries `payout_reference` (use `Log::withContext`).
- Alert severity matrix (reuse `AlertAdminJob`): S1 = `payout_hold_mismatch`, `invariant_violation`, `payout_confirm_conflict`, unresolved `unknown` beyond grace → immediate; S2 = float short, breaker tripped, settlement mismatch, provider outage, stale heartbeat → within 15 min; S3 = digests. Throttle duplicates (1/hour/condition).
- Dashboard (admin): decision latency, auto-approve rate, hold rate, deferred by reason, unknown outcomes, provider success rate, time-to-paid per provider, float days of cover, invariant status, reconciliation status.
- Runbooks to write in `docs/payouts/`: top-up float; stuck `processing`; `unknown` outcome; payout conflict; returned payout; provider outage; webhook secret rotation; restore after backup; change tier limits; enable/disable auto-approval; investigate a user's payout trail; month-end close.

### D-3.23 Deploy safety (G-26)
All migrations additive and reversible; no column drops/renames in this program. Backfills as chunked commands, idempotent, resumable. Feature flags default OFF. Deploy sequence on VPS/Cloudways: maintenance not required; `horizon:terminate` after deploy; on shared hosting the drain command picks up new code automatically. Jobs killed mid-flight are covered by `unknown` handling. Never deploy a gateway change and its corridor enablement in the same release. Rollback plan per phase listed in PROGRESS.md.

### D-3.24 Plan-B matrix (G-27)
Phase 0 produces `docs/payouts/PROVIDER-PLAN-B.md`: if Payoneer partner approval is declined or delayed → use Grey (if API access granted) → else PayPal where supported → else Cryptomus (admin-only, optional) → else manual payout via admin-recorded external transfers (`manual_external` gateway: admin records proof, request moves to `paid` with evidence, fully audited, counted by Radar/accounting). The `manual_external` gateway is built in D-H2 so the product can launch the guide + radar before any provider is live.

### D-3.25 White-label discipline (G-28)
No change to the two white-label children. New tables/config are master-only. Before touching shared migrations or config keys, run the CLAUDE.md porting test. Name new settings under `payouts.*` and never reuse existing keys. Verify committed `HEAD` of each child before assuming parity. Do not touch CI triggers.

---

## PART 4 — Test and quality matrix (G-29)
1. **Money invariants**: property-style tests generating random sequences (create, approve, send, webhook paid/failed/duplicate/out-of-order, cancel, return) and asserting D-3.8 invariants after each.
2. **Chaos cases per gateway** (sandbox + HTTP fakes): timeout after accept; 5xx then success on lookup; duplicate webhook; webhook before our response is stored; webhook for unknown reference; paid-then-failed; failed-then-paid; worker killed mid-call; DB restore simulation; clock skew; provider returns changed amount/currency.
3. **Contract tests**: recorded sandbox fixtures per provider; a `GatewayCompletenessTest` (webhook, lookup, signature, corridor seed present).
4. **Concurrency**: two admins + system approving same request; 10 parallel withdraws against the threshold; two sweepers.
5. **Security**: IDOR on payout accounts/requests, rate limits, step-up bypass, webhook signature bypass, mass assignment on new models, masked exports.
6. **Performance**: radar snapshot at 50k enrolled users; guide matrix query count; sweeper with 5k pending.
7. **i18n**: en/ar/fr/sw keys present (test fails on missing keys), RTL smoke.
8. **End-to-end script** `tests/e2e/payout-sandbox.md` run per provider before enabling.

## PART 5 — Definition of Done and go-live gates
A phase is DONE only when: code merged locally with tests green (existing suite + new), PROGRESS.md updated, feature behind a flag defaulting OFF, runbook/docs updated, invariants checker green on seeded data.
Global go-live gates (all required): (1) Phase 0 owner actions complete and recorded; (2) sandbox E2E passes for each enabled provider; (3) shadow-mode report reviewed by the owner (≥ 14 days or ≥ 100 payouts); (4) invariants + reconciliation green for 7 consecutive days in sandbox/shadow; (5) restore drill executed once; (6) kill-switch drill executed once; (7) terms/privacy updated (D-6); (8) float funded and alerts verified by a forced low-float test; (9) real provider keys added last, one corridor enabled, small caps, widen over weeks.

## PART 6 — Owner checklist (non-code; Claude Code only creates `docs/payouts/OWNER-CHECKLIST.md`)
- Stripe: written confirmation that Treasury/Global Payouts and the collect-then-pay model are fine for the account; Treasury approval if used.
- Lawyer: short opinion that paying platform-funded rewards (never user deposits) is outside money transmission in the registration country/US state(s) and relevant payee countries; terms wording on right to withhold/reverse for fraud, payout timelines ("up to 14 days" statement), returned-payout policy, clawbacks.
- CPA: US reporting/withholding for payees (see D-3.18).
- Providers: Payoneer partner/API + sandbox program; Grey API + docs + sandbox; Raenest as destination-only unless they grant API access; confirm real minimums, fees, settlement times, webhook behaviour, lookup endpoints; replace the 14-day assumption with real data once available.
- Privacy policy: data shared with payout providers, retention periods, rights.
- Decide: tier limits, FX tolerance, maturity days, returned-payout policy, who holds which role (D-3.19).

## PART 7 — Known unknowns (Claude Code must NOT guess these; verify from provider docs/sandbox or ask the owner)
Grey API reference, auth, webhooks, idempotency and lookup · whether Raenest offers any partner payout API · Payoneer status codes/webhook names, `client_reference_id` limits, lookup endpoint, payee onboarding fields per country · Stripe Global Payouts availability for this LLC and its current recipient country list · exact provider settlement times · whether each earnings ledger permits clawback entries and exposes accrual timestamps · whether `payment_charges` can be linked to users (Addendum B) · actual queue drain arguments in `routes/console.php` · whether Cloudways plan has Supervisor.

---

## PART 8 — Phase map for this addendum
- **D-H1 (build with C-A1)**: G-03/04/07/08/26 — job hardening, webhook event table, invariants checker, restore protocol command + runbook, deploy notes. *Accept:* killed-worker test leaves no stuck unique lock; duplicate webhook is a no-op (unique index test); invariants fail loudly on a seeded corrupted ledger; `post-restore-check` detects a seeded stale request.
- **D-H2 (after Payoneer sandbox works)**: G-09/12/13/20/05 completion — `returned` status + re-credit, erasure guard, failover rules, `manual_external` gateway, snapshot enforcement in all gateways, PII casts + blind index. *Accept:* returned payout re-credits once and nets to zero; erasure refused with open payout; outage defers not redirects; manual gateway requires evidence note.
- **D-H3 (with Funding Radar)**: G-14/15/21/25 — accounting entries, settlement reconciliation, tax hooks/export, alert matrix, runbooks. *Accept:* every payout posts balanced entries (Σdebits = Σcredits); reconciliation flags seeded mismatches; annual export columns correct.
- **D-H4 (last)**: G-29 — run the full test matrix, produce the go-live report, finalise OWNER-CHECKLIST.md. *Accept:* all gates in Part 5 reachable and documented.
