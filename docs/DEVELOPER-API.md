# NaaraSim Developer API — Reference (v1)

Resell NaaraSim connectivity — **eSIM data** and **numbers** (OTP / rental) —
from your own app. You call a simple REST API, pay a **prepaid wholesale price**,
and relay the delivery (QR / activation code, or phone number + OTP) to your
customers.

> **Status:** eSIM and number catalogue/quoting/ordering/status are live.
> Permanent numbers, webhooks, and the in-app interactive sandbox are on the
> roadmap.

---

## 1. Base URL

```
https://YOUR-DOMAIN/api/v1
```

The API is **disabled by default**. If every request returns `404`, the platform
operator hasn't switched the Developer API on yet — ask them to enable it.

## 2. Authentication

Every request is authenticated with a **bearer token** (your API key):

```
Authorization: Bearer {your-api-key}
```

- Keys look like `7|Xy8s…` and are issued from your **Developer** area (or by the
  operator). The full key is shown **once** at creation — store it securely; only
  its last four characters are ever shown again.
- A key belongs to an **API client**. Suspending the client (or your account)
  disables the key immediately.
- Missing/invalid key → `401`. Valid key without the required scope → `403`.

### Scopes

Each key is granted a subset of scopes. A call needs the scope shown on the
endpoint, or it returns `403`.

| Scope | Grants |
| --- | --- |
| `catalogue` | Browse available plans |
| `quote` | Get live prices |
| `order` | Place orders (spends your balance) |
| `status` | Read your order status |

## 3. Money model (what you pay)

- Every price is **your developer price** = wholesale + a small operator markup.
  It is below the retail price your end-customers would pay directly, and the
  **provider's cost is never exposed**.
- Orders are **prepaid**: they draw down your client's **API balance** (USD).
  Top up in the Developer area. An order against an empty balance returns `402`
  and provisions nothing.
- Prices are **USD** and are returned to 4 decimal places.

## 4. Idempotency

Pass a `reference` on `POST /orders` (any string ≤ 64 chars, unique per key). If
you retry with the same `reference`, you get back the **same order** — you are
never double-charged and nothing is double-provisioned. Omit it and the server
generates one.

## 5. Rate limits

Requests are rate-limited per key (default **300/min**). Exceeding it returns
`429 Too Many Requests`.

## 6. Errors

Errors are JSON. Business errors carry an `error` code + `message`:

```json
{ "error": "insufficient_balance", "message": "Top up your API balance and retry." }
```

Auth/validation errors follow Laravel's shape (`{"message": "..."}`, and
`{"message": "...", "errors": { ... }}` for validation).

| Status | When |
| --- | --- |
| `400/422` | Invalid input (bad/missing fields, unknown plan) |
| `401` | Missing or invalid API key |
| `402` | Insufficient API balance |
| `403` | Key lacks the required scope, or client not permitted |
| `404` | API disabled, or resource not found |
| `429` | Rate limit exceeded |
| `500` | Order could not be finalised (your balance is auto-refunded) |
| `502` | No provider could fulfil right now (your balance is auto-refunded) |

---

## 7. Endpoints

### GET `/catalogue` — list eSIM plans
Scope: `catalogue`

```bash
curl https://YOUR-DOMAIN/api/v1/catalogue \
  -H "Authorization: Bearer $NAARA_KEY"
```

Optional filter — `?has_voice=true` returns only **Naara Connect** plans (Full
eSIMs: calls + data), `?has_voice=false` returns only data-only plans.

**200**
```json
{
  "data": [
    {
      "id": 12,
      "name": "Nigeria 5GB / 30 days",
      "type": "local",
      "has_voice": false,
      "data_mb": 5120,
      "validity_days": 30,
      "countries": ["NG"],
      "price_usd": 11.0,
      "currency": "USD"
    }
  ]
}
```

`has_voice` distinguishes the two eSIM lines: `false` is a data-only plan,
`true` is a Naara Connect Full eSIM (voice minutes + SMS + data on one eSIM).

### POST `/quote` — price without ordering
Scope: `quote`

**eSIM**
```bash
curl -X POST https://YOUR-DOMAIN/api/v1/quote \
  -H "Authorization: Bearer $NAARA_KEY" -H "Content-Type: application/json" \
  -d '{ "type": "esim", "plan_id": 12 }'
```
```json
{ "type": "esim", "plan_id": 12, "price_usd": 11.0, "currency": "USD" }
```

**Number** (quote only for now)
```bash
curl -X POST https://YOUR-DOMAIN/api/v1/quote \
  -H "Authorization: Bearer $NAARA_KEY" -H "Content-Type: application/json" \
  -d '{ "type": "number", "number_type": "otp", "country": "nigeria", "service": "whatsapp" }'
```
```json
{ "type": "number", "number_type": "otp", "country": "nigeria",
  "service": "whatsapp", "price_usd": 0.23, "currency": "USD" }
```
`422 { "error": "unavailable" }` if no number is available for that country/service.

| Field | Type | Notes |
| --- | --- | --- |
| `type` | string | `esim` or `number` |
| `plan_id` | int | required when `type=esim` |
| `number_type` | string | `otp` or `rental`, required when `type=number` |
| `country` | string | required when `type=number` (e.g. `nigeria`, `usa`) |
| `service` | string | required when `type=number` (e.g. `whatsapp`) |

### POST `/orders` — place an order
Scope: `order` · **spends your API balance**

```bash
curl -X POST https://YOUR-DOMAIN/api/v1/orders \
  -H "Authorization: Bearer $NAARA_KEY" -H "Content-Type: application/json" \
  -d '{ "type": "esim", "plan_id": 12, "reference": "your-order-abc123" }'
```

**201 Created**
```json
{
  "reference": "your-order-abc123",
  "kind": "esim",
  "status": "processing",
  "price_usd": 11.0,
  "currency": "USD",
  "result": {
    "iccid": "8944…",
    "qr_code": "https://…/qr.png",
    "lpa": "LPA:1$rsp.example.com$ACTIVATION-CODE"
  },
  "created_at": "2026-07-20T12:00:00+00:00"
}
```
Relay `qr_code` (or the `lpa` string for manual install) to your customer. Poll
`GET /orders/{reference}` until `status` is `completed`.

**Number order**
```bash
curl -X POST https://YOUR-DOMAIN/api/v1/orders \
  -H "Authorization: Bearer $NAARA_KEY" -H "Content-Type: application/json" \
  -d '{ "type": "number", "number_type": "otp", "country": "nigeria",
        "service": "whatsapp", "reference": "your-otp-xyz" }'
```
```json
{
  "reference": "your-otp-xyz",
  "kind": "number",
  "status": "processing",
  "price_usd": 0.23,
  "currency": "USD",
  "result": { "number": "+234…", "code": null },
  "created_at": "2026-07-20T12:00:00+00:00"
}
```
The verification code arrives asynchronously — poll `GET /orders/{reference}`;
once received, `status` becomes `completed` and `result.code` holds the code.
(A number that never receives a code times out: `status` becomes `failed`.)

| Field | Type | Notes |
| --- | --- | --- |
| `type` | string | `esim` or `number` |
| `plan_id` | int | required when `type=esim` (an `id` from `/catalogue`) |
| `number_type` | string | `otp` or `rental`, required when `type=number` |
| `country` | string | required when `type=number` (e.g. `nigeria`, `usa`) |
| `service` | string | required when `type=number` (e.g. `whatsapp`) |
| `reference` | string | optional idempotency key (≤ 64 chars) |

Responses: `402` insufficient balance · `422` invalid plan / number unavailable ·
`502` unfulfilled (refunded) · `500` finalisation failed (refunded). A repeated
`reference` returns the original order with `200`.

### GET `/orders/{reference}` — order status
Scope: `status`

```bash
curl https://YOUR-DOMAIN/api/v1/orders/your-order-abc123 \
  -H "Authorization: Bearer $NAARA_KEY"
```

**200** — same shape as the order response; `status` advances
`processing → completed` once the eSIM is active. `404 { "error": "not_found" }`
if the reference isn't one of your orders.

---

## 8. Order lifecycle

```
POST /orders ──> charge API balance ──> fulfil at provider ──> 201 { status: processing }
                     │                        │
              (402 if too low)         (502 + refund if no provider)
                                              │
                                        persist order
                                              │
                              (500 + refund if it can't be saved)

GET /orders/{reference} ──> status: processing ──> completed
```

Money-safety guarantees: you are **never charged without delivery** — any
provider or persistence failure auto-refunds your API balance; retries with the
same `reference` are safe.

## 9. Quick start

1. Get a key from your **Developer** area (scopes: `catalogue`, `quote`, `order`,
   `status`) and top up your API balance.
2. `GET /catalogue` → pick a `plan_id`.
3. `POST /quote` → confirm the price.
4. `POST /orders` with a unique `reference` → hand `result.qr_code` to your
   customer.
5. `GET /orders/{reference}` → confirm `completed`.

> Endpoints, scopes and shapes above match the implementation exactly. Permanent
> numbers, webhooks (delivery/OTP callbacks) and the in-app interactive sandbox
> are on the roadmap (`docs/ROADMAP-PAYOUTS-MERCHANTS-API.md` §Layer 2).
