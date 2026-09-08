# eSIM Providers — integration notes

NaaraSim sells two eSIM lines, each backed by an interchangeable **failover
lane** (blueprint §6). A provider is only "Active" once its API key is saved
(Admin → API Keys); until then it shows **Coming Soon** and is skipped by the
router. Provider identity is **never** exposed to users, and every catalogue
`cost` is **wholesale + private** — retail is always computed by the
`PricingEngine`, never taken from a provider's suggested price.

| Field the user sees | Field we keep private |
|---|---|
| `final_retail_usd` (retail) | `cost_price_usd` (wholesale) |
| plan name, data, validity, countries, `has_voice` | provider name, provider refs |

---

## Naara Data — data-only lane

`ProviderRouter` chain: **eSIM Go → Airalo → Quibity → Zendit**

| Provider | Role | Auth | Cost field |
|---|---|---|---|
| **eSIM Go** | Primary | `X-API-Key` (+ `x-sandbox`) | catalogue `price` |
| **Airalo** | Secondary — honour `minimum_selling_price` | OAuth2 client-credentials | `net_price` (floor `minimum_selling_price`) |
| **Quibity / eSIM.sm** | Tertiary | `api_key` | catalogue `price` |
| **Zendit** | Data backup | `Bearer` | `cost.fixed / currencyDivisor` |

Zendit appears in **both** lanes: its data-only offers expand Naara Data, its
voice offers feed Naara Connect.

---

## Naara Connect — Full eSIM (calls + data) lane

`ProviderRouter` voice chain: **Zendit → 1GLOBAL → Monty Mobile → Gigs**

A voice purchase fails over **within this lane only** — never down to a
data-only provider (that would deliver the wrong product). `findEquivalentPlan`
matches `has_voice` exactly in both directions.

### Zendit — self-serve, LIVE in sandbox
- Docs: `https://test-api.zendit.io/swagger/doc.json` (sandbox), `developers.zendit.io`.
- Host split: sandbox `test-api.zendit.io/v1`, production `api.zendit.io/v1`
  (driven by `ZENDIT_SANDBOX`). Auth: `Authorization: Bearer <key>`.
- eSIM offer objects **do** carry `voiceMinutes`, `voiceUnlimited`, `smsNumber`,
  `smsUnlimited` alongside `dataGB`/`dataUnlimited` — so an offer can be a Full
  eSIM. **Confirmed on the sandbox key (2026-07-26): all 5,393 live eSIM offers
  are currently data-only (`voiceMinutes = 0`).** The schema proves capability,
  not live catalogue population — Naara Connect shows "coming soon" until
  production carries voice offers.
- Endpoints: `GET /esim/offers` (paged `_limit`/`_offset`), `POST /esim/purchases`
  (idempotent `transactionId`), `GET /esim/purchases/{id}` (activation
  confirmation: `iccid`, `activationCode`, `smdpAddress`),
  `POST /esim/purchases/{id}/refund`, `GET /balance` (minor units / `currencyDivisor`).
- `dataSpeeds` (`2G`..`5G`) and `roaming[]` (per-country underlying network) are
  real per-offer fields — future filter/badge sources.

### 1GLOBAL (Connect API) — partner access
- Docs: `docs.connect.1global.com`. Branded eSIM + voice, 200+ destinations.
- Auth: OAuth2 client-credentials (`ONEGLOBAL_CLIENT_ID` / `_SECRET`), Bearer per call.
- Onboarding is partner-access, not self-serve. Endpoints in `OneGlobalService`
  follow the documented Connect shape and are **confirmed on sandbox once access
  is granted** — SyncStatus surfaces any 4xx so a wrong path is never silent.

### Monty Mobile (RSP API) — sales-led
- 800+ direct MNO relationships, sub-90s provisioning, full lifecycle API.
- Auth: RSP API key as a Bearer token. Request docs/sandbox via montymobile.com's
  partner channel. Endpoints in `MontyMobileService` follow the documented RSP
  shape; confirm on the sandbox.

### Gigs (Connectivity API) — self-serve-ish
- Docs: `developers.gigs.com`. Full MVNO stack (voice + SMS + data + real number).
- Auth: Bearer API key; every resource is **project-scoped** (`GIGS_PROJECT`).
- Provisioning is a **subscription** against a plan. Endpoints in `GigsService`
  follow the documented shape; confirm on the sandbox.

---

## Adding keys / going live

1. Admin → API Keys → paste the provider's credential(s). Save broadcasts
   `queue:restart` so background workers pick up the new key within seconds.
2. Admin → API Keys → eSIM catalogue sync → **Sync now** (or `php artisan
   esim:sync <provider> --now`). The per-provider **Last synced / Last result**
   panel shows success + plan count, or the exact failure.
3. The provider flips **Active**; its plans appear on the storefront (data plans
   on **eSIM Data**, voice plans on **Naara Connect**) and in the Developer API
   catalogue (`?has_voice=true|false`).
