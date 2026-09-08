# NaaraSim — Roadmap: Admin Setup Wizard, Staff/User Management & Partners

> **Status: DESIGN for later build.** Three related admin-side features. Nothing
> built yet. Builds on what exists: `Installer` + `<x-cron-setup>`, `ProviderKeys`,
> Spatie roles (super_admin/admin/staff/user), the ticket system, `WalletService`,
> and `Auditor`.

---

## A. Admin first-run **Setup Wizard**

**Vision:** the first time an admin lands in the dashboard after install, a guided
setup opens — even a non-technical operator can follow it. Skippable, resumable,
with a progress bar.

### Flow (ordered, each step tooltip-rich)
1. **Cron & scheduler** — the one-time cron (reuse the existing `<x-cron-setup>`
   component + Copy button). **First**, because nothing background works without it.
2. **Payment gateways** — paste keys for the collection gateways (Paystack,
   Flutterwave, Stripe, + the new ones) with tooltips linking each dashboard.
3. **eSIM providers** — Naara Orbit/Roam/Skylink keys (eSIM Go / Airalo / Quibity).
4. **Number providers** — Naara Verify/Flash/Liberty/Line/Signal keys.
5. **Branding** — logos, colours, preloader (reuse Admin → Branding).
6. **Everything else** — mail, Anthropic (Price with Claude + Wizard), Turnstile,
   Wasabi, KYC/payout keys (as those layers ship).

### Behaviour
- **Progress bar + %** across the steps; each step marked done when its keys/config
  are saved (detected via `ProviderKeys::hasValue()` etc.).
- **Skip any step** ("I'll do this later") and **Minimise to dashboard**; a header
  button **Relaunch setup** re-opens it with all completed steps intact.
- **Run without the wizard** — every step is just a shortcut to an existing admin
  page, so nothing is locked behind the wizard.
- Rich **tooltips / mini-guides** per field (extend the existing key hints), written
  so "a 5-year-old admin" can follow. SVG icons, dark-mode, loading states.

### Data
`admin_setup_progress` (per-admin: step, done_at) or a single cached Setting blob;
a `setup_completed` flag to stop auto-opening once finished (still relaunchable).

---

## B. Staff & User Management (careful, money-safe)

**Vision:** staff can help users with common issues **without ever touching money
or deleting accounts.** Bugs go to Claude's maintenance loop, not staff.

### What staff CAN do (scoped Spatie permissions)
- **Find a user** (search by email/ID) and view a **non-financial** profile:
  status, verification, orders (no card/wallet internals).
- **Reset / change a password** → generate a reset or set a new one and **deliver it
  through the ticket system** (never shown in plain admin log). Never via email
  scraping — always the in-app ticket thread the user opened.
- **Help with account issues** (reactivate a self-paused account on request,
  resend verification, fix a stuck non-money status) — a researched allow-list.
- **NOT delete a user** — deletion stays **super-admin approval only** (existing
  S26 rule) and only on the user's own request.

### The one money-adjacent exception — pending refunds
Refunds that show **pending and didn't reflect** can be moved to **refunded** by
staff, but **only with proof**:
- The user must **upload evidence** and the transaction is found by **transaction
  ID / a verification "proof stamp" code**. Staff **search that code** in the
  user's dashboard/ledger to confirm the transaction actually happened (and is
  genuinely stuck), before flipping only that `wallet_transactions.status`
  pending → refunded. Every change is **audited** (`Auditor`) with the evidence ref.
- Staff have **no access** to stored cards, atomic wallet balances, or any other
  money surface. This single, evidence-gated status change is the *only* money
  action, and it never moves funds — it corrects a stuck record after a real,
  proven refund.

### Permissions (Spatie)
New granular abilities: `users.view`, `users.reset_password`, `users.assist`,
`refunds.resolve_pending` — assigned per staff role by the admin. Staff never get
`users.delete`, `wallet.*`, or `payments.*`.

### Claude's role (bugs, not people)
Claude's **maintenance loop** already reads error logs and proposes a CI-gated fix
for real bugs — so genuine software errors are caught **before** a human ticket.
Staff handle the human/account side; Claude handles the code side. Keep them
separate.

### ⚠️ Build-safety
- The refund status change must **never** trigger a real re-payment — it only
  corrects a record after an out-of-band refund is proven. Guard it hard + audit.
- Password delivery goes **only** through the ticket the user opened (proves
  ownership); never a blind email to an address staff typed.

---

## C. Partners layer

**Vision:** the admin registers **partners** who share in platform profit and get a
dashboard showing **only their earnings** — never the user base.

### Model
- Admin adds a partner (name, email, password, **profit share %**, **next-month
  refill/API-subscription deduction %**). Both percentages are **admin-set and
  hidden** from the partner — the partner sees **money earned**, not the formula.
- Partner logs in at **`/partner`** (separate entry) with the admin-issued
  credentials. They get the **normal user interface** *plus* an **Earnings** area.
- **Earnings** = a configured share of platform **profit** (Retail − Cost across
  sales), computed per period; the deduction % (for next-month provider refill +
  the platform's own API subscription) is netted off **before** the partner's share,
  so the platform is never out of pocket.
- Partners **cannot** see the user list, user data, or platform internals — only
  aggregate earnings + their own payout history.
- Partners add **bank/payout accounts** (shared payout engine) and **withdraw
  earnings** like merchants — via the same `payout_accounts` + payout engine.

### Data
`partners` (user_id, share_pct, deduction_pct, status), `partner_earnings`
(partner_id, period, gross_profit_basis, share_amount, deducted_amount, payable),
`partner_settlements`. Earnings computed by a scheduled job from the profit ledger.

### Money-safety
The partner's payout comes **out of the admin's profit share by design** (admin
sets the %), after the platform's refill/subscription deduction — so admin decides
exactly what they give away and is never underwater. Everything routes through the
same audited, idempotent payout engine.

### ⚠️ Build-safety
- Strictly **scope the /partner surface** — no query that can reach user PII.
- Compute earnings from a **committed profit ledger snapshot** per period; never
  re-derive live (avoid double-count). Lock the period before settlement.

---

## Shared payout accounts (users · merchants · partners)

All three payee types (plus normal users cashing out NaaraCredits) use **one**
`payout_accounts` + `PayoutGatewayInterface` engine (see
`ROADMAP-PAYOUTS-MERCHANTS-API.md` and `ROADMAP-PAYMENT-GATEWAYS.md`), so bank +
mobile-money + crypto + local-wallet destinations, account-name resolution,
autopilot settlement, and audit are built once and reused everywhere.

## Build order (suggested)

1. **Admin Setup Wizard** — pure UI over existing admin pages; low risk, high value.
2. **Staff & User Management** — scoped permissions + evidence-gated pending-refund
   fix + password-via-ticket.
3. **Partners** — after the payout engine (Layer 0) exists, since partners settle
   through it.

## Cross-cutting

- Each feature behind an **admin toggle**, off by default.
- All money moves keep `WalletService` discipline; all changes are **audited**.
- SVG icons only, dark-mode, loading states, rich tooltips — same platform UI rules.
