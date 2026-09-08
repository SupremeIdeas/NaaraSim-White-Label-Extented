# NaaraSim — Roadmap: Payouts, Merchants & Developer API

> **Status: DESIGN / RESEARCH for later build.** Nothing here is built yet. Each
> layer is an **admin-toggleable** feature the owner switches on when ready. This
> document is the blueprint so the eventual build is fast, safe, and consistent
> with NaaraSim's money-safety rules.
>
> **The one rule that governs all three layers:** *the admin's profit floor is
> sacred.* Every credit redeemed, every reseller margin, every developer discount,
> and every payout is arranged so the platform owner **never pays out of their own
> margin** — payouts come from money that was already earmarked as someone else's
> earning (a referral bonus, a merchant's reseller margin, etc.), and MarginGuard
> (cost + minimum profit) still clamps every price underneath.

---

## Layer 0 — Shared foundation: Payout engine + bank accounts + KYC

All three layers share these building blocks, so build them first.

### 0.1 Bank accounts (everyone can add payout details)
New table `payout_accounts`:
`user_id, type (bank|mobile_money), country, currency, bank_code, account_number,
account_name (resolved, read-only), provider_recipient_ref, is_verified, is_default`.

- **Account-name resolution** before saving (so money never goes to a typo):
  Paystack *Resolve Account Number* / Flutterwave *Resolve Bank Account* returns
  the real account name; we store it and show it back for confirmation.
- African banks + mobile money (M-Pesa, MoMo, etc.) via **Paystack Transfers** and
  **Flutterwave Transfers/Payouts** (bank, mobile money, wallet-to-wallet across
  30+ African countries). **International** bank accounts via **Wise** or
  **Stripe** payouts. The provider is chosen automatically by destination country.

### 0.2 Payout engine (the money-out side)
New tables `payout_requests` (id, payee_id, payee_type, amount, currency,
source_bucket, status, provider, provider_ref, failure_reason, approved_by,
settled_at) and `payout_batches` for autopilot runs.

- **Provider abstraction** mirroring the existing `PaymentGatewayInterface`:
  a `PayoutGatewayInterface` with `resolveAccount()`, `createRecipient()`,
  `sendTransfer()`, `verifyTransfer()`, one implementation per PSP.
- **Money-safety (same discipline as WalletService):** every payout runs under a
  DB transaction + lock; idempotent by reference; a payout can never exceed the
  **withdrawable** balance; webhook-confirmed final status before the ledger is
  debited; failed transfers auto-reverse the hold and alert — never blind-retry.
- **Two modes:** *manual approval* (admin reviews each request) and *autopilot*
  (a scheduled `payouts:settle` job batches and sends eligible requests). Admin
  toggles per feature.

### 0.3 Automated KYC (gate for withdrawals & merchant migration)
New table `kyc_verifications` (user_id, level, provider, status, checks json,
reviewed_at). Pluggable `KycProviderInterface`:

- **Smile ID** — broadest pan-African coverage (all 54 countries), BVN + NIN,
  document + biometric/liveness. Good default for Nigeria + continent-wide.
- **Dojah** — BVN/NIN/document/biometric, ~4s average, YC-backed, competitive
  pricing — good for smaller/standard flows.
- **Youverify / Prembly** — KYC + KYB + AML screening when you need business
  verification (merchant onboarding) and compliance controls.
- **International:** Stripe Identity / Onfido / Persona for non-African users.

**Levels:** L1 (email/phone — already have) → L2 (ID + liveness, required to
withdraw) → L3 (KYB business docs, required to become a merchant). Admin sets the
provider + keys under **Admin → API keys** (add a "Identity / KYC" group, with
tooltips pointing to each provider's dashboard, like the existing key groups).

---

## Layer 1 — NaaraCredits → Cash payouts (admin-toggleable)

**Vision:** users can turn earned NaaraCredits into real cash to a bank account.

### Eligibility (the owner's explicit rule)
Only NaaraCredits earned from a person's **first referral each** are withdrawable.
Everything else (check-ins, ad rewards, purchase bonuses) stays spend-only inside
the platform. Implementation: the `credit_ledger` gets a **`withdrawable`** boolean
(or a separate `withdrawable_credits` bucket on the wallet). The referral-reward
earn path sets `withdrawable = true` **once per referred person** (guarded by the
existing idempotency reference, e.g. `referral:{referrerId}:{referredId}`); all
other earn sources stay non-withdrawable.

### Flow
1. User verifies **KYC L2** (once).
2. User adds a **payout account** (0.1) — name resolved + confirmed.
3. User requests a withdrawal ≤ their **withdrawable** balance, ≥ a minimum
   threshold (admin-set). Credits are converted to cash at the configured
   NaaraCredit rate (100 = $1 default), then to local currency.
4. A `payout_request` is created; credits are **held** (debited to a pending bucket).
5. Manual-approval or autopilot settles it via the payout engine; the webhook
   confirms; on success the hold clears, on failure the credits are returned.

### Money-safety
The USD value withdrawn was already granted as a referral bonus that the platform
budgeted for — it never touches admin margin. Withdrawals are capped at the
withdrawable bucket; the money wallet and credit wallet stay separate; all the
WalletService guarantees apply.

### Admin controls
Toggle on/off · min withdrawal · NaaraCredit→cash rate · which earn sources are
withdrawable (default: first-referral only) · manual vs autopilot · per-country
provider. Claude (Pricing Architect pattern) can *suggest* safe thresholds.

---

## Layer 2 — Developer API reselling (wholesale + admin markup)

**Vision:** third-party developers use the NaaraSim API to resell our connected
services and get **wholesale-ish** pricing — but with a small admin markup baked
in, so the owner earns on developer volume too (an expansion channel, never a loss).

### Model
- **API clients**: new table `api_clients` (owner_user_id, name, token_hash,
  scopes, prepaid_balance, rate_limit_tier, is_active). Auth via **Sanctum**
  personal-access tokens (already installed) with per-client scopes + rate limits.
- **Developer price = wholesale cost + `developer_markup`** (admin-set %, default
  e.g. 8–12%). This is **below retail** (so developers get a real deal) but
  **above cost + minimum profit** (so admin still profits). MarginGuard clamps it
  exactly like retail: `dev_price ≥ cost + min_profit`. Claude can propose the
  developer markup per service; admin approves/overrides.
- **Billing**: prepaid — developers top up an API wallet; each API order debits it
  atomically (reuse WalletService). No delivery without funds; orphan-charge guard
  applies. Usage metered per client for invoices/analytics.
- **Surface**: a versioned REST API (`/api/v1/...`) for catalogue, quote, order,
  and status; a developer portal page (keys, docs, sandbox, usage), and webhooks
  so developers get delivery/OTP callbacks. Never expose `cost_price_usd` — the
  developer sees only their (marked-up) price.

### Money-safety
`dev_price` is a separate price lane through the **PricingEngine** with its own
markup setting and the same floor guard. Cost is never surfaced. Every external
call stays a queued job.

---

## Layer 3 — Merchant / Reseller system (KYC-gated, co-branded)

**Vision:** a KYC-verified user migrates to a **merchant**, runs a co-branded
storefront under NaaraSim, resells *everything NaaraSim sells* (eSIM + numbers) at
an admin-set reseller margin, invites their own customers, and withdraws their
earnings — while NaaraSim settles them on autopilot and the owner never loses.

### 3.1 Becoming a merchant
- Gate: **KYC L3 (KYB)** — business docs verified via Youverify/Prembly/Smile ID.
- On approval, create a `merchants` row (owner_user_id, business_name, slug,
  logo_url, brand_color, status, reseller_tier, settlement account) and flip the
  user's role to include `merchant`. Same account, same dashboard — plus a
  **Merchant** area.

### 3.2 The pricing / profit model (owner's explicit rule — admin never loses)
Three stacked prices, each clamped by MarginGuard:

```
  provider cost  (C)
        └── + admin margin      → Retail (R)   ← normal NaaraSim price
                 └── + reseller margin (admin-set %)  → Merchant price (M)
```

- A merchant's customer pays **M**. The user **cannot** add their own markup — the
  reseller margin is **set by admin** (globally, per-service, or per-merchant),
  Claude-proposable + manually overridable.
- **Split of each sale:** admin keeps **R − C** (their usual profit, always);
  the merchant earns **M − R** (the reseller margin). So admin's floor is
  untouched no matter what the merchant does.
- Settlement: either **split at payment time** (Paystack/Flutterwave
  *subaccounts / transaction splits* — the merchant's cut routes to their
  subaccount automatically) **or** accrue to a `merchant_earnings` ledger and pay
  out on autopilot via the payout engine (0.2). All payment happens *in the
  NaaraSim dashboard*; NaaraSim settles the merchant.

### 3.3 Merchant-referred users earn from the MERCHANT's profit (not admin's)
When a user who belongs to a merchant refers others and earns NaaraCredits, and
those credits are redeemed at checkout **or** withdrawn as cash, the value is
**deducted from that merchant's earnings (M − R)** — never from admin's (R − C).
Mechanism: tag such credit ledger entries with `funded_by = merchant:{id}`; at
redemption/withdrawal the settlement engine reduces the merchant's payable by that
amount. Same fair methodology as the platform's own referral economics, scoped to
the merchant. Claude can determine the merchant profit per service; admin can
override manually.

### 3.4 Co-branding & the merchant customer path
- **Invite path:** `/merchant/{business-slug}/join` (and per-customer invite links
  like `/merchant/{business-slug}/{invite-token}`). A customer who signs up there
  is permanently linked to that merchant (`users.merchant_id`).
- **Co-brand theming:** reuse the existing runtime brand-theming layer
  (`BrandSettings` / CSS variables) but **scoped per merchant** — the merchant's
  logo shows **big/bold**, and **"Powered by NaaraSim"** with a **smaller** NaaraSim
  mark sits beneath it, so the merchant leads and NaaraSim endorses without
  outshining. This branding follows the customer **even when they log in from the
  main user-facing login** (resolved from `users.merchant_id`), exactly as
  requested.
- Same dashboard, same catalogue, same checkout — just co-branded and priced at M.

### 3.5 Merchant dashboard features
Storefront setup (logo, colour, business name) · customer list + invites · sales &
earnings analytics · reseller margin view (read-only; set by admin) · payout
accounts + withdraw earnings · KYC/KYB status · settlement history.

### 3.6 Admin controls for merchants
Approve/suspend merchants · set global/per-service/per-merchant reseller margin
(Claude-proposed + manual override) · settlement mode (split-at-payment vs
autopilot payout) · min payout · view every merchant's ledger · force-settle.
Add an **Admin → Merchants** page + a **Merchants** toggle in feature flags.

---

## Suggested build order (each shippable + tested)

1. **Layer 0.1 + 0.2** — payout accounts + payout engine (Paystack/Flutterwave
   transfers, name resolution, manual mode). *Foundation for everything.*
2. **Layer 0.3** — KYC provider abstraction + L2 gate.
3. **Layer 1** — NaaraCredit → cash (first-referral withdrawable bucket).
4. **Layer 2** — developer API reselling (Sanctum clients, dev price lane, portal).
5. **Layer 3** — merchant system (KYB, reseller margins, co-brand theming, invite
   paths, settlement, merchant payouts).

## Cross-cutting requirements

- **Feature flags:** each layer behind an admin toggle (Setting-backed, cached),
  off by default — nothing changes for existing users until switched on.
- **Money-safety:** every new price lane goes through PricingEngine + MarginGuard;
  every money move through WalletService discipline (atomic, idempotent,
  balance_before/after, no blind retry, webhook-verified).
- **Compliance / legal (must confirm before go-live):** NaaraSim is **not** a bank
  — all cash-out rides on **licensed PSPs** (Paystack/Flutterwave/Wise/Stripe).
  Merchant payouts + reseller economics may need local money-transmitter / agent
  considerations and clear T&Cs; KYC/AML (BVN/NIN + AML screening) is mandatory
  before any payout. Review with a Nigerian/pan-African fintech lawyer.
- **Currency & rounding:** payouts convert NaaraCredit → USD → local currency at a
  locked rate captured on the request; never re-price mid-flight.

## Sources (provider research)

- Paystack — [Transaction Splits / Subaccounts](https://paystack.com/docs/payments/split-payments/), [Split API](https://paystack.com/docs/api/split/)
- Flutterwave — [Split Payments](https://developer.flutterwave.com/v3.0/docs/split-payments), [F4B v4 payouts overview](https://github.com/api-evangelist/flutterwave)
- KYC — [Smile ID (Nigeria: BVN/NIN)](https://usesmileid.com/countries/nigeria/), [Dojah identity verification](https://dojah.io/), [Youverify KYC API guide](https://youverify.co/en/blogs/kyc-api-integration-guide), [Prembly](https://prembly.com/)
- Payments in Africa overview — [Best payment gateways in Africa 2026](https://queryfinders.com/blogread-more/best-payment-gateways-guide-africa-2026)
