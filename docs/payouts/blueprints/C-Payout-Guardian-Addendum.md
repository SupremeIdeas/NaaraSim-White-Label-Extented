# NAARA-PAYOUT-GLOBAL — ADDENDUM C: Payout Guardian (Automated Approval Engine) + Engine Fixes
Extends: main blueprint (Phases 1–7), Addendum A (Funding Radar), Addendum B (Rail Guide). Needs main Phase 1 (corridors, `usd_amount`, `quote_expires_at`) and Phase 4 (float + `awaiting_funds`) first.
One phase at a time, sandbox only, local PHPUnit, push with `[skip ci]`. Master-first per CLAUDE.md.

## 0. Goal
Admins stop approving payouts by hand. A background engine ("Guardian") cross-checks every withdrawal for legitimacy, then approves, defers, holds for a human, or rejects, running entirely from the cron + queue setup already in the repo (single `schedule:run` cron; database-queue drain on shared hosting, Redis + Horizon on VPS/Cloudways-with-Supervisor). It also delivers the five engine fixes found in review.

## 1. What the code does today (verified in the 2026-09-22 snapshot) and why it must change
1. `PayoutService::approve()` is admin-only (`abort_unless(hasAnyRole(['super_admin','admin']))`) and is the ONLY place that dispatches `SendPayoutJob`.
2. **Existing "autopilot" bypasses every safeguard.** In `ReferralWithdrawalService` and `StaffWithdrawalService`, `if ($autoSend || PayoutSettings::autopilot()) { $this->payouts->send($request); }` runs INSIDE the `DB::transaction` that also created the hold. That means: (a) a live provider HTTP call inside a DB transaction and inside the user's request cycle — violates the project's own money-safety rule 8 (external calls only in queued jobs); (b) no legitimacy checks at all; (c) if the transaction later rolls back after the provider accepted the transfer, the money is gone but the hold is released. `EarningsPayoutRunCommand` uses the same services. This path is REMOVED in Phase A1 and replaced by Guardian.
3. `PayoutService::send()` catches ANY `Throwable` from the provider call and calls `finalizeFailure()` → `PayoutReversed` returns the held funds. A timeout AFTER the provider accepted the payout therefore refunds the user AND pays them (double pay). Fix in A2.
4. `PayoutThreshold::payoutCount()` counts only `PAID` requests. On a slow rail (up to 14 days) a user can file many requests before any is `paid`, exceeding the free-payout/KYC limit. Fix in A3.
5. Hold references are inconsistent by bucket: credits use `wd-hold:{reference}`, earnings use `earn-hold:{reference}`. Guardian needs a per-bucket `HoldVerifier` (A4).
6. `payout_requests.approved_by` is a nullable FK to users, so system approvals can be stored with `approved_by = null` + a new `approval_source` column.
7. Deployment facts to honour (DEPLOYMENT.md): shared/cPanel = one cron, `QUEUE_CONNECTION=database`, `routes/console.php` schedules a per-minute `queue:work --stop-when-empty`; VPS = Redis + Horizon under Supervisor. Guardian must work on BOTH with no code branches beyond queue names. Cloudways: use the same single-cron entry via its Cron Job Manager; if the server plan offers Supervisor use the Horizon path, otherwise the database-drain path. Do not assume which.

## 2. Architecture
```
withdraw click / earnings run
        |  (hold funds + create request, status=pending, review_state=auto_pending)  -- NO provider call, NO send()
        v
EvaluatePayoutRequestJob  (queue: payout-guard, unique per request)   <-- also picked up by cron sweeper
        |
   PayoutGuardian::evaluate()  -> gates G1..G9 (read-only) -> risk score -> decision
        |
  approve -> compare-and-set pending->approved (approval_source=system) -> SendPayoutJob (queue: payouts, afterCommit)
  defer   -> review_state=deferred, next_check_at set (cooling-off / awaiting_funds / quote refresh)
  hold    -> review_state=manual_review (admin queue) + alert digest
  reject  -> PayoutService::reject-style reversal with a safe user-facing reason
        |
SendPayoutJob -> PayoutService::send() -> provider (intent logged BEFORE call) -> webhook confirms paid
        |
Cron reconcilers: unknown-outcome resolver, stuck watchdog, float-retry, metrics
```
Principles: evaluation is read-only and fast (< 1s, no external HTTP); the only mutation is one atomic status change; every decision is recorded immutably; admin can override anything; a kill switch and a shadow mode exist from day one.

## 3. Data model (all additive)
```
payout_requests  (+ columns)
  approval_source enum('admin','system') null,
  review_state enum('auto_pending','deferred','manual_review','approved_auto','approved_manual','rejected_auto') default 'auto_pending',
  hold_reason string null,        -- machine code, e.g. cooling_off, float_short, name_mismatch
  risk_score smallint null,
  next_check_at timestamp null,   -- when Guardian should look again
  evaluating_at timestamp null,   -- claim marker to stop two workers grabbing one row
  fx_locked_at timestamp null     -- (quote_expires_at already added by main Phase 1)
  index(review_state,next_check_at)

payout_decisions   -- append-only, never updated
  id, payout_request_id fk, attempt smallint, decision enum(approve,defer,hold,reject),
  score smallint, rules json (per rule: id, result pass|warn|fail, weight, evidence),
  engine_version string, shadow bool default false,
  decided_by string ('system' | 'admin:{id}'), decided_at, next_check_at null
  index(payout_request_id)

payout_provider_calls   -- written BEFORE any provider call (crash-safe intent log)
  id, payout_request_id fk, provider, attempt smallint, idempotency_key (= request.reference),
  state enum(intent,submitted,confirmed,unknown,definitively_failed),
  http_status smallint null, provider_ref null, error text null,
  started_at, finished_at null, next_lookup_at null, lookup_attempts smallint default 0
  unique(payout_request_id, attempt)

payout_trust_profiles
  user_id pk, tier enum(new,trusted,vip) default new, clean_payouts int default 0,
  last_incident_at null, override_by null, override_reason null, updated_at

payout_account_fingerprints   -- detect one destination shared by several users
  id, provider, fingerprint (HMAC-SHA256 of normalised account identifier with app key), user_id, payout_account_id,
  unique(provider, fingerprint, user_id); index(provider, fingerprint)
```
Settings (`Setting`-backed via `PayoutSettings`): see §7.

## 4. The gates (Guardian rules). Each returns `pass | warn | fail` + evidence; hard gates decide outright, soft gates add to a 0–100 risk score.

**HARD gates (any fail = not auto-approved)**
- **G1 Switches & health**: `payouts.enabled`, auto-approval enabled, provider enabled + healthy (circuit/ProviderStatus), corridor enabled for country/currency, country not on deny list. Fail → `defer` (retry in 5 min) if provider unhealthy, `hold` otherwise.
- **G2 Account integrity**: account belongs to the requester, `is_verified`, provider recipient ref present, for global rail an `active` enrollment (Addendum A) and a saved guide acknowledgement (Addendum B). Fail → `hold`.
- **G3 Ledger hold verification (the core "is this real" check)**: `HoldVerifier` per `source_bucket` proves the source ledger contains exactly one live hold for this `reference` equal to the request's USD amount (`credit_amount` for credits/earnings), not released, and that the bucket balance is not negative. Implementations: `CreditsHoldVerifier` (`wd-hold:`), `EarningsHoldVerifier` for referral/merchant/partner/staff (`earn-hold:`), each reading its own ledger through the existing services — never a second ledger. Mismatch → `hold` + `AlertAdminJob(code=payout_hold_mismatch)`.
- **G4 KYC & limits**: `PayoutAdmission` check (see A3): committed-payout count (in-flight included) vs free-payout threshold; KYC-L2 required for first payout on any non-local corridor; per-corridor min/max; per-user daily/weekly/monthly USD caps; `> N×` the user's largest previous payout requires KYC-L2. Fail → `hold` (or `reject` if the user simply exceeds a hard cap).
- **G5 Cooling-off**: no payout within `payouts.cooling_off_hours` (default 48) of: adding/changing the payout account, password change, 2FA reset/disable. Fail → `defer` with `next_check_at` = end of window (auto-resumes, no admin needed).
- **G6 Destination sharing**: `payout_account_fingerprints` shows the same destination used by a different user → `hold` (potential multi-account farming). Same user reusing their own is fine.
- **G7 Name match**: KYC-verified legal name vs `account_name`/`payee_kyc_name` (normalise case, accents, word order; fuzzy threshold configurable, default ≥ 0.85). Below threshold → `hold`. Skipped only where the provider itself resolved the name (Paystack/Flutterwave resolvers) — record `skipped_provider_resolved`.
- **G8 FX quote validity**: if `quote_expires_at` has not passed → pass. If expired: recompute at the current rate. Drift within `payouts.fx_tolerance_pct` (default 3%) → honour the locked quote (platform absorbs), `warn`. Drift beyond tolerance → `hold` for an admin decision (owner default: platform absorbs up to 3%, beyond that human decides). Never silently change what the user was promised.
- **G9 Funding**: `payout_float_balances` for the provider/currency covers this request plus already-committed unsent requests (FIFO fairness: older requests first). Short → `defer` to `awaiting_funds`, user sees "queued, no action needed"; resumes automatically when a top-up is recorded (event) or on the next sweep.

**SOFT signals (score 0–100, weights configurable, all logged with evidence)**
- Account age < `payouts.new_account_days` (default 7); first-ever withdrawal.
- Earn-then-withdraw speed: earnings younger than `payouts.maturity_days` (default 3) make up a large share of the payout (needs earning row timestamps from the ledgers; if unavailable, skip the signal and log `unavailable`).
- Referral concentration/farming: earnings dominated by few referred accounts, referred accounts sharing device fingerprint or IP with the referrer (`KnownDevice.fingerprint/ip_address`).
- New device/IP at request time vs `KnownDevice` history; IP country ≠ profile country ≠ payout country (warn only — VPNs and travel are normal).
- Velocity: requests in the last 24h/7d; recent failed/reversed payouts; open disputes/chargebacks on the originating orders (`DisputeAwareGateway` data) within the maturity window.
- Trust tier: `trusted`/`vip` lowers the score; a recent incident raises it.

**Decision bands (defaults, admin-editable)**
- Any hard `fail` → per gate (`defer` / `hold` / `reject`).
- Score < 30 → approve if `amount_usd ≤ tier_limit` (new = $100, trusted = $500, vip = $2,000).
- Score 30–59 → approve ONLY if tier is `trusted+` and amount ≤ half the tier limit; else `hold`.
- Score ≥ 60 → `hold`.
- Amount above tier limit → `hold` (human sees it, one-click approve).
- Circuit breakers (evaluated before approving): auto-approved USD in rolling 24h > `payouts.auto_approval.daily_cap_usd` (default 5,000) OR auto-approved count in the last hour > 3× the trailing average → everything else goes to `hold` + alert `guardian_breaker_tripped`.
- 2–5% (`payouts.auto_approval.qa_sample_pct`, default 3) of auto-approved payouts are additionally flagged for post-hoc admin review (does not delay sending).

## 5. Engine fixes (the five misalignments + two related)
**A. Unknown outcome after provider call (double-pay fix) — `PayoutService::send()` rewrite**
- Write `payout_provider_calls` row with state `intent` (own committed transaction, NOT the row-lock transaction) → mark `submitted` immediately before the HTTP call → after: record result.
- Classify results: (1) provider returned a definitive rejection (4xx validation, explicit `failed`) → `definitively_failed` → existing `finalizeFailure` (reverse hold). (2) provider returned success/accepted → `processing`, wait for webhook as today. (3) ANY transport error, timeout, 5xx, or worker death after `submitted` → state `unknown`, request stays `processing`, NO reversal, NO alert-spam (one alert after grace).
- Add optional interface `SupportsStatusLookup { lookupTransfer(PayoutRequest): LookupResult{found|not_found|unsupported, status, providerRef} }`, implemented by Payoneer (query by `client_reference_id`), Stripe (list transfers by `transfer_group`/metadata reference), Grey (per its API/idempotency reference), Paystack/Flutterwave (verify by reference). Never guess endpoints — use each provider's real docs.
- `payouts:reconcile-unknown` (every 2 min): for `unknown` calls due for lookup: `found` → apply result via the normal `confirm()`/`fail()` (idempotent, conflict-safe); `not_found` → require 3 consecutive definitive not-found lookups spread over a provider-specific grace window (default 30 min) THEN reverse; `unsupported` or exhausted lookups → mark `manual_review` and alert admin ("cannot verify, do not refund blindly"). A verified webhook arriving at any time resolves it first.
- `SendPayoutJob::failed()` handler: if the worker died mid-call, mark the open call `unknown` (never leave `submitted` dangling).
- Keep `$tries = 1`. Never retry a submit; only the lookup path can conclude.

**B. Free-payout threshold counts in-flight requests**
- `PayoutThreshold::payoutCount()` becomes `committedCount()`: requests with status `paid`, `processing`, `approved`, `awaiting_funds` and `pending` (excluding `failed`/`reversed`). `remainingFree`, `requiresKyc`, `canWithdraw` use it. A reversal frees the slot automatically.
- Race safety: add `PayoutAdmission::check(User, PayoutRequestDraft)` invoked INSIDE `PayoutService::createRequest`'s transaction after `lockForUpdate()` on the user's row, so two parallel clicks cannot both pass. All five withdrawal services already funnel through `createRequest`; their pre-checks stay for friendly errors but the locked check is the authority. Also count-and-cap `pending+approved+processing` per user (`payouts.max_open_requests_per_user`, default 3).

**C. Expired FX quote** — implemented as gate G8 above; store `fx_locked_at`; user-facing note "your rate is locked for {hours}h" from settings.

**D. User-visible status while waiting** — one mapping (lang files in en/ar/fr/sw), used by Withdraw, PayoutDashboard, emails, and the API:
| status / review_state | User sees |
|---|---|
| pending / auto_pending | "Being checked — usually a few minutes" |
| pending / deferred (cooling_off) | "Security hold until {time} — nothing needed from you" |
| pending / deferred or awaiting_funds (float_short) | "Queued — we're preparing funds, no action needed" |
| pending / manual_review | "Extra check in progress — we'll notify you" |
| approved / processing | "Sent to {provider} — can take up to {days} days" |
| paid | "Delivered to your {provider} account" |
| failed / reversed | "Returned to your balance — {safe reason}" |
Never expose rule names or scores to users. Send a notification when the state changes to deferred/manual_review/paid/failed.

**E. "Paid" definition** — paid = delivered into the user's Payoneer account or the bank/virtual account details they gave. Their onward withdrawal from Payoneer/Grey/Raenest to a local bank is theirs. Put this sentence in the Rail Guide (Addendum B) and in the paid notification.

**F. Remove synchronous autopilot** — in `ReferralWithdrawalService`, `StaffWithdrawalService` (and any similar path, grep for `->send(`): delete the inline `send()` inside transactions. Creation ends at "hold + create request + dispatch `EvaluatePayoutRequestJob` afterCommit". `payouts.mode` semantics: `manual` = Guardian only evaluates and never approves (every request lands in the admin queue); `auto` (replaces legacy `autopilot`, map old value to `auto`) = Guardian may approve. Regression test: no provider HTTP call is made while a DB transaction is open.

**G. Admin approve vs system approve race** — `PayoutService::approve()` and a new `approveBySystem(PayoutRequest, PayoutDecision)` both use one compare-and-set: `UPDATE payout_requests SET status='approved',... WHERE id=? AND status='pending'`; zero rows affected = someone else won → return current state without dispatching a second job. `SendPayoutJob` stays `ShouldBeUnique` and `send()` stays row-locked.

## 6. Cron + queue design (works on shared cPanel, VPS, Cloudways)
Queues: `payout-guard` (evaluations, small fast jobs), `payouts` (sends — keep as is), `default`. On the database-queue path, the existing per-minute drain in `routes/console.php` must process them in priority order (`--queue=payouts,payout-guard,default`); CHECK the current drain arguments and extend, do not add a second drain. On Horizon add supervisors: `payouts` (1–2 processes, timeout > provider timeout, tries 1), `payout-guard` (2–4 processes, tries 2, backoff 30s).

Schedule additions in `routes/console.php` (all `withoutOverlapping()`, add `onOneServer()` if the app runs on more than one node, register each in `SchedulerHealth::TASKS` with expected intervals so the admin scheduler widget shows overdue):
| Command | Cadence | Purpose |
|---|---|---|
| `payouts:guard-sweep` | every minute | claims up to 50 requests that are `auto_pending` or `deferred` with `next_check_at <= now` (atomic claim via `evaluating_at`), dispatches `EvaluatePayoutRequestJob` |
| `payouts:reconcile-unknown` | every 2 minutes | resolves `unknown` provider calls (fix A) |
| `payouts:float-retry` | every 5 minutes (+ on top-up event) | re-evaluates `awaiting_funds` oldest-first |
| `payouts:stuck-watchdog` | every 10 minutes | `processing` beyond provider SLA → lookup → alert (extend the Phase 4 command; do not duplicate) |
| `payouts:guard-metrics` | hourly | rolls up approve/hold/defer/reject rates, decision latency, breaker status |
| `payouts:trust-recompute` | daily | updates `payout_trust_profiles` (clean payouts, incidents) |
| `payouts:guard-digest` | daily 07:30 | admin summary notification/email |
Jobs: `EvaluatePayoutRequestJob` implements `ShouldQueue, ShouldBeUnique` (unique id = request id, `uniqueFor` 120s), `$tries=2`, catches everything → on unexpected exception set `review_state=manual_review`, `hold_reason=guardian_error`, alert. Guardian errors must FAIL SAFE to human review, never to approval.
Timing rule: the sweeper only claims and dispatches; evaluation happens in the queue so a slow evaluation cannot block the scheduler tick (important on the single-cron shared-host setup).

## 7. Settings and kill switches (`PayoutSettings`, all admin-editable, all audited)
`payouts.auto_approval.enabled` (default OFF) · `payouts.auto_approval.shadow` (default ON: evaluate + log decisions but change nothing; admin still approves manually) · per-provider `payouts.provider.{name}.auto_approve` · tier limits · score band thresholds · cooling-off hours · FX tolerance · maturity days · max open requests · daily cap · QA sample % · lookup grace minutes per provider. A single red "Pause all auto-approvals" button (`payouts.auto_approval.enabled=false`) takes effect on the next evaluation; requests already `approved` still send.

## 8. Admin role after automation (what humans still do)
1. **Review queue** (`Admin\Payouts`, new tab): only `manual_review` items, each showing the rule results, evidence, risk score, user history, and buttons Approve / Reject / Request KYC, with a REQUIRED note. Approve = `approve()` (source `admin`).
2. **Fund the float** (Addendum A planner) — the only routine job.
3. **Resolve exceptions**: `unknown` outcomes flagged unresolvable, FX drift holds, `payout_confirm_conflict` alerts.
4. **Tune**: thresholds, tier overrides per user (`payout_trust_profiles.override_*`), country/corridor limits.
5. **Read the daily digest** and QA-sampled payouts.
6. **Kill switch / shadow-mode graduation** decisions.
Decision log page: filter by decision/rule/provider/user; export (audited).

## 9. Rollout plan (do not skip)
1. Ship A1–A3 fixes with Guardian OFF (engine safety first).
2. Turn Guardian ON in **shadow mode** for ≥ 14 days or ≥ 100 payouts: compare its decisions with the admin's actual ones; track would-have-approved-but-admin-rejected (false negatives) and held-but-admin-approved (friction). Tune weights.
3. Enable auto-approval for `new` tier ≤ $25, local rails only. Then widen tier limits, then enable global rail.
4. Keep the daily cap and breakers on permanently.

## 10. Build phases and acceptance checks
- **A1 Foundations + autopilot removal**: migrations, decision/provider-call/trust/fingerprint tables, `approveBySystem` compare-and-set, delete inline `send()` autopilot, queue names + drain args, settings, `PayoutGuardian` skeleton returning `hold` for everything. *Accept:* no provider HTTP during an open DB transaction (test with Http fake + `DB::transactionLevel()`); admin and system approving the same request simultaneously produces exactly one `SendPayoutJob`; existing payout tests green.
- **A2 Unknown-outcome fix**: `send()` rewrite, `payout_provider_calls`, `SupportsStatusLookup` + real implementations for providers that exist at build time, `reconcile-unknown`, `SendPayoutJob::failed()`. *Accept:* provider accepts then client times out → request stays `processing`, later found → `paid`, funds NEVER returned; not-found ×3 after grace → reversed once; no lookup support → manual review, no reversal; late webhook after reversal → existing conflict alert.
- **A3 Threshold + admission fix**: `committedCount`, `PayoutAdmission` under user-row lock, max-open-requests. *Accept:* 6 parallel slow-rail requests with 5 free payouts → the 6th is refused; reversal frees a slot; concurrency test with two simultaneous requests.
- **A4 Gates G1–G9 + HoldVerifiers**: implement each gate with unit tests (pass/warn/fail evidence), bucket verifiers, fingerprint capture at account-add, FX tolerance logic, float FIFO. *Accept:* one test per gate incl. tampered hold (amount mismatch, missing hold, released hold), shared destination across users, cooling-off deferral time, FX drift branches, float short → `awaiting_funds` → auto-resume on top-up.
- **A5 Scoring + decisions + cron**: soft signals, bands, breakers, sweeper claim logic, jobs, schedule + `SchedulerHealth` + heartbeats, digest. *Accept:* claim test (two sweepers never evaluate one request twice), fail-safe test (exception → `manual_review`), breaker trips at cap, shadow mode changes nothing, kill switch stops approvals, scheduler entries visible and heartbeat-monitored, database-queue drain processes `payouts` and `payout-guard`.
- **A6 Admin UI + user status + tuning**: review queue, decision log, settings screen, trust overrides, user status mapping (4 languages), notifications. *Accept:* only admin roles can act; note required on manual approve/reject; users never see rule names/scores; status text matches the table in §5-D.

## 11. Safe defaults if the owner does not decide
Shadow mode ON and auto-approval OFF at first · tier limits $100 / $500 / $2,000 · cooling-off 48h · FX tolerance 3% (beyond → human) · maturity 3 days · daily auto-approval cap $5,000 · name-match ≥ 0.85 · max 3 open requests per user · lookup grace 30 min with 3 lookups · QA sample 3%.
