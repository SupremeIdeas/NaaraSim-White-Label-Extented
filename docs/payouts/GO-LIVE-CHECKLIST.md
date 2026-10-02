# Global Payout Layer — Go-Live Checklist (MASTER ONLY)

Nothing in this list is optional. Do not enable live payouts until every box is ticked.

## Provider keys and sandbox
- [ ] Sandbox keys only until the hardening module passes; live keys go in last (`.env`, never code).
- [ ] Paystack: confirm `GET /transfer/verify/{reference}` response shape on the sandbox (the reconciler's `found` / `not_found` mapping depends on it).
- [ ] Flutterwave / Stripe / other gateways: no status lookup is implemented. Unknown outcomes on these go to manual review by design. Do not add a lookup without the provider's real docs.
- [ ] Rotate provider keys every 90 days.

## Workers and schedule
- [ ] Horizon running with `supervisor-payouts` and `supervisor-payout-guard`; on cPanel the drain command (`queue:work --queue=payouts,payout-guard,default`) is on cron.
- [ ] `payouts:reconcile-unknown` is scheduled (every 2 minutes) and shows green in Scheduler Health.

## Behaviour to verify on sandbox
- [ ] Timeout during transfer -> request stays `processing`, provider call marked `unknown`, user NOT refunded.
- [ ] Provider 4xx rejection -> refunded once, call `definitively_failed`.
- [ ] Reconciler: transfer found -> confirmed; 3 not-found lookups over the grace window -> reversed once; unsupported gateway -> manual review.
- [ ] Double-click / concurrent approve -> exactly one send (CAS).
- [ ] Open-request cap and free-withdrawal count respect committed requests.

## Auto-approval (Guardian)
- [ ] `PAYOUT_AUTO_APPROVAL` stays OFF at launch. Run in shadow mode (decisions logged, no action) and review the decision log first.
- [ ] Guardian gates (G1–G9) implemented and tested before auto-approval is ever switched on. The current skeleton always sends requests to human review.

## Ledger and funds
- [x] Deposits are never withdrawable (guard tests in `PayoutFloatTest` fail if a new path credits the withdrawable bucket — if that ever changes, STOP and re-open the compliance scope).
- [ ] Float: for every rail you pay from, start tracking it (Admin -> Payouts -> Float), record the real opening balance and a low-balance alert level. An untracked rail is NOT gated by float.
- [ ] Verify on the Paystack sandbox that `GET /balance` reports minor units (kobo) before turning on `auto_sync`; until then record top-ups by hand.
- [ ] Simulate low float on the sandbox: the payout must go to `awaiting_funds`, the user's money must stay held, and a top-up must resume it (oldest first).
- [ ] Daily: open Admin -> Reconciliation -> "Payout rails"; any flagged rail (`float ledger drift` / `float payout drift`) must be explained before the next payout run.

## Owner-blocked
- [ ] Payoneer and Grey corridors (Phases 2/3): need owner account access and the real provider docs.

## Addendum D additions
- [ ] `payouts:invariants-check` green for 7 consecutive days in sandbox/shadow.
- [ ] Settlement reconciliation run for each enabled provider; no unresolved items.
- [ ] Restore drill executed once (`RUNBOOKS.md`); kill-switch drill executed once.
- [ ] In the provider sandbox: a payout sent with the derived provider reference (`ns…-…`) is accepted, and the webhook returns that same reference.
- [ ] `PAYOUT_ENV=live` only on the production box, with live keys; test keys are refused in live mode.
- [ ] Step-up (security code) decision made; Manual rail decision made.
- [ ] `OWNER-CHECKLIST.md` fully ticked.
