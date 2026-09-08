# Laravel Production Readiness — Part 2, audited against NaaraSim

Per Rule 1, every one of the 6 Part-2 sections was checked against the real code.
Legend: **present** / **partial** / **missing** / **infra** (hosting decision, not
app code) / **method** (how Claude Code is driven, not app code).

| # | Section | State | Evidence / notes |
|---|---------|-------|------------------|
| 1 | DB scaling decision tree | **infra** (code-side prereqs present) | **Correction (2026-09-07):** eSIM/number checkout provider calls (`Checkout::purchase()`, `GetNumber::order()`/`getLine()`) run synchronously inside the Livewire action, not as queued jobs — the earlier "every external/provider call is a queued job" claim above was wrong for the checkout path. This is a deliberate, owner-confirmed pattern: the sync call is wrapped in the circuit breaker + orphan-charge/refund guard, so money-safety rules are honored even though the request blocks on the provider's HTTP response. Other provider calls (catalogue sync, pricing recompute, webhook-triggered work) genuinely are queued. `config/database.php` has no read/write split — correct for a single MySQL until read volume warrants it. Connection pooling / replicas / Octane are **hosting decisions**, not app code. No action needed. |
| 2 | First-48h activation & retention | **partial** | An in-app **first-purchase / comeback nudge** exists (`MarketingCoupons::nudgeFor()`, dashboard `couponNudge`, admin toggle in Coupons). **Missing:** a real `users.activated_at` signal + a **scheduled 24/48h inactivity job** that emails/nudges users who never took the core action (`routes/console.php` has no such command). This is a genuine, buildable retention gap. |
| 3 | Enterprise auth (SAML/SSO/SOC 2) | **missing** (OAuth-only today) | OAuth social login is solid (Socialite: Google/Facebook/Apple/X/Microsoft/Discord) — fine for OAuth SSO. **True SAML 2.0 (IdP-initiated, per-org IdP metadata / multi-tenant) is absent.** SOC 2 is a vendor/process attestation, not code. A real, sizeable build if an enterprise deal requires SAML — evaluate before the deal, not during. |
| 4 | Data retention & deletion compliance | **partial** | User-controlled deletion is solid (S26): self-request (`deletion_requested_at`) → **super-admin `approveDeletion`**, data export filtering third-party PII (`UserDataExporter`), admin `AccountDeletions` queue. **Missing:** a compliance-retention layer — no `retained_records`/`retain_until`, no `config/retention.php`, no `retention_audit_log`. NaaraSim holds wallet/payment records that may legally need to survive a deletion request; that retention schedule isn't modeled yet. |
| 5 | Long-running agent orchestration | **method** (already practised) | Not app code — it's how this build is driven, and we already do it: `PROGRESS.md` + `docs/PLATFORM-STATE.md` state files, section-scoped commits (clean rollback points), a summary/checkpoint per chunk. Nothing to "build." |
| 6 | Secrets & API key hygiene | **present + one gap** | `.env` is git-ignored (CI install-integrity verifies), keys flow through `config()` not raw `env()`, and CI runs a **Security audit** (`composer audit`). **Missing:** a `gitleaks`/`git-secrets` pre-commit or CI secret-scan step — a small, high-value add so a key can never be committed in the first place. |

## Genuinely-buildable, pure-code gaps (if/when wanted)
1. **§2 activation/churn job** — `users.activated_at` + a scheduled inactivity
   nudge (email + in-app), reusing the existing Notifications + Horizon stack and
   the `MarketingCoupons` nudge surface. Medium.
2. **§6 gitleaks CI step** — add a secret-scan job to `.github/workflows` (and an
   optional local pre-commit hook). Small.
3. **§4 retention layer** — `retained_records`/`retain_until` + `config/retention.php`
   + `retention_audit_log`, so "delete my account" anonymises user-facing fields
   while compliance-flagged financial records stay locked to their expiry. Needs a
   jurisdiction/retention-period decision first (Nigeria + card-scheme rules).
4. **§3 SAML** — a real project (`slides/saml2` or similar, per-org IdP config).
   Only worth starting against a concrete enterprise requirement.

## Infra / process (yours, no app code)
- **§1** connection pooling → query tuning → read replica, in that order, only when
  metrics justify it.
- **§3** SOC 2 attestation + auth-vendor documentation — a compliance/process task.
- **§5** the state-file + chunk + checkpoint loop — already the working method here.

Nothing in Part 2 is a live money-path risk today; §2 and §6 are the highest-value
pure-code additions, §3 and §4 are product/compliance bets to make deliberately.
