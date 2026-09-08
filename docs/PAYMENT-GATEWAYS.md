# Payment Gateways — reference & operational guide

One page per concern for all nine wallet-funding gateways. "Implemented here"
means the code in `app/Services/Payments/` does it today; "Provider supports"
means the gateway offers it but we have **not** built it yet (tracked in
`docs/PLATFORM-STATE.md` under the payments follow-up).

All nine are wired to the unified webhook controller
(`app/Http/Controllers/Webhooks/PaymentWebhookController.php`) and dispatch the
idempotent `CreditWalletJob`. The **webhook is the only thing that credits a
wallet** — the browser callback/return URL never does (it can drop). Every
credit is a `wallet_transactions` ledger row via `WalletService::credit()`
(idempotent on `topup:{gateway}:{reference}`), never a direct balance write.

> **Money rule:** verify the signature BEFORE trusting the body; process the
> credit in a queued job; dedupe on the provider reference. Proven end-to-end
> for Paystack against a real `database` queue in `PaymentWebhookTest`.

---

## Admin → Payments → Gateways (BUILD-2 §3)

A per-gateway operations screen: a **sandbox/live mode toggle** (swaps the API
base URL for gateways whose sandbox and live hosts differ — PayPal, NOWPayments;
informational for the key-prefix gateways, where the live detector reads the
`sk_test_` etc. key), the read-only **webhook URL** and **callback/return URL**
each with a copy button, and a live **Test connection** button (a bounded,
read-only API ping with the current keys — Paystack/Stripe/Flutterwave balance,
PayPal OAuth, NOWPayments balance; the signed-request gateways say "verify with a
small live payment"). API keys stay on Admin → Provider Keys (one credential
system, not duplicated).

## Setup checklist (per gateway, in Admin → Provider Keys)

1. Enter the **secret/API key** (and any merchant id / IPN secret / webhook
   signing secret the gateway needs — see the table below).
2. Register the **webhook URL** on the provider's dashboard:
   `https://{your-domain}/webhooks/payments/{gateway}`
   (e.g. `/webhooks/payments/paystack`). This is the step whose absence caused
   the original "top-up didn't credit" — no registered webhook ⇒ no credit.
3. Where the provider needs a **return/callback URL**, set it to your wallet page
   (`/wallet`).
4. Make sure the **queue worker runs** (shared cPanel: the `queue:work
   --stop-when-empty` cron; VPS: Horizon). A verified webhook only credits once
   its queued job runs.

---

## Per-gateway matrix

| Gateway | Live base URL | Sandbox/test signal | Webhook signature (verified against current docs) | Checkout today | Provider inline option |
|---|---|---|---|---|---|
| **Paystack** | `api.paystack.co` | `sk_test_` key prefix | HMAC-SHA512 of raw body, `x-paystack-signature` | Redirect (hosted) | **Inline JS** (`PaystackPop`) — not built here |
| **Flutterwave** | `api.flutterwave.com/v3` | `FLWSECK_TEST-` key | `verif-hash` header equals the stored secret hash | Redirect (hosted) | Inline modal SDK — not built here |
| **Stripe** | `api.stripe.com/v1` | `sk_test_` key prefix | `Stripe-Signature` `t=…,v1=HMAC-SHA256(t.body)`, timestamp-toleranced | Redirect (Checkout) | Elements / Payment Element (embed) — not built here |
| **PayPal** | `api-m.paypal.com` | `api-m.sandbox.paypal.com` host | Transmission verified via PayPal's `verify-webhook-signature` API | Redirect (required) | Redirect is generally required — keep |
| **NOWPayments** | `api.nowpayments.io` | `sandbox` in base URL | HMAC-SHA512 of **key-sorted** JSON, `x-nowpayments-sig` (slash-escaping tolerant) | Redirect (hosted invoice) | Hosted invoice / QR |
| **Cryptomus** | `api.cryptomus.com` | — (no key convention) | `md5(base64(json_encode($body, JSON_UNESCAPED_UNICODE)) . api_key)` in `sign` (slash-escaped canonical; verify tolerant) | Redirect (hosted) | Hosted invoice / QR |
| **CoinPayments** | `www.coinpayments.net/api.php` | — | HMAC-SHA512 of **raw** body, `HMAC` header, + merchant id match | Redirect (hosted) | Hosted invoice / QR |
| **BinancePay** | `bpay.binanceapi.com` | — | HMAC-SHA512 signed (merchant v3 scheme) | Redirect (hosted) | Hosted checkout / QR |
| **Payssion** | `www.payssion.com` | — | in-body `notify_sig` (api_key + secret) | Redirect (hosted) | Hosted (local methods) |

Notes from the live-doc review (BUILD-2 §4.4 / §5):
- **Cryptomus** signs with slashes **escaped** (`JSON_UNESCAPED_UNICODE` only).
  A prior bug added `JSON_UNESCAPED_SLASHES`, which broke payment creation and
  webhook verification for any payload containing `/`. Fixed; verification now
  also accepts the unescaped variant defensively.
- **NOWPayments'** canonical (Python) signs with slashes unescaped; their own
  WooCommerce PHP plugin uses escaped. Verification accepts either.
- **CoinPayments** HMACs the raw body — no reserialization ambiguity.
- Base URLs are env-overridable (`{GATEWAY}_BASE_URL`); PayPal & NOWPayments
  swap to a sandbox **host**, most others swap keys on the same host.

---

## API-surface gaps (endpoints used vs available)

Used today per gateway: **initialize charge** + **webhook verify/parse**. Not
yet wired for any gateway: **refunds**, **dispute/chargeback webhooks**, partial
capture. These are the two build items below.

---

## In-platform vs redirected checkout (BUILD-2 §6)

Every gateway is **redirect/hosted** today (each `initialize()` returns a
`redirect_url`). Providers that support an embedded flow for a better completion
rate — Paystack Inline, Stripe Payment Element, Flutterwave inline modal — are
candidates to embed; PayPal and the crypto rails are hosted by nature. Building
the inline variants is tracked as a follow-up; confirm each SDK's current
version against the provider's docs before wiring (this changes often).

---

## Refunds & disputes (BUILD-2 §7)

**Refunds — built.** Admin → Payments → **Refunds & Disputes** lists wallet
top-ups with a Refund action, handled by `RefundService`:
- Provider-first: the gateway refund must succeed **before** the wallet is
  touched, so we never remove funds without the money going back.
- Ledger discipline: the reversal is a `debit` `wallet_transactions` row (never a
  direct decrement), fully audited (who/when/how much/why), idempotent (a top-up
  refunds at most once).
- Never a silent loss: a refund is **refused** if the user already spent the
  funds (resolve manually) rather than pushing the wallet negative.
- The gateway call is behind a `RefundableGateway` contract, wired for
  **Paystack** (`POST /refund` by reference), **Stripe** (`POST /v1/refunds` on
  the payment_intent), **Flutterwave** (`POST /v3/transactions/{id}/refund`), and
  **PayPal** (`POST /v2/payments/captures/{id}/refund`). Stripe/Flutterwave/PayPal
  need the provider's own charge id, which our NAARA reference doesn't carry — so
  it's captured at webhook time into `payment_charges` (payment_intent / txn id /
  capture id) and handed to the refund call. Crypto rails are irreversible →
  recorded as a **manual** task + admin alert, never auto-moved.

**Disputes / chargebacks — built.** The same signed webhook endpoint handles
dispute events (`DisputeService`):
- On open → **freeze** the disputed amount from the wallet (a `reserve` earmark,
  so it can be neither spent nor withdrawn while contested) and alert.
- On resolution → release the earmark (won), or release then **debit** it (lost /
  charged back). Idempotent per `(gateway, provider_dispute_id)`.
- The wallet earmark is USD-only, so non-USD disputes are recorded + alerted for
  manual handling rather than pretending to freeze.
- Wired for **Paystack** (`charge.dispute.create` / `.resolve`;
  `resolution=merchant-accepted` or a `refund_amount` ⇒ lost), **Stripe**
  (`charge.dispute.created` / `charge.dispute.closed`, `status` won/lost), and
  **PayPal** (`CUSTOMER.DISPUTE.CREATED` / `.RESOLVED`,
  `dispute_outcome.outcome_code`). Stripe/PayPal disputes cite the provider
  charge id (payment_intent / capture id), mapped back to our reference + user
  via `payment_charges`. **Flutterwave** chargeback webhooks are an on-request,
  variable-payload feature — left for a verified follow-up rather than shipped on
  a fund-freezing path unverified (the `DisputeAwareGateway` seam is ready).

Per-gateway terms to capture when adding each: partial-refund support,
dispute-response window, dispute-loss fee.

---

## End-to-end sandbox test (Paystack, the reference)

1. Admin → Provider Keys: enter the Paystack **test** secret key (`sk_test_…`).
2. On the Paystack dashboard, set the webhook URL to
   `https://{domain}/webhooks/payments/paystack`.
3. Confirm the queue cron is running on the host.
4. From the wallet page, start a top-up → complete it on Paystack's test page.
5. Confirm: webhook received (row in `webhook_logs`, `verified=1`) → the queued
   `CreditWalletJob` runs → `wallet_transactions` gets one `topup:paystack:…`
   row → the wallet balance rises. A redelivered webhook adds no second credit.

The admin dashboard shows a **Test mode** banner while any gateway is on test
keys; the checkout shows a per-gateway **Test mode** tag. Flip to live keys to
clear both.
