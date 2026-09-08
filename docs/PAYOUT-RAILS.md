# Global payout rails — research & recommendation (BUILD-4 §5.3)

**Status: research complete, integration pending Frank's rail choice + keys.**

## Why this exists
Merchant reach is now global (§2). But the two payout resolvers wired into
`app/Services/Payouts/` — **Paystack** and **Flutterwave** — are Africa-focused.
A merchant with a US/EU/UK/APAC bank account currently has no rail to be paid out
through. §5.3 extends payout coverage beyond Africa, **behind the existing
`PayoutGatewayInterface` / `PayoutAccountService`** (same seam, not a new one).

## Non-negotiable safety (§5.2 — do NOT weaken)
`PayoutAccountService::addAccount()` resolves the destination through a
country-specific resolver and **refuses to save any account whose real holder
name it can't confirm**. That is a deliberate anti-fraud property. Any new rail
added below MUST keep it: a rail that can't confirm the holder name is not a
valid resolver for saving an account.

## Coverage confirmed from current (2026) sources
| Rail | Reach | Cost (payout) | Fit |
|---|---|---|---|
| **Wise (Wise Platform)** | 40+ currencies, mid-market FX, fast bank transfers | from ~1.16%, transparent | **Best general global bank-payout rail** — cheapest, cleanest FX. |
| **Payoneer** | 190 countries, 70+ currencies | ~1% + 0.5% FX | Great for freelancer/marketplace networks; broad reach. |
| **Stripe Connect** | Global where Stripe operates | 0.25%/cross-border payout (waived EEA/UK-EEA) | Natural if Stripe is already an **inbound** gateway here — reuse the account. |
| **PayPal Payouts** | Widest reach | up to ~4.4% + fixed | Widest but costliest; a fallback, not primary. |

## Recommendation
- **Primary global rail: Wise (Wise Platform)** — add a `WisePayoutGateway` (+ a
  Wise bank resolver that confirms the recipient name) behind
  `PayoutGatewayInterface`, routed by country exactly like Paystack/Flutterwave.
- **Natural second: Stripe Connect** — Naara already integrates Stripe for
  inbound payments, so Connect payouts reuse that relationship; good for US/EU.
- **Keep Paystack / Flutterwave for African payouts.** Route by country in
  `PayoutAccountService::resolverFor()` — the method already picks "the first
  available resolver that supports the country," so a global rail simply slots in.
- Tie into §8's volume ranking: only *recommend* a rail the platform has real
  inbound volume on (see `docs/` / the §8 work).

## Integration point (ready once a rail + keys are chosen)
1. `WisePayoutGateway implements PayoutGatewayInterface` (+ `WiseBankResolver
   implements BankResolverInterface` with real holder-name confirmation).
2. Register it in the resolver/gateway lists (container), key-gated `available()`.
3. `PayoutAccountService::resolverFor()` already routes by country — no redesign.
4. Keys in Admin → API keys via settings, never hardcoded (money-safety rule 10).

## What's needed from Frank to finish §5.3
1. Pick the rail(s) — recommended: **Wise** (+ **Stripe Connect** since Stripe is
   already wired for inbound).
2. Provide the API keys / Connect platform credentials.

## Sources
- [Best global payout platforms 2026](https://connectpay.com/blog/best-global-payout-platforms/)
- [PayPal vs Payoneer vs Wise 2026](https://www.xflowpay.com/blog/paypal-vs-payoneer-vs-wise)
- [Global contractor payment methods (ACH/SWIFT/Stripe/Wise) 2026](https://omnivoo.com/blog/global-contractor-payment-methods-compared-2026)
