# Owner checklist — things only you can do or decide (Addendum D, Part 6)

Nothing here is code. Tick each before the matching go-live gate in `GO-LIVE-CHECKLIST.md`.

## Providers (blocks real money)
- [ ] Paystack: switch OFF transfer OTP in the dashboard; sandbox keys in `.env`; confirm in the sandbox the transfer reference rules, `GET /transfer/verify/{reference}`, `GET /balance` (units) and `GET /country`.
- [ ] Flutterwave, PayPal, Cryptomus: sandbox keys and a note of each provider's real minimum, fee, settlement time and webhook behaviour.
- [ ] **Stripe entity decision:** is the paying Stripe account US- or UK-based and approved for Treasury? Global Payouts (the only Stripe route to Nigeria/Ghana/Kenya/South Africa recipients) requires both, and Stripe says it suits businesses that already hold a money-transmitter licence. Connect cross-border payouts only reach US/UK/EEA/CA/CH. See PROVIDER-RESEARCH.md.
- [ ] Stripe: written confirmation that Global Payouts / Treasury and the collect-then-pay model are fine for the account.
- [ ] Payoneer: partner/API access + sandbox. Grey: API access + docs. Raenest: destination only unless they grant an API.
- [ ] Until a provider is live, the **Manual rail** (Admin → All payout settings → Manual rail) lets you pay by hand and record proof.

## Legal and tax
- [ ] Lawyer: short opinion that paying platform-funded rewards (never user deposits) is outside money transmission in your registration country/state and the payee countries. Terms wording on withholding/reversing for fraud, payout timelines, returned payouts, clawbacks.
- [ ] CPA: US reporting/withholding for payees. Use `payouts:annual-summary {year}` as the input. The tax gate (Admin → settings → "Ask for tax details above") stays 0 until they advise.
- [ ] Privacy policy: data shared with payout providers, retention, user rights.

## Decisions (each has a safe default in Admin → All payout settings)
- [ ] Tier limits for the Guardian (new / trusted / VIP) and the daily auto-approval cap.
- [x] Earnings maturity days: default is now **7** (as recommended for card-funded earnings). Change in Admin → All payout settings.
- [ ] Whether to switch on the security-code step (recommended before any global rail goes live).
- [ ] Send delay for new destinations (default 10 minutes on global rails).
- [x] Clawback policy: decided (human-triggered reversal from Payout health; the earner may go into debt that future earnings repay; withdrawals blocked while in debt). See `D-COVERAGE.md` G-11.
- [ ] Who holds which role: `payouts.review` (decide requests) and `payouts.finance` (float, reconciliation, exports) are staff scopes you grant in Admin → Staff. Settings, kill switches and trust overrides stay super-admin.
- [ ] Returned-payout policy (default: money returns to the user's balance, account flagged, locked on the second return).

## Drills (go-live gates)
- [ ] Restore drill once (`RUNBOOKS.md` §Restore).
- [ ] Kill-switch drill once (Admin → Payouts → pause auto-approvals; payouts enabled off).
- [ ] Forced low-float test so the alert is seen.

## Rails that arrive later (Payoneer, Grey, Stripe Global)
- [ ] Keep `blueprints/E-Rail-Extension-Blueprint.md` safe. When provider access exists, give it back to Claude with the provider docs: each rail is built on its own branch and shipped as a signed zip through the Platform Updater. Until then they show as "Coming soon" and nothing else is affected.
- [ ] US LLC -> Stripe account with Treasury + Global Payouts approval; Payoneer partner approval + integration guide; Grey API docs.

## Automatic payouts: what must be true
- [ ] `payouts.enabled` switched on (the one switch), a Paystack key in `.env`, the Laravel scheduler running (`php artisan schedule:run` every minute), and Paystack transfer OTP off. Admin → Payouts → Payout health shows a live checklist of exactly these.
- [ ] Member-to-member transfer (for countries with no rail) is on by default with strict limits: see `PEER-TRANSFER.md`.
