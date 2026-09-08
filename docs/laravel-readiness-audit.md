# Laravel Production-Readiness — audit against the NaaraSim codebase

Per the blueprint's **Rule 1** (confirm current state before building) and the
owner's chosen scope (*pure-code, high-value only*), every one of the 14 domains
was checked against the real code. Legend: **present** / **partial** / **missing**
/ **infra** (needs a hosting decision, not app code).

| # | Domain | State | Notes |
|---|--------|-------|-------|
| 1 | Support playbooks | missing (product) | No `feature_support_playbooks`. A documentation/process practice, not a live-risk gap. Defer. |
| 2 | Tiered support automation | partial | `SupportChat`/Nia (Tier 1-ish) + ticket escalation exist; no formal 3-tier `incident_tiers`. Product decision. |
| 3 | Edge security (rate limiting) | **present (app) / infra (edge)** | App-level is solid: Fortify `login` limiter (5/min per email+ip), `two-factor`, `admin`, `api` (60/300), `orders` (10/min). Edge WAF/bot rules = **Cloudflare, infra**. No code gap. |
| 4 | API contract hardening | partial | Developer API is Sanctum-token + per-scope gated, JSON errors, path-namespaced (`/api/v1`). HMAC request-signing + Sunset headers not present — a real but non-urgent addition (API is already authed). |
| 5 | Multi-tenant isolation | n/a | NaaraSim is single-tenant (merchants are a feature, not tenants). Skip. |
| 6 | Onboarding & activation | partial (product) | Aurora welcome + wizard exist; no formal disclosure-milestone engine. Product. |
| 7 | Reliability (error budgets) | infra | Per-endpoint budgets + burn-rate alerting need an APM/metrics backend. Infra decision. |
| 8 | Read/write consistency | infra | Single MySQL today; replica routing/lag only matters once replicas exist. Infra. |
| 9 | AI cost governance | partial | Claude usage is feature-gated + spend-gated (`SpendGate`, paying-only). A weekly per-workflow cost dashboard would be additive. |
| 10 | GitHub / CI discipline | **present** | Every change goes through a PR (this branch → PR #1); CI runs PHP 8.2/8.3/8.4 + security audit + install integrity and blocks on failure; commits are small/scoped. Branch-protection *enforcement* is a **GitHub repo setting** (infra) — recommend enabling "require PR + checks" on `main`. |
| 11 | Resilience (circuit breakers) | partial | Every external call is a queued job with retry/backoff; no formal circuit-breaker/bulkhead. Meaningful future addition, sizeable. |
| 12 | Mobile client security | infra/n/a | Only relevant once a Capacitor/native shell ships; no shell today. Defer. |
| 13 | Webhook security & idempotency | **present + hardened this pass** | All webhooks verify HMAC (`hash_equals`) and are idempotent per-handler via unique provider refs. **Added this pass:** an inbound **webhook delivery log** (observability only) so an operator can confirm a provider is actually calling and whether it was accepted/rejected — directly answering the recurring "is Paystack's webhook reaching us?" question. |
| 14 | Observability (session replay) | partial | Durable error capture (`ErrorLogger`) + admin error log exist; Sentry was recommended earlier. Session replay/rage-click = **third-party (Sentry/PostHog), infra**. The new webhook log adds delivery-level observability. |

## Built this pass (pure-code, high-value)
- **Webhook delivery log** (Domain 13/14): `webhook_deliveries` table + a
  path-scoped `LogWebhookDelivery` middleware that records every `webhooks/*` hit
  **after** the response (never altering handler logic), surfaced as "Recent
  webhook deliveries" in the **System Health** admin panel.

## Recommended infra actions (yours to make — no app code)
1. **GitHub:** enable branch protection on `main` (require PR + the existing CI
   checks) — Domain 10 enforcement.
2. **Cloudflare** (or equivalent): auth-route rate limiting at the edge + WAF
   OWASP ruleset + bot rules on pricing/checkout — Domain 3 edge half.
3. **Sentry** (or PostHog): error tracking + session replay + rage-click —
   Domains 7/14. `ErrorLogger` already gives a clean integration seam.
4. **DB replicas:** only when read volume warrants; then add read/write routing —
   Domain 8.

The blueprint's full `platform_capabilities` + `verify()` framework was
intentionally **not** built: the high-value pure-code controls it governs (rate
limiting, webhook HMAC + idempotency) are already present, so the framework would
add ceremony over money paths without new protection. It remains available to
adopt later if the platform grows into the infra domains above.
