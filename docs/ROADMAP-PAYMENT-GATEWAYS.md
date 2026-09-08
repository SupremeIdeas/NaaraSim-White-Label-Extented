# NaaraSim — Roadmap: Extra Payment Gateways (collection + payout)

> **Status: DESIGN / RESEARCH for later build.** Admin-toggleable. Extends the
> existing gateway system — it does **not** replace Paystack/Flutterwave/Stripe.
> Nothing here is built yet; this is the spec so the build is fast and safe.

## What we already have (build on this, don't rewrite)

- **`PaymentGatewayInterface`** with `initialize()` (start a top-up → redirect URL)
  and webhook verification; implementations `pay.paystack`, `pay.flutterwave`,
  `pay.stripe`, resolved by key. Webhooks are HMAC-verified with `hash_equals`.
- **`ProviderKeys`** — admin pastes keys in **Admin → API keys**; a gateway with
  no key simply shows "Coming soon". All new gateways plug into this same schema.
- **`WalletService`** credits the wallet **from the webhook**, never the redirect.
- The planned **`PayoutGatewayInterface`** (see `ROADMAP-PAYOUTS-MERCHANTS-API.md`)
  is where the payout side of each gateway lives.

## The plan

Add six gateways. Each is (a) a **collection** method (top-up) implementing
`PaymentGatewayInterface`, and — where the provider supports it — (b) a **payout**
method implementing `PayoutGatewayInterface`. Keep the two concerns separate so a
gateway can collect, pay out, or both.

### Capability matrix (from provider API docs)

| Gateway | Collect (top-up) | Payout (cash-out) | Payout target | Notes |
| --- | --- | --- | --- | --- |
| **Stripe** | ✅ (already) | ✅ **Payouts / Connect** | Bank account (cards→bank), Connect transfers | Best for international bank payouts; Connect for merchant split |
| **PayPal** | ✅ | ✅ **Payouts API** (ex-MassPay) | PayPal/Venmo balance by email/phone/payer-ID | 156 countries, 23 currencies, ≤15k/call; **Business account approval required** |
| **Binance Pay** | ✅ C2B (PAY) | ✅ **B2C Batch Payout** | Binance ID / email (crypto) | `POST /binancepay/openapi/payout/transfer`; also C2C, refund, remittance |
| **Cryptomus** | ✅ static wallet / checkout | ✅ **Payout API** (mass) | Crypto address (auto-convert) | Separate payment key + payout key; `order_id` must be unique |
| **CoinPayments** | ✅ | ✅ **create_withdrawal / mass** | Crypto address | Withdrawal states: created / needs email confirm |
| **Payssion** | ✅ 200+ **local** methods | ✅ **Payout methods** | Local wallets (e.g. Alipay) + more | One API for pay-in + pay-out; rolling settlement |

> **Crypto note:** Binance Pay / Cryptomus / CoinPayments settle in crypto. For a
> user who tops up in crypto, we credit the USD-equivalent to their NaaraSim wallet
> at the confirmed rate (locked at webhook time). Crypto **payouts** send to a
> wallet address the user provides (a new `payout_accounts.type = crypto`).

## What a user needs to request a payout (per method)

The payout flow (from `ROADMAP-PAYOUTS-MERCHANTS-API.md`) lets the user pick **any
enabled method**. Required details per type:

- **Bank (Stripe/PayPal-bank/Wise):** country, bank/routing + account number →
  we resolve + confirm the account name before saving.
- **PayPal balance:** the recipient email or phone tied to their PayPal/Venmo.
- **Crypto (Binance/Cryptomus/CoinPayments):** coin + network + wallet address
  (validated by format/checksum); Binance also supports Binance-ID/email.
- **Local wallet (Payssion):** the method (e.g. Alipay) + the account identifier.

All are stored in `payout_accounts` with `is_verified`, and the payout engine
routes each request to the right `PayoutGatewayInterface` by the account's method.

## Implementation notes (extend, don't fork)

1. Add each gateway's keys to `ProviderKeys::schema()` under **Payments** (with
   rich tooltips pointing to each dashboard) — collection key(s) + payout key(s).
2. Implement `pay.paypal`, `pay.binance`, `pay.cryptomus`, `pay.coinpayments`,
   `pay.payssion` for collection; register their webhooks (HMAC/`hash_equals`,
   idempotent on provider ref) exactly like the existing ones.
3. Implement the payout side under `PayoutGatewayInterface` (`resolveAccount`,
   `createRecipient`, `sendTransfer`, `verifyTransfer`).
4. **Admin → API keys** already gates visibility: a gateway with no key is hidden
   from the top-up + payout method pickers automatically.
5. Admin-overridable **gateway icons** (audit §5) show each method's mark.

## ⚠️ Build-safety notes (wait-for-later flags)

- **PayPal Payouts needs account approval** — build the collection side first;
  gate payout behind an admin "enabled + approved" flag.
- **Crypto rate risk** — always lock the USD↔crypto rate at confirmation; never
  re-price mid-flight. Add a small spread buffer (admin-set) so volatility never
  eats margin.
- **Regulatory** — crypto/local-wallet cash-out may be restricted per country;
  each method carries an admin allow-list of countries. Confirm licensing before
  enabling payouts (see the compliance note in the payouts roadmap).

## Sources

- [PayPal Payouts API](https://developer.paypal.com/docs/api/payments.payouts-batch/v1/) · [overview](https://developer.paypal.com/docs/payouts/)
- [Binance Pay — Batch Payout](https://developers.binance.com/docs/binance-pay/api-payout) · [Payout functionality](https://merchant.binance.com/en/docs/functionalities/payout)
- [Cryptomus API](https://doc.cryptomus.com/) · [Payout](https://doc.cryptomus.com/methods/user/payout)
- [CoinPayments — Create Withdrawal](https://www.coinpayments.net/apidoc-create-withdrawal)
- [Payssion API](https://payssion.readme.io/reference/create-payout-method) · [docs](https://www.payssion.com/en/docs/)
- Stripe — Payouts / Connect (existing gateway; extend to payouts)
