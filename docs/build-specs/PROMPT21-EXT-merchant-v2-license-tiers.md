# NAARA PROMPT 21-EXT — Merchant V2 License Tiers, Balance-to-Upgrade, Platform Earnings Wallet, Plan Carousel & Resell Gating

**For: Claude Code, working against the master `NaaraSim-main` repo only, then tested against both white-label forks (they consume the license/entitlement state this prompt changes, they don't run this code).**
**Supersedes Prompt 21 §4.1 only** (see §0.4 below) — every other confirmed fact and constraint in `NAARA-PROMPT-21-MERCHANT-V2-SELF-SERVICE-LICENSE.md` (kept alongside this file) still applies and is not repeated in full here. Read that file first; this one is the owner's follow-up extension of it, not a replacement.

---

## §0 — Confirmed before writing this, read before touching anything

0.1. `WhiteLabelInstance::TIERS = ['normal', 'extended']` (ordered, richest-inclusive) and `LEVELS = ['basic', 'standard', 'full']` already exist. `defaultLevelForTier()` maps `normal → basic`, `extended → full`. `standard` is an existing, ADMIN-ONLY manual level ("Normal fork post-payment, richest features locked only") set via `WhiteLabelLicenseService::setEntitlementLevel()` — this prompt does not touch, reuse, or reinterpret `standard`. The balance-to-upgrade mechanic below goes straight `basic → full` via a `normal → extended` tier change, never through `standard`.

0.2. `WhiteLabelLicenseService::issueLicense()` is **destructive on re-issue**: it deletes every live Sanctum token and generates a brand-new `license_key` every time it's called, even on an already-active instance. That's correct for a genuinely fresh license (Prompt 21's own self-service purchase flow uses it exactly this way, once, at first activation) but is the WRONG mechanism for the balance-completion auto-upgrade in this prompt — a merchant's fork is already deployed and holding a live token; forcing a full re-issue would break their running deployment (new key to re-enter, old token dead) at the exact moment they finished paying. §3 below adds a new, non-destructive method for this reason, modeled on `setEntitlementLevel()`'s existing shape (which already proves a tier/entitlement change without touching tokens is a normal, supported operation in this codebase).

0.3. `Merchant::TIER_V2` / `Merchant::isV2()` / `status === ACTIVE` is the existing V2 gate (Prompt 21 §1) — reused as-is, no changes.

0.4. **Prompt 21 §4.1 said "there is no fixed price list... don't build one."** The owner's follow-up message directly supersedes that one line with a real, seeded four-tier catalog (§1 below). Everything else in Prompt 21 §4 (admin reviews before the merchant can pay, `price_usd` on the instance, payment only after review) still holds — `price_usd` is now **pre-filled from the selected plan** at request time rather than freely typed from nothing, but an admin may still override it per-request for a genuine exceptional deal (Prompt 21's own flexibility is kept, just given a sane starting point instead of a blank field).

0.5. `WalletService::charge(User $user, float $amount, string $currency, Closure $deliver, array $meta)` is reused exactly, twice: once for the initial plan purchase, once (independently) for the balance-completion payment. Same money-safety pattern both times — no new pattern invented.

0.6. `MerchantEarningsService`/`MerchantWithdrawalService` (reseller cash-out) is the closest existing analog for the new platform earnings wallet in §5 — same ledger-with-`balance_after`, same `Cache::lock` + `DB::transaction` atomic-apply shape, same `PayoutService::createRequest()` + `PayoutReversed` reversal-listener pattern (`source_bucket` is a plain string column, not an enum — a new bucket value needs no migration). §5 mirrors this shape with a single global bucket instead of one per merchant.

0.7. No support-ticketing/SLA system exists anywhere in this codebase today. Extended V2's "more extended support" is scoped in this prompt as a **recorded flag only** (`support_level` on the plan/instance, surfaced in the merchant dashboard and admin oversight screen) — building an actual priority-ticket/SLA engine is explicitly OUT of scope here; it would be its own future prompt once a ticketing system exists to plug it into.

0.8. Threshold counts for §6's resell-status auto-close are **derived directly from `WhiteLabelInstance` rows** (`tier` + `acquisition_method` + a paid/active status), never a parallel counter — same discipline Prompt 20 §0 already established for this codebase ("no new lists, no shadow tracking of state the data already answers").

---

## §1 — Seeded plan catalog (new model, replaces the "no fixed list" instruction)

**Build:**
1. New `WhiteLabelLicensePlan` model + migration: `key` (unique, e.g. `basic`/`medium`/`extended`/`extended_v2`), `name`, `tagline`, `description` (long text — the plan's own sales copy), `price_usd` (decimal), `tier` (`normal`/`extended`, matching `WhiteLabelInstance::TIERS` — never invent new tier names here either), `support_level` (`standard`/`priority`, per §0.7), `cover_image_url` (nullable — admin-uploaded plan illustration, Wasabi-stored per CLAUDE.md's "never local disk" rule), `features` (JSON — a comparison list the carousel/comparison table renders), `sort_order`, `is_active` (admin can retire a plan without deleting its historical rows/instances that reference it).
2. Seed exactly four rows at the stated prices:
   - **Basic** — $1,500 — `tier = normal`, `support_level = standard`.
   - **Medium** — $2,500 — `tier = normal`, `support_level = standard`.
   - **Extended** — $5,000 — `tier = extended`, `support_level = standard`.
   - **Extended V2** — $7,500 — `tier = extended`, `support_level = priority`. Identical feature/entitlement unlock to Extended (both resolve to `entitlement_level = full`) — the ONLY difference is `support_level`, per the owner's own framing ("all same plan only that the extended V2 is pro with more Extended support").
3. `WhiteLabelInstance` gains `license_plan_id` (nullable FK to the plan chosen at request time) alongside the fields Prompt 21 §2 already adds — this is what lets the balance math in §3 look up "what's the target price for this instance's tier" without re-deriving it from a loose `requested_tier` string.
4. Admin-facing plan-management screen (extends `Admin\WhiteLabelRegistry` with a new tab, doesn't fork it into a second screen): create/edit a plan's name, tagline, description, price, cover image, feature list, sort order, active/inactive — this is what satisfies "admin can add plan cover design... every necessary thing wired to the flow."

## §2 — Merchant-facing plan carousel (replaces a plain form with a swipeable chooser)

**Build:**
1. Where Prompt 21 §3.1 described "a form: requested tier, hosting preference," the tier-selection step is now a **swipeable carousel** of the active `WhiteLabelLicensePlan` rows (ordered by `sort_order`), each card showing: cover image, name, tagline, price, feature list, and — for Basic/Medium specifically — "Pay $X now, complete the remaining $Y whenever you're ready to unlock Extended" (the balance amount is `extended_plan.price_usd - this_plan.price_usd`, computed live from §1's seeded Extended price, never hardcoded twice).
2. A plan whose tier is currently resell-closed (§6) still appears in the carousel but visibly locked/disabled with the reason ("Extended licenses are temporarily closed — check back later") — same visible-but-locked pattern Prompt 21 §1 already established for the Merchant-V2 gate itself, applied here to the plan level too.
3. Hosting preference + disclaimer acknowledgment (Prompt 21 §3.1) stay exactly as originally specified, as the step after picking a plan card — this prompt doesn't change that part of the flow.
4. Selecting a card and submitting calls `WhiteLabelLicenseService::register()` exactly as Prompt 21 §3.2 specifies, with `license_plan_id` set alongside `requested_tier` (derived from the plan's own `tier` column, not typed separately — one source of truth) and the other fields Prompt 21 already lists.

## §3 — Admin review, pricing, and payment (extends Prompt 21 §4, not a second flow)

**Build:**
1. Admin review is unchanged in spirit (Prompt 21 §4.1) except `price_usd` now defaults to the requested plan's `price_usd` the moment the admin opens the request — still overridable per-request (§0.4).
2. Payment reuses `WalletService::charge()` exactly as Prompt 21 §4.3 specifies. Inside the closure, for a request whose plan resolves to `tier = extended` paid in full (Extended or Extended V2), call the **existing** `issueLicense($instance, $instance->tier, $reviewerId)` exactly as today — full price paid up front gets full verification immediately, no new logic here.
3. **New: the balance-completion payment.** A `normal`-tier instance (Basic/Medium purchased, license already issued and active) can submit a SECOND, independent `WalletService::charge()` for the remaining balance (`extended_plan.price_usd - amount_already_paid`, tracked via §4's payment ledger, never a re-typed guess). On success, the closure calls a **new** `WhiteLabelLicenseService::upgradeTier(WhiteLabelInstance $instance, string $newTier): WhiteLabelInstance` method — modeled on `setEntitlementLevel()`'s shape (§0.2): sets `tier` and `entitlement_level = defaultLevelForTier($newTier)` via `forceFill()->save()`, audits the change, and returns the instance — **never touches `license_key`, `tokens()`, or `status`**. The merchant's already-deployed fork keeps its existing token working and simply unlocks the extended package/feature set on its next check-in, exactly like a `setEntitlementLevel()` bump already does today for the existing admin-only path.
4. If `upgradeTier()` throws for any reason, `WalletService::charge()`'s existing refund-on-failure handles it — no new refund logic, per Prompt 21 §4.3's own instruction, now applied to this second payment too.
5. Extended V2 is reachable ONLY as a direct full-price purchase in this prompt's scope — an Extended→Extended V2 balance-top-up-for-priority-support upgrade path is explicitly NOT built here (§0.7's ticketing-scope note applies: there's no support-tier machinery yet to make that upgrade meaningfully different in the product beyond the flag itself). Revisit if/when a real support-ops system exists.

## §4 — Payment ledger (new, not a duplicate of anything existing)

**Build:** a new `WhiteLabelLicensePayment` model (one row per successful charge against an instance — the initial plan purchase AND, later, the balance-completion payment are both separate rows on the SAME instance): `white_label_instance_id`, `amount_usd`, `kind` (`initial`/`balance_completion`), `payment_reference` (the `WalletTransaction` reference `WalletService::charge()` produced — Prompt 21 §2.4 already calls for a `payment_reference` field on the instance itself for the FIRST payment; this ledger generalizes that to support more than one payment per instance without overloading a single column). `amount_paid_total` for an instance is `SUM(amount_usd)` over its own payment rows — this is the number §3.3's balance math and §6's threshold counts both read, a single source of truth, never re-derived two different ways in two different places.

## §5 — Platform earnings wallet (new, deliberately separate from platform-profit reporting)

**Build:**
1. New `PlatformEarning` model + migration, shaped exactly like `MerchantEarning` (§0.6) but with **no owning `merchant_id`** — a single global running ledger (`type`, `amount`, `balance_after`, `currency = 'USD'`, `reference`, `source_type`, `description`). New `PlatformEarningsService` with `accrue()`/`hold()`/`release()`/`balance()`, same atomic `Cache::lock` + `DB::transaction` + idempotent-on-reference shape as `MerchantEarningsService::apply()` — copied discipline, not reinvented.
2. Every successful white-label license payment (§3.2's full-price purchase AND §3.3's balance-completion payment) calls `PlatformEarningsService::accrue()` for the **full amount charged** (this is a flat platform-product sale, not a resale with an underlying wholesale cost to net out — unlike `MerchantEarningsService::accrue()`, which nets `charged − retail`, this nets nothing: the whole price is platform revenue). This ledger is never read by, merged into, or reconciled against any existing general platform-profit/revenue dashboard — the owner was explicit that white-label sale proceeds are a separate pool, kept separate on purpose.
3. New `PlatformWithdrawalService`, shaped exactly like `MerchantWithdrawalService` (§0.6): any `User` holding the `super_admin` role can request a cash-out of `PlatformEarningsService::balance()` to **their own** verified `PayoutAccount`, through the same `PayoutService::createRequest($actingAdmin, $localAmount, $currency, 'platform_earnings', $account, $reference)` call merchants already use, with the same free-payout-threshold/KYC gate (`PayoutThreshold`) applied identically — "admin can withdraw this the way normal users withdraw funds" is satisfied by literally reusing the same payout engine end to end, not building a parallel one.
4. New `ReturnPlatformEarnings` listener on `PayoutReversed`, mirroring `ReturnMerchantEarnings` exactly (§0.6) but releasing back into the global `PlatformEarning` bucket instead of a specific merchant's.
5. Admin-facing balance + withdraw UI: a new small screen (or a new tab on an existing admin financial screen, whichever this codebase's own convention favors — check before building a fresh route) showing the running balance and a withdraw form identical in shape to the merchant withdrawal UI already in this codebase.

## §6 — Resell-status gating with sales-threshold auto-close

**Build:**
1. Two admin-controlled booleans via the existing `Setting` model (`Setting::getValue()`/`setValue()`, same pattern used throughout this codebase — no new settings mechanism): `whitelabel.resell.normal_open` and `whitelabel.resell.extended_open`, both default `true`.
2. The merchant-facing carousel (§2.2) and the request-submission endpoint both check the relevant flag for the plan's `tier` — a closed tier is visible-but-locked in the carousel and hard-rejected server-side if somehow still submitted (defense in depth, never trust the UI alone — same principle this codebase already applies everywhere else).
3. Auto-close check runs at the moment each `merchant_self_service`-acquired instance reaches a real "counts as sold" state (its license is issued — i.e., right after §3.2's or §3.3's `issueLicense()`/`upgradeTier()` call succeeds, not at request time, since a pending/rejected/unpaid request was never really a sale): count `WhiteLabelInstance::where('acquisition_method', 'merchant_self_service')->where('tier', 'normal')->whereNotNull('license_issued_at')->count()` — once it reaches 200, set `normal_open = false` (Extended-only from then on). Count the same shape for `tier = extended` — once it reaches 2000, set BOTH flags `false` (fully closed). These are plain counts against existing rows (§0.8) — no new counter column, no scheduled job, checked inline right after the sale that might cross the threshold.
4. An admin can manually flip either flag back to `true` at any time regardless of how it got closed (threshold or a manual close) — the two toggles are the single source of truth for "is this open," with no separate "why it's closed" state to keep in sync, per the "don't over-engineer" principle — simplicity here over a richer audit trail nobody asked for.
5. Admin UI: the two toggles live on the same plan-management tab §1.4 already adds (not a third new screen) — showing current counts against each threshold so an admin can see how close a tier is to auto-closing before it happens.

---

## Acceptance checklist for this prompt

- [ ] Four `WhiteLabelLicensePlan` rows seeded at the exact stated prices ($1,500 / $2,500 / $5,000 / $7,500) — no other tier prices invented
- [ ] Extended and Extended V2 resolve to the IDENTICAL `entitlement_level = full` — only `support_level` differs between them
- [ ] Basic/Medium purchases issue a `normal`-tier license immediately via the existing `issueLicense()`, unchanged from Prompt 21
- [ ] A balance-completion payment upgrades `tier`/`entitlement_level` via a NEW, non-destructive `upgradeTier()` — confirmed by a test that the instance's existing `license_key` and live Sanctum token are UNCHANGED after the upgrade
- [ ] `amount_paid_total` is read from the new `WhiteLabelLicensePayment` ledger in exactly one place in the code, reused by both the balance math and the threshold counts — never computed two different ways
- [ ] `PlatformEarningsService`/`PlatformWithdrawalService` reuse `PayoutService`/`PayoutThreshold`/`PayoutReversed` exactly as `MerchantEarningsService`/`MerchantWithdrawalService` already do — no parallel payout mechanism
- [ ] The platform earnings ledger is never read by or merged into any existing general platform-profit reporting — confirmed by grep, not assumed
- [ ] Resell-status auto-close thresholds (200 normal, 2000 extended) are computed from `WhiteLabelInstance` counts, not a new counter column
- [ ] A closed tier is visible-but-locked in the carousel AND hard-rejected server-side if submitted anyway
- [ ] An admin can manually reopen a threshold-auto-closed tier at any time
- [ ] Every acceptance item from the original `NAARA-PROMPT-21-MERCHANT-V2-SELF-SERVICE-LICENSE.md` still holds unmodified, EXCEPT §4.1's "no fixed price list," which this document explicitly supersedes
- [ ] Full suite green on master; then boot-tested against both `NaaraSim-WhiteLabel` and `NaaraSim-White-Label-Extented` forks to confirm the tier/entitlement changes this prompt makes are actually consumed correctly by each fork's existing `FeatureLocks`/entitlement-check code (already built in Batch 8, not re-tested from scratch — confirmed still correct against these NEW code paths specifically)
