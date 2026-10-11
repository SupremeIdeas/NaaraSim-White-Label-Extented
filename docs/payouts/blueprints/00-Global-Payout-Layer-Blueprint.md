# NAARA-PAYOUT-GLOBAL — Global Payout Layer Blueprint
Target repo: NaaraSim master (Laravel 12 / Livewire 3). Prepared 2026-09-29 against the 2026-09-22 codebase snapshot.
Read CLAUDE.md first. Build ONE phase at a time, update PROGRESS.md after each, sandbox keys only, test locally, push with `[skip ci]`.

---

## 0. READ THIS FIRST — what this blueprint is and is not

NaaraSim ALREADY has a production-grade payout engine. This blueprint EXTENDS it. It does **not** replace it.

Existing pieces (verified in code, do not rebuild):
- `App\Services\Payouts\PayoutService` — idempotent by `reference`, row-locked `send()`, webhook-confirmed `paid`, never blind-retry, `PayoutSettled` / `PayoutReversed` events, admin approve/reject, gateway ranking.
- `PayoutGatewayInterface` — `name, available, createRecipient, sendTransfer, verifyWebhook, parseWebhook`.
- Gateways: Paystack, Flutterwave, PayPal, Cryptomus, Stripe (Connect Transfer). Registered in `AppServiceProvider` as `payout.{name}` and in the `PayoutService` constructor list.
- `PayoutAccountService` + `BankResolverInterface` (Paystack/Flutterwave resolvers) — name-resolves accounts before saving.
- `StripeConnectService` — hosted onboarding pattern; `is_verified` mirrors `payouts_enabled`.
- `PayoutThreshold` — N free payouts (`payouts.free_payout_count`, default 5) then KYC-L2 required. `KycService`, Stripe Identity KYC provider.
- `WithdrawalService`, `MerchantWithdrawalService`, `ReferralWithdrawalService`, `PartnerPayoutService`, `StaffWithdrawalService`, `EarningsPayoutRunCommand`, `PartnerPayoutRunCommand`.
- `PayoutWebhookController` (`POST /webhooks/payouts/{provider}`, `PROVIDERS` allow-list), `WebhookLog`, `Auditor`, `AlertAdminJob`, `FinancialReconciliation`, `PayoutSettings` (Setting-backed flags).
- Tables: `payout_accounts`, `payout_requests`, `user_wallets`, `wallet_transactions`, `kyc_verifications`, `webhook_logs`.

### DO NOT create from the research doc (`stripe-custom-payouts-spec.md`)
The spec proposes `user_wallets`, `user_payout_profiles`, `payout_transactions`, `PayoutFactory`, `ProcessUserPayoutJob`. All of these already exist in better form or would conflict:
- `user_wallets` migration already exists (2026_07_12). Creating it again breaks migrations.
- `user_payout_profiles` ≡ `payout_accounts`. `payout_transactions` ≡ `payout_requests`. `PayoutFactory` ≡ `PayoutService::gatewayFor()`.
- The spec's job marks a payout `completed` on API submit (violates "webhook-confirmed truth"), holds a DB lock across an external HTTP call, refunds the wallet inside the same transaction after a call that may have succeeded (double-pay risk), auto-retries `tries=3` on a money action, stores raw bank numbers, and uses `float` for money. Do not port any of it.
- Its endpoint shapes for Grey and Raenest (`grey.sandbox.co/v1/payouts`, `api.raenest.com/v1/transfers`) are unverified guesses. Do not code against them. Use the provider's real docs after access is granted.

---

## 1. Findings that shape the design (researched 2026-09-29)

| # | Finding | Status |
|---|---|---|
| F1 | Stripe is a COLLECTION rail for us. It does not "connect" to Payoneer/Grey/Raenest. Each payout provider is a separate integration with its own funding. | Verified (Stripe docs) |
| F2 | Stripe Connect cross-border payouts: platform must be in US, UK, EEA, CA or CH, and pays connected accounts in the supported list. Flows: separate charges & transfers (no `on_behalf_of`), top-ups+transfers, destination charges (no OBO). Not self-serve outside listed regions. 0.25% fee per cross-border payout (waived UK↔EEA / within EEA). | Verified |
| F3 | Stripe Global Payouts (send to a recipient's bank, no Stripe account needed): sender must be US or UK (EEA in private preview). Needs Treasury + funded financial account. Stripe states you manage your own legal/compliance and may need a Money Transmitter licence if you hold customers' funds. | Verified |
| F4 | Global Payouts recipient list includes Kenya, South Africa, Egypt, Morocco, Tanzania, Rwanda, Senegal, Benin, Côte d'Ivoire, Ethiopia, Gambia, Madagascar, Mauritius, Mozambique, Namibia, Botswana, Tunisia, Algeria. It does NOT list Nigeria, Ghana, Uganda, Zambia, Cameroon, Zimbabwe. So Stripe alone cannot cover the countries Frank is worried about. | Verified at time of research; re-check the page before build |
| F5 | Payoneer Mass Payouts API is real: OAuth2 client-credentials, partner approval required, payee onboarding via registration link/white-label API (Payoneer runs KYC), submit payouts single/batch, webhooks for payee + payout status, cancel-payout API, 190+ countries claimed. The platform PRE-FUNDS the payouts by transferring money to Payoneer. Payoneer's own guide names Airbnb and Google as white-label clients. | Verified (Payoneer docs) |
| F6 | Grey has a business API for automated payouts (bank + mobile money, "170+ countries" marketing claim, USD/EUR/GBP balances, USDC). Public API reference not confirmed. Some user reviews report held/pending transfers with compliance questions. | Partly verified — get docs + sandbox via developers@grey.co before committing |
| F7 | Raenest (ex-Geegpay): no public payout API found. It is a RECEIVING account for freelancers (USD/GBP/EUR details). Treat it as a payee destination, not an integration partner, unless Raenest provisions API access to us. | Not verified as an API partner |
| F8 | The "workaround" of users putting Payoneer/Grey/Raenest virtual US/EU account numbers into Stripe Connect as if they were resident there is a misrepresentation risk (Stripe verifies the account holder's real country). Do NOT build product flows that encourage it. Payees who legitimately hold such accounts can receive via Payoneer/Grey rails instead. | Design decision |
| F9 | Existing gap: `StripeConnectService::accountFor()` creates the Express account without a `country`. Stripe defaults that to the platform's country, so a non-platform-country user would be onboarded as the wrong country. Verify and fix before offering Stripe cross-border. | Verify in code + Stripe docs |
| F10 | `WithdrawalService::localAmount()` only supports USD and NGN and throws for everything else. This is the current hard ceiling on international payouts. | Verified in code |
| F11 | `PayoutAccount.account_number` is stored plaintext and `StripeConnectService::findByAccountId()` queries by it. Adding `encrypted` cast breaks that lookup. See Phase 1. | Verified in code |

### What "100% compliance with Stripe" can and cannot mean
- Keep Stripe as inbound only for flows Stripe does not pay out. Do not describe Stripe-held funds as belonging to the payee.
- Who is paid matters: NaaraSim pays its own affiliates/partners/merchants commissions and rewards (platform expense), which is a far cleaner position than holding a third party's customer funds. Keep it that way in product copy and ledger naming.
- Two items CANNOT be confirmed from public docs and need a written answer before go-live (owner action, Phase 0): (a) Stripe support/account manager confirming this collect-then-pay-via-third-party model is acceptable for the account; (b) a lawyer's opinion on MSB/MTL exposure in the platform's registration country (UK/Nigeria) and any payee-country rules. Claude Code must not claim compliance; it builds the controls in Phase 5 and a go-live checklist.

---

## 2. Target architecture

```
Earnings ledgers (existing)           Payout engine (existing)                 NEW layer
merchant / partner / referral   ->    WithdrawalService*  ->  PayoutService -> CorridorRouter -> Gateway
staff / credits / ad rewards          (hold funds first)      (lifecycle)       (country+ccy      paystack | flutterwave |
                                                                                  -> ranked        stripe | paypal | cryptomus |
                                                                                  providers)       payoneer (NEW) | grey (NEW)
                                                                    ^                                   |
                                                                    |         webhooks (verified, idempotent)
                                                                    +-----------------------------------+
Treasury (NEW): per-provider float, low-balance alerts, top-up runbook, daily reconciliation, stuck-payout watchdog
Compliance (NEW): country allow-list, caps, cooling-off, name-match, screening hook, dual approval, audit
```

Principles (all already house rules — keep them):
1. Hold source funds BEFORE creating the request. Reverse on failure. Never blind-retry.
2. `paid` only from a verified webhook or a provider's synchronous final-success (Stripe Transfer). For Payoneer/Grey, never mark paid on submit.
3. External calls only inside queued jobs (`SendPayoutJob`), never in the request cycle and never while holding a DB lock.
4. Money is `decimal`/string — never float. Provider ids are idempotency keys.
5. User-facing UI shows the platform's fee and ETA, never provider cost (existing money rule 2). Admin-only screens may show provider cost.
6. Every new provider ships behind an admin flag, default OFF, sandbox first.

---

## 3. Phases (build in order; each has an acceptance check)

### PHASE 0 — Owner actions (no code; Claude Code only creates the checklist file `docs/payouts/GO-LIVE-CHECKLIST.md`)
- Confirm the Stripe account's country (this decides whether Stripe Connect cross-border / Global Payouts are even available).
- Apply for Payoneer partner/API access (Mass Payouts + white-label onboarding). Request sandbox program id.
- Request Grey Business API access + sandbox + docs. Ask Raenest whether a partner payout API exists.
- Get Stripe's written confirmation (see §1) and a lawyer's opinion.
- Decide the funding bank account(s) used to pre-fund Payoneer/Grey.
Acceptance: checklist file exists with these items and owner/date columns.

### PHASE 1 — Corridor foundation (no new provider yet)
1. Migration `payout_corridors` (additive):
   `id, country char(2), currency char(3), provider string, method string (bank|mobile_money|wallet|paypal|crypto|stripe_connect), enabled bool default false, priority smallint, min_usd decimal(12,2) null, max_usd decimal(12,2) null, platform_fee_bps smallint default 0, platform_fee_flat_usd decimal(8,2) default 0, est_provider_cost_bps smallint null (ADMIN ONLY), eta_text string null, requires_kyc_level string null, notes text null, timestamps; unique(country,currency,provider,method)`.
2. `App\Services\Payouts\CorridorRouter`:
   - `optionsFor(string $country, ?string $currency = null): Collection` — enabled corridors whose gateway `available()`, ordered by `priority`, excluding any with a tripped circuit (reuse existing CircuitOpened/Closed pattern if it fits; else simple health flag).
   - `pick(...)` returns first option; admin can override per request.
   - Seed conservative defaults: NG/NGN → paystack, flutterwave; existing supported countries → their current resolver; everything else empty (disabled) until Phase 2/3.
3. Additive migration on `payout_accounts`: `method string default 'bank'`, `corridor_id nullable fk`, `provider_status string nullable`, `details encrypted-json nullable` (IBAN/SWIFT/sort code/branch/mobile number etc. — varies per country), `payee_kyc_name string nullable`. Keep existing columns.
4. Generalise FX: add `CurrencyService::usdTo(string $ccy): float` (fed by existing `SyncFxRates`). Replace the `match` in `WithdrawalService::localAmount()` with `usdTo()`, throwing `PayoutException` only when no rate exists. Add to `payout_requests` (additive): `usd_amount decimal(18,4) null, fx_rate decimal(18,8) null, platform_fee_usd decimal(12,4) default 0, corridor_id nullable, quote_expires_at nullable`. Lock the quote at request time (already the existing behaviour for NGN).
5. Encryption hardening: set `account_number` to `encrypted` cast ONLY after moving Stripe's connected-account id lookup to `provider_recipient_ref` (or a `lookup_hash` blind-index column). Write a data migration that re-encrypts existing rows in chunks. Update `StripeConnectService::findByAccountId`, `StripePayoutGateway::createRecipient/sendTransfer` accordingly. Masking accessor stays.
6. Fix F9: pass `country` (from `users.country_code`) on Stripe account creation; refuse Stripe corridor when country unsupported per corridor table.
Acceptance: existing payout test suite still green; new tests: router ordering/filtering, FX generalisation (USD, NGN, one extra currency), migration reversibility, encrypted-cast round trip, Stripe lookup after cast change.

### PHASE 2 — Payoneer gateway (recommended primary international rail)
Files: `Payouts/PayoneerPayoutGateway.php` (implements `PayoutGatewayInterface`), `Payouts/PayoneerClient.php` (token cache 30-day OAuth via Redis, retries on 5xx for READ calls only), `Payouts/PayoneerPayeeService.php` (mirrors `StripeConnectService`), Livewire onboarding return route, config keys in `config/services.php` + `.env.example` + `ProviderKeys`.
Flow:
1. User picks "Payoneer" for their country → `PayoneerPayeeService::registrationLink($user, $returnUrl)` (fresh link each time, never cached, like Stripe account links).
2. Payoneer webhook (payee approved/declined) → set `payout_accounts.provider_recipient_ref = payee_id`, `is_verified = approved`, `provider_status`. Declined → `is_verified=false` + user message.
3. `createRecipient()` returns the stored `payee_id` (no separate object).
4. `sendTransfer()` → Submit Mass Payout with `client_reference_id = payout_requests.reference` (idempotency), amount as string with 2dp, currency from corridor. Return `processing` with provider ref. NEVER `paid`.
5. `parseWebhook()` maps: request received/accepted/sent-to-bank → ignore (still processing); account/card loaded → `paid`; failed bank transfer / canceled / declined → `failed`. Signature verification per Payoneer's webhook-security section (constant-time compare, timestamp tolerance if provided).
6. Add `'payoneer'` to `PayoutWebhookController::PROVIDERS`, register `payout.payoneer` singleton and add to the `PayoutService` gateway list. Log signature header in `WebhookLog`.
7. Cancel path: admin "Cancel at provider" action → Payoneer Cancel Payout API, only while `processing` and not yet delivered; result still resolved by webhook.
8. Call Payoneer's "Get Register Payee Format" per country/currency to drive the account-details form (do not hard-code bank field formats).
Acceptance (sandbox): payee onboarding round trip; payout happy path ends `paid` only via webhook; duplicate webhook is a no-op; failed webhook → `failed` + `PayoutReversed` returns held funds; duplicate `SendPayoutJob` never double-submits; conflict cases (paid-after-failed) alert admin via existing `payout_confirm_conflict`.

### PHASE 3 — Grey gateway (bank + mobile money; second international rail)
Blocked until Grey provides docs/sandbox. Build against THEIR reference, not the research doc's guessed URLs.
- `GreyPayoutGateway` + `GreyClient`. Idempotency via reference header/field if supported; else a local `payout_provider_calls` guard table (unique on reference+provider).
- Beneficiary model: bank details (from `payout_accounts.details`) or mobile money number. Grey may not offer account-name resolution → use the existing PayPal-style double-entry confirmation UX and the Phase 5 name-match rule.
- Webhooks: same controller pattern, allow-list `'grey'`.
- Corridors seeded disabled; admin enables per country after sandbox verification.
Acceptance: same suite as Phase 2 against Grey sandbox.

### PHASE 3b — Stripe corridors (US LLC platform confirmed; build AFTER Phase 2 so Payoneer is the safety net)
Order inside 3b: (1) Global Payouts first for non-Stripe-account recipients (needs Treasury/financial account approval on the LLC — Phase 0 owner action); (2) Connect cross-border only for recipients in Stripe's supported regions, and only after re-reading the current service-agreement rules, because Stripe's docs conflict across versions. Corridor rows are seeded ONLY from Stripe's live recipient list at build time.
- Connect cross-border: extend `StripeConnectService` (correct `country`, correct service agreement per current Stripe docs — the docs currently say cross-border Connect needs the FULL agreement and Global Payouts covers recipient-agreement cases; re-read before coding). Corridor rows only for countries on Stripe's current list.
- Global Payouts: separate `StripeGlobalPayoutGateway` (Treasury financial account, recipient objects, email-based recipient forms). Gate behind owner confirmation of the MTL/compliance position (F3).
- Raenest: no gateway. Add a "Raenest / Grey / Payoneer virtual account" hint in the account form that tells users to enter those details as an ordinary USD/GBP/EUR bank account under the Payoneer or Grey rail, never as a fake US identity.

### PHASE 4 — Treasury, reconciliation, watchdogs
1. `payout_float_balances` (provider, currency, balance, low_threshold, last_synced_at) + `payout_float_movements` (top-ups, payouts, adjustments; append-only). `provider:float-sync` command pulls provider balances where the API allows; otherwise admin records top-ups manually.
2. Pre-send guard in `PayoutService::send()`: if provider float < request amount → do NOT fail the user; move to a new `awaiting_funds` state (or keep `approved`) and alert admin. Add the status constant + admin filter; keep `isFinal()` correct.
3. `FinancialReconciliation`: add per-provider `sent`, `confirmed`, `pending`, `failed` and float delta; flag mismatches.
4. `payouts:reconcile-stuck` scheduled command: any request `processing` longer than a per-provider SLA → poll provider status API (if any) then `AlertAdminJob`. First check whether an equivalent already exists; extend instead of duplicating.
5. Extend `SchedulerHealth`/heartbeats for the new commands.
Acceptance: simulated low float leaves requests recoverable and never loses held funds; reconciliation report lists a seeded mismatch.

### PHASE 5 — Lean controls (owner position 2026-09-29: rewards/commissions are a platform expense, no user funds held)
Design rule that keeps this lean: ONLY platform-funded earnings are withdrawable (referral rewards, partner/staff profit-share, merchant commission, ad rewards). Money a user deposited or paid for services is NEVER withdrawable. Add a test that fails if any withdrawable bucket can be credited from a top-up or a purchase refund path. If that rule ever changes, STOP and re-open the full compliance scope (licensing questions change).
KEEP (cheap, and protect against reward fraud / US-LLC sanctions exposure):
- Country deny list (sanctioned/unsupported), admin-editable, deny beats corridor. Applies regardless of whose money it is.
- Existing free-payout threshold + KYC-L2 (already built). Add: KYC-L2 before the FIRST payout on any non-local (Payoneer/Grey/Stripe) corridor.
- Per-corridor min/max and one daily + monthly USD cap per user.
- Cooling-off 48h after adding/changing a payout account (2FA re-auth to change).
- Name-match KYC name vs account name -> mismatch goes to manual review, not auto-send.
- Auditor::log everywhere, masked account numbers, no PII in logs.
- Referral-farming signals (many accounts per device/IP, earn-then-withdraw fast): flag to admin only.
DEFER (build only if volume or a provider/lawyer asks): dual admin approval, pluggable sanctions-screening service (leave `SanctionsScreeningInterface` as a null stub), tax-form collection (Payoneer/Stripe collect from payees themselves).
Acceptance: tests for deny-list beating a stale corridor, cap edges, cooling-off bypass attempt, and the "no deposit-funded balance is withdrawable" guard.

### PHASE 6 — UX
User (`Withdraw`, `PayoutDashboard`): country picker → available methods from `CorridorRouter` with ETA + platform fee + locked quote countdown; provider-specific onboarding step (Payoneer redirect / Grey details form); clear statuses; failed payout returns credits with plain-language reason.
Admin (`Admin/Payouts`): corridor manager (enable/priority/limits/cost), provider float dashboard, stuck/awaiting-funds queue, cancel-at-provider, manual mark-paid WITH required evidence note (audited), route override, kill switch per provider.
Localization strings through the existing localization layer.

### PHASE 7 — Hardening & go-live
- Sandbox end-to-end script per provider; webhook replay tests; chaos cases (timeout after submit, duplicate webhook, out-of-order webhook, provider says paid then failed).
- Feature flags: `payouts.provider.{name}.enabled` via `PayoutSettings`/`Setting`; staged rollout: admin-only test payees → 1 corridor → widen.
- Runbooks in `docs/payouts/`: top-up float, handle stuck payout, provider outage, reconcile a mismatch, rotate keys.
- Real keys last (CLAUDE.md rule).

---

## 4. Data model summary (all additive)
- NEW `payout_corridors`, `payout_float_balances`, `payout_float_movements`, optional `payout_provider_calls`.
- ALTER `payout_accounts` (+method, corridor_id, provider_status, details, payee_kyc_name; account_number → encrypted after Stripe lookup move).
- ALTER `payout_requests` (+usd_amount, fx_rate, platform_fee_usd, corridor_id, quote_expires_at, new status `awaiting_funds`).
- No new wallet/profile/transaction tables.

## 5. Config / env additions
`services.payoneer.{client_id, client_secret, program_id, base_url, webhook_secret}`, `services.grey.{api_key, base_url, webhook_secret}`, add all to `.env.example`, `ProviderKeys`, and `EnvCheckExampleCommand` expectations. Sandbox base URLs by default.

## 6. White-label discipline
Per CLAUDE.md: treat corridor management, float dashboards and provider-cost views as MASTER-ONLY until the porting test says otherwise. Do not port anything to the two white-label repos in this build. Check committed `HEAD` state of a child repo before assuming parity. Do not touch CI triggers.

## 7. Test plan (local PHPUnit only)
Unit: router, fee/FX maths (decimal), state transitions, webhook mapping. Feature: full lifecycle per gateway with HTTP fakes; idempotency; concurrency (two workers same request); compliance rules. Regression: existing payout, wallet, referral, merchant, partner and staff payout tests.

## 8. Open decisions (defaults if owner does not answer)
1. Stripe account country → ANSWERED (owner, 2026-09-29): US LLC. Phase 3b is IN SCOPE. Stripe Global Payouts (US sender) and Connect cross-border are both candidates; Payoneer/Grey still cover countries Stripe lists nowhere.
2. Primary international rail → default Payoneer, Grey second, PayPal third, crypto last resort (admin-toggle).
3. Funding source → default manual float top-ups tracked in `payout_float_movements`.
4. Dual-approval → deferred (see Phase 5).
5. Cooling-off → default 48h.
