# NAARA-PAYOUT-GLOBAL — ADDENDUM B: Payout Rail Guide (user-facing, inside Wallet / Withdraw)
Extends `NaaraSim-Global-Payout-Layer-Blueprint.md` (needs its Phase 1 corridors) and feeds `...Funding-Radar-Addendum.md` (rail enrollments). One phase at a time, sandbox only, local PHPUnit, push with `[skip ci]`. Master-first per CLAUDE.md white-label rule.

## 0. What the owner asked for
A guide that lives in the wallet payout system so a user reads it and decides which payout rail to use, with:
1. A clear warning of where THEIR country falls: Paystack, Flutterwave, Stripe Connect (and Global Payout), with the FULL country list visible.
2. Advice to choose the local rail they already use to buy/fund their wallet (fast payout), unless their country isn't supported on that rail.
3. If their country isn't supported by the fast rails, they use Global Payout and are warned it can take up to 14 days to land in their Payoneer / Grey / Raenest account.

## 1. Ground truth from the codebase (verified 2026-09-29 snapshot — do not assume otherwise)
- `App\Livewire\Withdraw` + `resources/views/livewire/withdraw.blade.php` already handle: country select → bank list → account add, PayPal email, Stripe Connect onboarding, KYC/free-payout banner, and a platform-wide "Recommended — fast payout" badge (`PayoutService::recommendedGateway()`, highest inbound volume). Reuse those pieces; do NOT rebuild the forms.
- Country coverage today lives as hard-coded constants:
  - `PaystackBankResolver::COUNTRIES` = NG, GH, ZA, KE, CI, EG.
  - `FlutterwaveBankResolver::COUNTRIES` = NG, GH, KE, UG, TZ, ZA, RW, ZM, CI, SN, CM, EG.
  - `Withdraw::currencyFor()` only maps NG/GH/KE/ZA to local currency, everything else = USD.
  - Stripe Connect: no country list in code (F9 in main blueprint: account created without `country`).
- `PaymentCharge` (`payment_charges`) records `gateway` but its fillable fields show no `user_id`. Per-user rail history therefore needs a reliable user link. Claude Code must FIRST check whether `payment_charges.reference` joins to `wallet_transactions.reference` (which has `user_id`). If the join is reliable, use it. If not, add a nullable `user_id` to `payment_charges`, populate it at write time in every gateway's success path, and backfill via the reference join.
- Languages present: `lang/ar, en, fr, sw`. Every guide string goes through lang files in all four (Arabic needs RTL check).

## 2. Single source of truth for country support (MOST IMPORTANT DESIGN RULE)
The guide's list and the money path must never disagree. Create one registry and make the resolvers read from it.

Migration `payout_country_rails`:
```
id, country char(2), rail string (paystack|flutterwave|stripe_connect|paypal|cryptomus|global),
provider_supports bool,            -- does the provider itself cover this country
we_enabled bool default false,     -- is it actually switched on for NaaraSim users (mirrors an enabled payout_corridors row)
methods json null,                 -- ["bank","mobile_money","wallet"]
currency char(3) null,
eta_min_hours smallint null, eta_max_hours smallint null,   -- overrides the rail default
note_key string null,              -- lang key for a country-specific caution
source_url string null, verified_at timestamp null, verified_by string null,   -- "api" or admin id
admin_override enum('none','force_on','force_off') default 'none',
timestamps; unique(country, rail)
```
- `PayoutRailRegistry` (service): `railsFor(country)`, `matrix()` (all countries × rails), `isEnabled(country, rail)`; cached (Redis, tagged, flush on admin edit or audit run).
- Refactor `PaystackBankResolver::supports()` and `FlutterwaveBankResolver::supports()` to ask the registry (fall back to the existing constants if the table is empty so nothing breaks before seeding). No behaviour change for NG etc. — acceptance test proves the same countries are supported before/after.
- `we_enabled` is derived from `payout_corridors.enabled` (main blueprint Phase 1) so the guide can only ever promise what the router can actually do. "Provider supports it but we haven't enabled it" renders as **Coming soon**, never as ✓.
- Seeding (`payouts:guide-seed`):
  - Paystack / Flutterwave: from the existing constants, then verify against each provider's API at seed time (Paystack `GET /country`; Flutterwave banks-by-country endpoint) and record `verified_at`. Log any disagreement, do not silently override.
  - Stripe Connect: DO NOT hard-code a list. Read Stripe's `GET /v1/country_specs` (countries Stripe supports for accounts) and intersect with what our platform can actually onboard per the current Connect docs; mark `provider_supports` accordingly and leave `we_enabled=false` until an admin enables the corridor. Stripe's docs conflict across versions on cross-border rules (see main blueprint 3b) — the corridor table decides real availability.
  - Global: `provider_supports` = country is in the Payoneer/Grey/Stripe-Global coverage the owner has confirmed; `we_enabled` follows corridors. Sanctioned/unsupported countries = deny list (Phase 5 of main blueprint) → the guide shows "Payouts to your country aren't available".
- `payouts:guide-audit` (scheduled monthly, plus admin button): re-queries provider APIs, diffs against the registry, alerts admin (`AlertAdminJob`, code `guide_registry_drift`) and marks rows older than 45 days as "needs re-verification".

## 3. Recommendation logic (`RailAdvisor`)
Pure service, unit-tested. Input: user, payout country (defaults to `users.country_code`, changeable in the guide), optional currency. Output: ordered list of options each with `rail`, `state`, `badges`, `eta`, `fee_text`, `warnings[]`, `cta`.

States per rail: `recommended | available | coming_soon | not_supported | blocked | global_fallback`.

Algorithm:
1. `fast_rails` = enabled rails for the country among paystack, flutterwave, stripe_connect (optionally paypal, cryptomus if admin-enabled and offered for the country).
2. If `fast_rails` is non-empty:
   - Score each by the user's own funding history over the last 180 days: share of their paid `payment_charges` (top-ups/purchases) by mapped gateway (paystack→paystack, flutterwave→flutterwave, stripe→stripe_connect, paypal→paypal, crypto gateways→cryptomus). Tiebreak 1: platform ranking (`PayoutService::rankedGateways()`); tiebreak 2: static order paystack, flutterwave, stripe_connect.
   - Top = `recommended`, others = `available`. Badge "You already use this to pay us" on any rail with the user's history.
   - Global payout is NOT offered as an equal option. It appears only under "Other options" with a warning, and is selectable only if admin setting `payouts.global_rail.allow_when_local_available` = true (default false). Otherwise show it disabled with the reason.
3. If `fast_rails` is empty and global is enabled for the country → single big card `global_fallback` with the 14-day warning (§5.3), pre-selected.
4. If nothing is enabled → `blocked` message + "notify me when available" (writes a demand row, see §7).
5. Stripe Connect specifics: mark `available/recommended` only if Stripe is enabled for the country; show "Stripe will ask you to verify your identity; you must be a resident of a supported country and use your own bank account". If the user's Stripe account creation was declined earlier → drop Stripe and re-run scoring.
6. Never recommend a rail whose provider is unhealthy (`ProviderStatus` / circuit flag) — show "temporarily unavailable" and pick the next.
Deterministic and explainable: return a `reasons[]` array (e.g. `["supported_in_country","you_paid_with_it_3_times","highest_platform_volume"]`) used for the on-screen "Why this?" line and for tests.

## 4. Honest-copy rules (the guide must not overclaim)
- Do NOT say paying with a rail is technically required to be paid out through it. It isn't. The truthful reason is: we keep payout balances on the rails our users pay through, so those payouts are the fastest for us to send. Copy: "Fastest for you: the rail you already use with us."
- Do NOT promise delivery times we can't control. ETA text is admin-editable per rail/country; defaults below are the owner's figures and must be re-checked against real settled payouts (use `payout_requests` created→settled durations to display "Typical: X" once ≥ 20 samples).
  - Local bank rails (Paystack/Flutterwave): "Usually minutes to 1 business day" (default).
  - Stripe Connect: "Usually 2–7 business days after onboarding" (default, admin-editable).
  - Global Payout: **"Up to 14 days"** (owner's stated figure; setting `payouts.eta.global_days` = 14).
- Do NOT tell users to enter Payoneer/Grey/Raenest virtual accounts into Stripe Connect as if they lived in the US/EU. State instead: "Stripe requires you to be resident in the country of your bank account."
- Raenest is a RECEIVING account for the user (USD/GBP/EUR details), not an integration we call. Copy for Global rail: "We pay into your Payoneer account, or into the bank/virtual account details you provide (for example a Grey or Raenest USD/GBP/EUR account) where enabled for your country." Which destination types show is driven by `payout_corridors.destination_types` (Payoneer account / bank details). Never show a destination we don't support.
- Fees: show the platform fee and locked FX quote from the main blueprint; never show provider cost (money rule 2).

## 5. UI spec
Placement (all reuse existing layout components, mobile-first, dark-mode aware, `x-icon`):
- **Wallet page** (`wallet.blade.php`): "How do I get paid?" card linking to the guide, shown when the user has no payout account or a withdrawable balance.
- **Withdraw page**: the guide becomes **Step 1 — Choose how to get paid** above the existing account forms. Country select moves into the guide; steps 2/3 are the existing rail-specific forms, shown after a rail is chosen.
- **Standalone route** `account/payout-guide` (same content, shareable, linked from help/FAQ; respects the white-label FAQ rule — it is not the `/faq` page).

### 5.1 Header: "Your country" + verdict
Country picker (defaults to profile country, searchable). Under it, a verdict banner from `RailAdvisor`:
- Fast rail available: green — "Your country is supported on Paystack ✓ Flutterwave ✓ Stripe ✗. Recommended: **Flutterwave** — you've paid with it 3 times, so payouts from us are fastest there."
- Only Stripe: green/amber — recommended Stripe with the identity-onboarding note.
- Global only: amber — "Your country isn't supported by Paystack, Flutterwave or Stripe Connect. Use **Global Payout**. It can take **up to 14 days** to arrive in your Payoneer, Grey or Raenest account."
- Blocked: red — "Payouts to {country} aren't available right now." + Notify me.

### 5.2 Rail cards (ordered by `RailAdvisor`)
Each card: rail name/logo, badge (Recommended / You use this / Coming soon), what you'll need (bank account or mobile money / Stripe identity check / Payoneer account), currency, fee, ETA, "Why this?" expandable, button **Use this rail** (starts the existing form/onboarding). Unsupported rails are shown greyed with "Not available in {country}" (transparency, not hiding).

### 5.3 Global Payout card and mandatory acknowledgement
Amber card: "Slower option — up to {days} days." Checklist the user must tick before continuing: (a) I understand payouts can take up to {days} days; (b) the account I enter is in my own verified name; (c) I have a Payoneer account or supported bank/virtual account. On confirm: write `payout_rail_acknowledgements` (user_id, rail, country, guide_version, ip_hash, user_agent_hash, created_at) and call `RailEnrollmentService::select()` from Addendum A so the user appears in the admin Funding Radar.

### 5.4 Full country list ("where do I fall?")
Section "All countries" below the cards:
- Full ISO country list (localised names via `Symfony\Component\Intl\Countries`), with flag, and 4 status columns: **Paystack · Flutterwave · Stripe Connect · Global Payout**. Cell = ✓ Available / ◔ Coming soon / — Not supported / ⛔ Blocked. User's country pinned to top and highlighted.
- Search box, filter chips (Available on Paystack / Flutterwave / Stripe / Global only / Not available), sort A–Z or by region.
- Mobile: each country is a compact card/accordion with four status chips; desktop: table. Virtualise/paginate (≈ 200 rows) — lazy-render, no giant DOM.
- Legend + "last verified {date}" footer read from the registry (`max(verified_at)`), so stale data is visible.
- Data comes from `PayoutRailRegistry::matrix()` (cached); never a hard-coded Blade list.

### 5.5 Small rules
- If the user changes country in the guide, recompute everything and warn: the payout account country must match the bank account's country.
- If the user already has a payout account, show it as "Your current rail" and warn when the guide's recommendation differs (e.g. they are on Global but a fast rail is now available) with a "Switch" action.
- Accessible (labels, keyboard, contrast), RTL-safe, all copy via lang keys with the four languages provided.

## 6. Admin controls (extend `Admin\Payouts` or the corridor manager; admin roles only)
- Country × rail matrix editor: toggle `force_on/force_off`, edit ETA overrides and notes per country, see `verified_at` and drift flags. Every edit → `Auditor::log`, cache flush.
- Settings: `payouts.global_rail.allow_when_local_available` (default false), `payouts.eta.global_days` (default 14), `payouts.eta.*` text per rail, `payouts.guide.version` (bump to re-require acknowledgement).
- Buttons: "Run audit now", "Re-seed from providers" (never overwrites `admin_override`).
- Preview: "View guide as country X / as a user with history Y" (admin-only simulation, no data written).

## 7. Tracking (feeds analytics; no PII beyond ids)
Events (table `payout_guide_events`: user_id, event, country, rail, meta json, created_at): `guide_viewed`, `country_changed`, `rail_recommended`, `rail_selected`, `global_ack_confirmed`, `blocked_notify_requested`. Admin report: selections by country/rail, % who follow the recommendation, users in unserved countries (demand for the next corridor), conversion guide → verified payout account. `blocked_notify_requested` rows feed the "Unserved demand" tab in Addendum A.

## 8. Build phases (acceptance checks)
- **G1 Registry**: migration, `PayoutRailRegistry`, seeder + provider verification, resolver refactor to read the registry with fallback. *Accept:* existing payout tests green; supported-country sets identical before/after; registry rows carry `verified_at`; Stripe list sourced via `country_specs`, not hard-coded.
- **G2 Rail history + Advisor**: confirm/add `payment_charges` user link (+ backfill command, idempotent), `RailAdvisor` with reasons. *Accept:* unit tests for: user with history picks that rail; no history picks platform-ranked; country only on Flutterwave never recommends Paystack; Stripe only when enabled; unhealthy provider skipped; global only as fallback (and as disabled "other option" when local exists); blocked country; declined Stripe removes Stripe; deterministic ordering.
- **G3 UI**: guide component, Step 1 integration in `Withdraw`, Wallet card, standalone route, country list (search/filter/mobile), four-language strings. *Accept:* Livewire tests for each verdict banner; country list renders all rows from the registry; global acknowledgement blocks progress until all boxes ticked; existing add-account/PayPal/Stripe flows still work unchanged.
- **G4 Enforcement + enrollment**: acknowledgements table, hook into `RailEnrollmentService::select()`, server-side rule that a global-rail account cannot be added when a fast rail is enabled unless the admin setting allows (UI-only checks are not enough). *Accept:* bypass attempt via direct Livewire call is rejected; acknowledgement row written; enrollment appears in Funding Radar.
- **G5 Admin + audit + tracking**: matrix editor, settings, `payouts:guide-audit` schedule + heartbeat, events + report. *Accept:* drift alert fires; override survives re-seed; audit log entries exist; report shows a seeded funnel.

## 9. Tests to include beyond the above
Snapshot test that no provider cost figure is rendered anywhere in the guide; test that "Coming soon" never renders ✓; test that ETA text comes from settings; test cache invalidation on admin edit; RTL render smoke test for `ar`; performance test that the full matrix renders under a set query count.

## 10. Defaults if the owner does not decide
History window 180 days · ETA defaults as in §4 (re-check after 20 settled samples per rail) · global-when-local-available = off · acknowledgement required for global rail = on · registry re-verification every 45 days.
