# Provider research (2026-10-02) — what is confirmed, what is not, and what it changes

Rule kept: nothing below is built on a guess. "Confirmed" = read on the provider's own documentation or OpenAPI spec (source listed). "Not confirmed" = could not be read publicly, or sources disagree; it stays blocked until you give us access or the real reference.

## 1. Paystack (already integrated) — the three owed checks

| Item | Result | Source |
|---|---|---|
| Transfer reference rules | **Confirmed.** Lowercase alphanumeric plus `-` and `_` only; a UUID (<=100 chars) or at least 16 characters. Re-sending the SAME reference is a safe retry; a new reference is a new transfer (double-pay risk). | paystack.com/docs/transfers/single-transfers (mirror docs-v2.paystack.com) |
| Our references | **Compliant.** `PayoutReference` produces `ns…-…` lowercase, 32 chars; the old `wd:uuid` form (with a colon) would have been rejected. Test: `test_our_provider_reference_meets_paystacks_published_rules…` | code |
| `GET /transfer/verify/{reference}` | **Path confirmed** (OpenAPI: "Verify the status of a transfer", reference as path parameter). **Status values confirmed:** pending, success, failed, otp, abandoned, reversed, blocked, rejected, received. We map all nine. The exact response BODY shape is the one thing left for a single sandbox call. | PaystackOSS/openapi `paystack.yaml` |
| `GET /balance` | **Confirmed.** Returns an array of `{currency, balance}`; amounts are in the currency's subunit (kobo/pesewas), so divide by 100. Our reader does exactly that; tested. Still do one sandbox call before enabling auto-sync. | Paystack "Managing transfers" + third-party mirrors |
| `GET /country` | **Confirmed shape:** `data[]` with `name`, `iso_code`, `default_currency_code`, `integration_defaults`, `relationships`. We read `iso_code`. **Caveat:** this is the list of countries Paystack operates in, not a guarantee that *transfers* work there — which is why the guide never ticks a country until you enable a corridor. | Paystack API reference (mirror) |
| Webhook signature | **Confirmed:** HMAC-SHA512 of the raw body with the secret key (`x-paystack-signature`); our verifier matches. Events: `transfer.success`, `transfer.failed`, `transfer.reversed`. No documented event id, so our dedupe hashes (provider, reference, status, code). | Paystack docs |

**New finding that changed the code:** if *transfer OTP* is enabled on your Paystack account, a transfer comes back `otp` and **waits for a Finalize call** — it never completes by itself. We previously treated that as "processing" and waited forever. It is now: kept in processing (never refunded — it could still be finalized and paid), plus an immediate admin alert `paystack_transfer_needs_otp`. **Action for you:** switch transfer OTP off in your Paystack dashboard before automated payouts (Paystack's docs describe OTP as an optional setting). Added to GO-LIVE.

## 2. Stripe

| Item | Result | Source |
|---|---|---|
| Global Payouts (pay people who are not Stripe account holders, 160+ countries) | **Available only to businesses located in the US and UK** (private preview for a list of EU countries and AU). **Requires Treasury** and a funded financial account. Stripe says it is "best for businesses that already hold the Money Transmitter License" and that you "manage your own legal and compliance requirements". | docs.stripe.com/global-payouts |
| API | `POST /v2/money_management/outbound_payments` (amount in minor units, `from.financial_account`, `to.recipient`, optional `to.payout_method`); recipients are v2 Accounts with `configuration.recipient` capabilities; payout methods via Outbound Setup Intents; restricted API key needed. Pinned to a *preview* API version (`2026-09-30.preview` in the docs). | docs.stripe.com/global-payouts/send-money, /recipient-creation |
| Connect cross-border payouts (what our current Stripe rail uses) | **Only between platforms and connected accounts in the US, UK, EEA, Canada and Switzerland.** "Stripe doesn't support self-serve cross-border payouts to countries outside the listed regions." Not available for the `recipient` service agreement. | docs.stripe.com/connect/cross-border-payouts |

**What it means for NaaraSim:** the existing Stripe rail cannot serve Nigeria/Ghana/Kenya/South Africa recipients (those need Global Payouts). Global Payouts needs your Stripe account to be US- or UK-based **with Treasury** and puts the legal burden (possible money-transmitter question) on you — the lawyer item in `OWNER-CHECKLIST.md` now matters more. **Decision for you:** is the paying entity US/UK-based with Treasury access? If not, Stripe stays limited to US/UK/EEA/CA/CH recipients and the guide must not suggest it for African payees. Until you answer: **do not enable any Stripe corridor outside those regions.** The Global Payouts gateway (`stripe_global`) is NOT built: it is a different API family (v2, preview-versioned) and needs a Treasury-enabled sandbox to test.

## 3. Payoneer

| Item | Result | Source |
|---|---|---|
| Submit payouts | `POST https://api.payoneer.com/v4/programs/{program_id}/masspayouts` (sandbox `api.sandbox.payoneer.com`), bearer token, body `Payments[]` of `{client_reference_id, payee_id, amount (string), currency, description}`, up to 500 per call. Only registered/Active payees can be paid; insufficient funds = not processed. Processing is asynchronous. | payoneer.com/developers-docs/mass-payout/mass-submit-payout |
| Payee onboarding | `POST …/payees/registration-link` -> redirect the payee; status via `GET …/payees/{id}/status` or webhook (Active / Pending / Declined). Needs your payee id, name, email, address, date of birth. | …/mass-payee-onboarding |
| **Not confirmed (blocks building)** | `client_reference_id` length/charset/uniqueness and **idempotency** behaviour; the **payout status endpoint** and status values; **webhook signature scheme** and event names; cancel; balance endpoint; auth/token details. A third-party OpenAPI aggregator shows a *different* API shape (`/programs/{id}/payouts`, a `payment_id` for idempotency, no lookup by our reference) — the two disagree, so one of them is a different Payoneer product/version. | — |

Payoneer's full reference is partner-gated ("Payoneer Payout Integration Guide for your program"). **Needed from you:** Payoneer partner/API approval, your program id, sandbox credentials, and the program-specific integration guide. Then the gateway can be written against the real status/webhook/idempotency rules (money rules do not allow guessing these).

## 4. Grey

Grey publicly says it offers payout APIs ("trigger local and international transfers", 170+ countries) but **publishes no API documentation** — no reference, auth, webhook or idempotency details are public. Access is via a Grey business account / `business@grey.co`. **Blocked** until you obtain API access and the docs. Raenest: no public partner payout API found; destination-only.

## What changed in code because of this research
- Paystack `otp` handling + alert; `rejected`/`blocked` on initiation are definitive failures (matches the documented final statuses); lookup/verify comment now cites the spec.
- Tests added for: reference rules (25 samples), OTP, rejected/blocked, all nine verify statuses, balance in subunits.
- Docs: GO-LIVE (disable OTP; Stripe corridor restriction), OWNER-CHECKLIST (Stripe/Treasury/entity decision), D-COVERAGE.
