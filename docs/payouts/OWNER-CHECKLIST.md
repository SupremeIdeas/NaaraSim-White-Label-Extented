# Owner checklist — things only you can do or decide (Addendum D, Part 6)

Nothing here is code. Tick each before the matching go-live gate in `GO-LIVE-CHECKLIST.md`.

## Providers (blocks real money)
- [ ] Paystack: sandbox keys in `.env`; confirm in the sandbox the transfer reference rules, `GET /transfer/verify/{reference}`, `GET /balance` (units) and `GET /country`.
- [ ] Flutterwave, PayPal, Cryptomus: sandbox keys and a note of each provider's real minimum, fee, settlement time and webhook behaviour.
- [ ] Stripe: written confirmation that Global Payouts / Treasury and the collect-then-pay model are fine for the account.
- [ ] Payoneer: partner/API access + sandbox. Grey: API access + docs. Raenest: destination only unless they grant an API.
- [ ] Until a provider is live, the **Manual rail** (Admin → All payout settings → Manual rail) lets you pay by hand and record proof.

## Legal and tax
- [ ] Lawyer: short opinion that paying platform-funded rewards (never user deposits) is outside money transmission in your registration country/state and the payee countries. Terms wording on withholding/reversing for fraud, payout timelines, returned payouts, clawbacks.
- [ ] CPA: US reporting/withholding for payees. Use `payouts:annual-summary {year}` as the input. The tax gate (Admin → settings → "Ask for tax details above") stays 0 until they advise.
- [ ] Privacy policy: data shared with payout providers, retention, user rights.

## Decisions (each has a safe default in Admin → All payout settings)
- [ ] Tier limits for the Guardian (new / trusted / VIP) and the daily auto-approval cap.
- [ ] Earnings maturity days (default 3; the blueprint suggests 7 for card-funded earnings).
- [ ] Whether to switch on the security-code step (recommended before any global rail goes live).
- [ ] Send delay for new destinations (default 10 minutes on global rails).
- [ ] Clawback policy (see `D-COVERAGE.md` open item 1).
- [ ] Who holds which role: `payouts.review` (decide requests) and `payouts.finance` (float, reconciliation, exports) are staff scopes you grant in Admin → Staff. Settings, kill switches and trust overrides stay super-admin.
- [ ] Returned-payout policy (default: money returns to the user's balance, account flagged, locked on the second return).

## Drills (go-live gates)
- [ ] Restore drill once (`RUNBOOKS.md` §Restore).
- [ ] Kill-switch drill once (Admin → Payouts → pause auto-approvals; payouts enabled off).
- [ ] Forced low-float test so the alert is seen.
