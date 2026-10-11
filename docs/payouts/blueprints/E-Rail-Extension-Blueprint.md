# NaaraSim — Payout Rail Extension Blueprint (Payoneer · Grey · Stripe Global Payouts)

**Purpose.** Hand this file back to Claude when you have the provider access listed in Part 2. It contains everything needed to build each rail as a **separate, signed, installable zip** that you apply through **Admin → Platform Updater** on your production site, without touching the payout rails that already work (Paystack, Flutterwave, Stripe Connect, PayPal, Cryptomus, Manual).

**Status of the platform today (already built, tested, in `main`):**
- The extension point exists. Anything dropped in `app/PayoutRails/<slug>/` with a valid `rail.json` is picked up automatically: registered with the payout engine, the webhook route, the Rail Guide, the Funding Radar and Payout Health. Nothing else in the core changes.
- Until a rail's folder is installed, Payoneer, Grey and Stripe Global show as **Coming soon** (Admin → Payouts → Payout health, "Rails coming through the Platform Updater") and are **never offered to users**. Their absence cannot block or slow any other rail.
- A broken, mismatched or hostile extension is skipped and reported; it cannot take the site down or touch a core rail. You can switch an installed rail off at any time without deleting it (Admin → All payout settings → Rail extensions → "Switched-off extensions").
- Automatic payouts, the Payout Guardian, the clawback policy, the member-to-member transfer (for countries with no rail) and the settings screen do not depend on any of these three rails.

---

## Part 1. How a rail is delivered

### 1.1 Package layout (one folder, one migration, optional lang)

```
app/PayoutRails/Payoneer/
    rail.json                         manifest (below)
    PayoneerPayoutGateway.php         implements PayoutGatewayInterface + DeclaresCapabilities (+ SupportsStatusLookup, ReportsBalance when the provider supports them)
    PayoneerClient.php                thin HTTP client (timeouts, auth, error mapping) - optional helper
    ...                               any other helper classes, ALL listed in rail.json "files"
database/migrations/2026_xx_xx_xxxxxx_install_payoneer_rail.php   seeds corridor rows (DISABLED) + registry rows
lang/en/payoneer.php                  user-facing strings (en first; ar/fr/sw as translated)
```

`rail.json`:

```json
{
  "slug": "payoneer",
  "label": "Payoneer",
  "provider": "payoneer",
  "gateway": "App\\PayoutRails\\Payoneer\\PayoneerPayoutGateway",
  "version": "1.0.0",
  "min_core": "1.0.0",
  "rail": "global",
  "files": ["PayoneerClient.php", "PayoneerPayoutGateway.php"]
}
```

Rules enforced by `PayoutRailExtensions` (tests: `tests/Feature/PayoutRailExtensionsTest.php`):
- `slug` equals the folder name; `provider` is lower-case letters/digits/underscore; it may **not** be a core name (`paystack`, `flutterwave`, `stripe`, `paypal`, `cryptomus`, `manual_external`).
- Every file listed in `files` must sit inside the rail folder and be `.php`; they are `require_once`d, so the rail works even when composer's autoloader is optimised (shared cPanel).
- The class must implement `PayoutGatewayInterface` **and** `DeclaresCapabilities`, and `name()` must equal `provider`.
- Anything failing these checks is skipped with a message shown on Payout health.

### 1.2 Build and ship (separate branch, signed update)

1. Branch from `main`: `git checkout -b rail/payoneer`.
2. Add the folder, migration and lang file above plus the tests in Part 5. Do **not** edit core files unless a core change is unavoidable (then it is called out in the PR).
3. Run the full suite and the rail's sandbox checks (Part 4).
4. Build the signed package on the build machine (the private signing key never lives on a server):
   `php artisan update:package --from=<last release tag> --key=<private key file> --changelog="Payoneer payout rail 1.0.0" --master-only`
   (`--master-only` keeps it off white-label distribution; drop it only if the owner decides the rail should reach white labels.)
5. On production: Admin → Platform Updater → upload the `.naaraupdate` → the updater verifies the signature, takes a backup, applies files, runs the migration, health-checks, and rolls back automatically on failure.
6. In Payout health the rail turns from **Coming soon** to **Installed**. It is still **not offered** to users until you enable a corridor (Admin → Payouts → Corridors) for each country you have confirmed with the provider.

### 1.3 Rollback

Admin → All payout settings → Rail extensions → add the slug to "Switched-off extensions" (instant, keeps files). To remove entirely, ship a package whose manifest lists the folder under `deletions`. In-flight payouts on a switched-off rail are not refunded blindly: they go through the unknown-outcome reconciler, which asks the provider.

---

## Part 2. What you must provide per rail (Claude will not guess any of it)

### 2.1 Stripe Global Payouts (`stripe_global`)

Confirmed (docs.stripe.com/global-payouts, /send-money, /recipient-creation, 2026-10-02):
- Pays people who are **not** Stripe account holders in 160+ countries.
- Available to businesses **located in the US and UK** (private preview for some EU countries and Australia). **Requires Stripe Treasury** and a funded financial account. Stripe notes it is best for businesses that already hold a money-transmitter licence and that you manage your own legal and compliance requirements.
- API: `POST /v2/money_management/outbound_payments` (minor units, `from.financial_account`, `to.recipient`, optional `to.payout_method`); recipients are v2 Accounts with `configuration.recipient`; payout methods via Outbound Setup Intents; restricted API key; pinned to a **preview** API version.

You provide:
- [ ] US (or UK) entity registered and Stripe account approved for **Treasury** and **Global Payouts**.
- [ ] Sandbox/test API restricted key with Treasury + OutboundPayments permissions; the financial-account id; the exact preview API version string Stripe pins for your account.
- [ ] Written confirmation of which recipient countries/currencies your account can pay (Stripe's list is per account).
- [ ] Lawyer note on the money-transmission question for this flow.

Still to confirm against the sandbox before coding the money path: outbound payment status values and the webhook event names/ids (`v2.money_management.outbound_payment.*`), idempotency header behaviour on v2, how a returned/failed payment is reported, cancel window, fee model.

### 2.2 Payoneer (`payoneer`)

Confirmed (payoneer.com/developers-docs/mass-payout, partly public):
- Submit: `POST https://api.payoneer.com/v4/programs/{program_id}/masspayouts` (sandbox host `api.sandbox.payoneer.com`), bearer token, `Payments[]` of `{client_reference_id, payee_id, amount (string), currency, description}`, up to 500 per call; only registered/Active payees; insufficient funds = not processed; asynchronous.
- Payee onboarding: `POST …/payees/registration-link`, status via `GET …/payees/{id}/status` or webhook (Active / Pending / Declined).

Blocking unknowns (partner-gated): `client_reference_id` length/charset/uniqueness and idempotency; **payout status endpoint and status values**; **webhook signature scheme and event names**; cancel; balance endpoint; token/auth refresh details. A third-party aggregator shows a different shape, so do not trust it.

You provide:
- [ ] Payoneer partner/API approval and the **program-specific Payout Integration Guide**.
- [ ] Program id, sandbox credentials, webhook secret and the exact signature algorithm from the guide.
- [ ] The list of payee countries/currencies your program supports and the fee schedule.

### 2.3 Grey (`grey`)

Grey advertises payout APIs (170+ countries) but publishes **no public documentation**.

You provide:
- [ ] Grey business API access and the full API reference: auth, create-recipient, create-transfer, **idempotency**, status lookup, webhook signature, balances, limits, fees, supported corridors.
- [ ] Sandbox credentials.

Without the reference nothing is built.

---

## Part 3. Gateway contract (what every rail must implement)

Interfaces (all in `app/Services/Payouts/`):

| Interface / class | Must | Notes |
|---|---|---|
| `PayoutGatewayInterface::name()` | return the manifest `provider` | |
| `available()` | true only when keys are configured **and** `PayoutEnvGuard::allows(name)` | A live key outside live mode (or a test key inside it) must be "not available" |
| `createRecipient(PayoutAccount)` | return the provider's recipient/payee ref; cached on the account | For Payoneer/Grey the payee must be registered/Active first: if not, throw a `PayoutException` with a plain message so the request is held, never half-sent |
| `sendTransfer(PayoutRequest, PayoutAccount): PayoutTransferResult` | send **once**, using `$request->wireReference()` as the provider-side idempotency/reference value | Return `processing` for async rails, `paid` only if the provider confirms finality synchronously, `failed` only for a definitive provider rejection. **Never** throw for a normal rejection. Timeouts/5xx bubble up: the engine then marks the call `unknown` and the reconciler resolves it from the provider. **No call inside a DB transaction.** |
| `verifyWebhook(Request)` | constant-time HMAC/signature check on the **raw body** (`hash_equals`) | Reject before touching the payload |
| `parseWebhook(Request): ?PayoutEvent` | map to `paid | failed | reversed` and carry **our** reference | Webhook dedupe is automatic (`payout_webhook_events`), but supply a provider event id when one exists |
| `DeclaresCapabilities::capabilities()` | state truthfully `confirms_synchronously / webhook / lookup / cancel` | A rail without lookup is routed to a human for unknown outcomes instead of being refunded blindly |
| `SupportsStatusLookup::lookupTransfer` | **required for any rail that can time out** | Look up by our reference; return `LookupResult::found/notFound/unsupported`. `notFound` is only allowed when the provider documents that "not found" proves nothing was sent |
| `ReportsBalance::balances()` | optional | Major units per currency; used by Float auto-sync |

Money rules that apply (CLAUDE.md): provider call only from the queued `SendPayoutJob`; idempotent on our reference; amounts converted with the shared currency-decimals table; cost/fees never shown to users; every failure path ends in refund-and-alert or reconcile, never a blind retry.

Guardian note: `AccountIntegrityGate::NEEDS_ENROLLMENT` currently holds every `payoneer`/`grey` request for a person ("enrollment_unverifiable"). When a rail is built, replace that with the real enrollment check (active `payout_rail_enrollments` row + saved guide acknowledgement) in the rail's own PR, and decide separately whether to add the provider to `payouts.auto_approve_default_providers`. Default stance: **new global rails stay manual-review until they have a clean track record**.

---

## Part 4. Sandbox checks required before any corridor is enabled

For each rail, record results in `docs/payouts/GO-LIVE-CHECKLIST.md`:
1. Authenticated read (balance or program info) succeeds.
2. Create recipient/payee; status transitions observed.
3. Send a minimum-value transfer with a reference; observe the provider's status values and map every one.
4. **Re-send the same reference**: confirm the provider dedupes (or document that it does not; then the gateway must refuse a second send itself).
5. Force a failure (invalid destination) and a reversal/return; confirm the webhook payloads.
6. Replay the same webhook twice and confirm one ledger effect.
7. Kill the worker mid-call (or time out) and confirm the reconciler resolves the outcome through `lookupTransfer`.
8. Bad-signature webhook returns 401 and changes nothing.

---

## Part 5. Tests to ship with each rail

- Gateway unit tests with `Http::fake`: success, definitive failure, timeout (-> unknown), every provider status mapped, reference format, amount minor-unit conversion, env-guard behaviour.
- Webhook tests: valid/invalid signature, duplicate event, unknown reference.
- Lookup tests: found(paid/failed/processing), notFound, unsupported.
- Extension-loader test: the rail loads, appears as Installed, can be switched off.
- Integration: a request routed through `PayoutService::send()` ends in the right state, ledger holds balance, invariants checker passes.
- Regression guard: with the rail folder removed, Paystack/Flutterwave/Stripe automatic payouts still pass `PayoutAutomationTest`.

Acceptance: full suite green, sandbox checklist signed off, rail shows Installed, corridor disabled until you enable it, rollback switch tested.

---

## Part 6. Order of work when access arrives

1. **Stripe Global** first if you obtain Treasury (best documented, one API family; sandbox is self-serve once Treasury is enabled).
2. **Payoneer** once the integration guide arrives (the unknowns in 2.2 are all answered by it).
3. **Grey** only when documentation exists.
Each is its own branch and its own zip. They never depend on one another.

## Part 7. The message to give Claude later (copy/paste)

> Build the `<stripe_global | payoneer | grey>` payout rail as an updater-delivered extension, following `docs/payouts/blueprints/E-Rail-Extension-Blueprint.md`. Here is the provider documentation and sandbox credentials: <attach/paste>. Work on a new branch `rail/<name>`, do not change core payout behaviour, write the tests in Part 5, run the sandbox checks in Part 4, and give me the signed `.naaraupdate` build command plus the go-live steps. Master only.

White-label discipline: these rails are master-only unless you decide otherwise; a white-label install keeps seeing them as "Coming soon" or not at all.
