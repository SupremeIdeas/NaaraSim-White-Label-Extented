# NAARA BUILD 6 of 6: WIZARD DEEP FIX, PAGE BUILDER PUBLISH BUG, SOCIAL-FOLLOW-TO-EARN, BRAND HUNT, LOTTIE WIRING
**Run after NAARA-BUILD-1 through -5 are all verified working.** Give this whole file to
Claude Code as one message. This build is the result of a direct source-code audit (not a
fresh guess) — every finding below was confirmed by reading the actual files named.

---

## 0. HOW TO WORK
- Inspect the actual file/component before assuming its contents or editing it.
- Do not create duplicate components, tables, or systems. Reuse `CreditService`,
  `ProviderModels`, `SectionLibrary`, and the existing Lottie-wiring pattern already used on
  the Rewards page — don't invent parallel systems.
- After each numbered section, verify manually before moving to the next.
- Commit after each numbered section — clean rollback points.
- This file is self-contained — do not open or reference any other file for context.

---

## PART A — WIZARD ("Naara Helper") DEEP AUDIT

### A.1 Does the Wizard actually solve the bottleneck? — Verdict: Partially, with one confirmed crash

The Wizard is a genuinely well-built, buttons-only state machine (`app/Livewire/Wizard.php`,
`app/Support/ProviderModels.php`) sitting on top of the real purchase engines
(`SmsNumberRouter`, `PermanentNumberRouter`, `WalletService`) — this part is solid and money-
safe: every quote is re-derived server-side, providers are never exposed to the client, and
every failure path refunds correctly. It genuinely removes the multi-step "which section do I
even go to" bottleneck for four of five product lines. It does **not** fully solve it, for one
confirmed, reproducible reason:

### A.2 CONFIRMED BUG: "Naara Connect" crashes the Wizard

`ProviderModels::MODELS` defines **five** Models: `naara_data`, `naara_connect`,
`naara_verify`, `naara_rent`, `naara_line`. `naara_connect` ("Full eSIM — calls + data on one
eSIM," lane `zendit/oneglobal/montymobile/gigs`) is a real, fully-wired, purchasable product —
confirmed in `app/Services/eSIM/ProviderRouter.php`, which has a dedicated `$voiceChain` and
correctly routes any `EsimPlan` with `has_voice = true` through it, completely independent of
the data-only lane. It is a genuine differentiator worth selling (see A.6 — this is exactly the
"data-only eSIM has no calls or SMS" complaint travelers make about Holafly).

But `Wizard.php` has **zero handling for `naara_connect` anywhere**:
- `purposes()`'s `$labels` map only defines `naara_verify`, `naara_rent`, `naara_line`,
  `naara_data` — `naara_connect` falls back to its raw `$m['name']`, which is cosmetically fine.
- `chooseCountry()` branches only on `naara_data` (→ device step) and `naara_line` (→ match
  step); everything else — including `naara_connect` — falls into the `service` step, which
  renders the OTP/rental service picker. That's the wrong step for an eSIM product.
- `interpret()` (the Claude free-text path) has the identical gap — same three-way branch,
  same fallthrough.
- `self::MODEL_TYPE` (the const used by `quote()`/`chooseService()`) only maps
  `naara_verify`/`naara_rent`/`naara_line`. Selecting `naara_connect` and picking a service
  calls `quote()`, which reads `self::MODEL_TYPE['naara_connect']` — an **undefined array key**
  — and passes a null type into `SmsNumberRouter::laneFor()`, whose `match` has no `null` case
  and throws `SmsException('Unknown number type [].')`. The user sees a bare, confusing error.
- `WizardIntent::system()`'s `$descriptions` map (the Claude system prompt) is also missing
  `naara_connect` — if it's ever selected via free text, Claude has no description to work from.

**Net effect today:** if an admin ever configures any of `zendit_api_key` /
`oneglobal_client_id` / `montymobile_api_key` / `gigs_api_key`, "Naara Connect" silently starts
appearing as a tappable purpose in the Wizard, and tapping it leads every user into a dead end.
This is a landmine, not a live bug yet, only because those four keys are presumably unconfigured
today — confirm that assumption and fix it before any of them are turned on.

**Fix:**
1. Give `naara_connect` its own branch everywhere `naara_data`/`naara_line` are special-cased:
   `chooseCountry()`, `interpret()`, and any other `$this->model ===` check in `Wizard.php`.
   Route it through the **same device-compatibility step as `naara_data`** (§A.3) — it's an
   eSIM product, it needs the same `*#06#` EID check — then to the **same "hand off to
   Checkout" pattern as `naara_data`** (§A.4), filtered to voice-capable plans.
2. Add `naara_connect` to `WizardIntent::system()`'s `$descriptions` map: `'a full eSIM with
   both calls and data, not just data'`.
3. Add a test asserting selecting `naara_connect` never throws and never reaches
   `SmsNumberRouter`/`self::MODEL_TYPE` at all (it's an eSIM path, not a number path).

### A.3 Does it cover all eSIM providers? — Yes, backend; no, front-of-wizard until A.2 is fixed

All seven eSIM provider services (`EsimGoService`, `AiraloService`, `QuibityService`,
`ZenditService`, `OneGlobalService`, `MontyMobileService`, `GigsService`) are real, bound in
`AppServiceProvider`, and correctly split across `ProviderRouter`'s two lanes (`$chain` for
data-only, `$voiceChain` for voice+data). The Wizard only reaches the data-only lane today
(§A.2 fixes the rest).

### A.4 Does it cover all Number section providers? — Yes, confirmed complete

All six number providers (`GetatextService`, `FiveSimService`, `HeroSmsService`,
`VirtSmsService`, `TwilioService`, `TelnyxService`) are bound and match exactly between
`ProviderModels::MODELS` lanes and `SmsNumberRouter::laneFor()`/`PermanentNumberRouter::$lane`
— no drift found here. `PermanentNumberRouter` (Naara Line) is genuinely fully wired end-to-end
(search, provision, monthly billing) — both `Wizard.php` and `GetNumber.php` call it correctly.
One piece of dead documentation, not a bug: `SmsNumberRouter::order()` still throws "Permanent
numbers are coming soon" for `TYPE_PERMANENT` — this code path is simply unreachable now (both
real callers use `PermanentNumberRouter` instead), but the stale comment above it should be
corrected so a future engineer doesn't think permanent numbers are still unbuilt.

### A.5 The Wizard's eSIM handoff is a genuine, if minor, "bottleneck not fully solved"

For `naara_data`, `checkDevice()` runs, then `goToEsims()` **redirects the user out of the
widget entirely** to `/catalogue`, filtered by country — the wizard never completes an eSIM
purchase itself; it's a guided hand-off, by design (the code comment says this reuses the
"dedicated, fully-tested Checkout" rather than duplicating that money path — a reasonable
call). The gap: this means "get eSIM data" is the one purpose where the Wizard doesn't actually
finish the job — it drops the user into a full catalogue page they still have to search through
even after choosing a country. Confirm with Frank whether this is acceptable (avoids duplicating
Checkout's coupon/credit logic) or whether the Wizard should show 2–3 matching plans directly
in-widget with a "Buy" button that calls the same Checkout service the dedicated page uses,
without a full page navigation. Recommend the latter for the "no bottleneck at all" experience,
but flag it as Frank's call since it's a real scope decision, not a bug.

### A.6 Claude sprinkle — current capability vs. "tougher user needs"

`WizardIntent` (`app/Services/Wizard/WizardIntent.php`) is a single-shot, single-turn text→JSON
classifier: one user message in, a whitelisted `{model, country, service}` guess out, tiny
200-token budget, cached, with a hard button fallback on any failure. This is genuinely safe
(it can never buy anything, never invents an option outside the whitelist) but it is **narrow**
— it cannot handle:
- **Multi-intent requests** ("I need a US number for WhatsApp verification and also a Nigeria
  eSIM for next month") — the parser returns one model/country/service, not a set.
- **Clarifying follow-up** — if the parse is ambiguous or partial, the only fallback is "I
  didn't quite catch that — tap an option below," a dead end back to buttons, not a follow-up
  question ("Did you mean a rental or a one-time code?").
- **Compatibility/troubleshooting questions** ("will this work for WhatsApp verification in
  Nigeria?") — there is no path for this at all; it either resolves to a Model/country/service
  guess or gets silently dropped.

**Recommendation, admin-toggleable, still logic-first:** extend `WizardIntent::parse()` to
return an optional `clarify` string in its whitelisted JSON shape (e.g. `{"model": "",
"clarify": "Did you need a one-time code, or a number you keep for a while?"}`) and have
`interpret()` display that as the `$notice` instead of the generic "didn't catch that" message
when Claude sets it — still zero freedom to invent options, still fully whitelisted, but a real
step up from a dead-end retry. Keep this **off the money path entirely**, same as today.

### A.7 Competitor-informed gaps — grounded in current (2026) reviews, not assumption

Real, current complaint patterns about Airalo, Holafly, TextNow, Burner, and similar apps (see
research below), checked against what NaaraSim actually does today:

1. **"Will this even work?" is the #1 trust question, and NaaraSim already half-answers it but
   doesn't show the answer.** The most common 1–3 star complaint across eSIM apps in 2026 is
   activation failing on an unsupported device with **no refund** — NaaraSim's `DeviceCompat`
   gate before eSIM purchase already prevents this class of complaint entirely, but this
   protection isn't marketed or highlighted anywhere in the Wizard or Checkout copy. Add a
   short, confident line at the device-check step ("We check this before you pay — no
   surprises") so the protection is visible, not just present.
2. **VoIP/line-type rejection is the #1 complaint for virtual numbers** — WhatsApp, banks,
   Tinder, and other high-trust platforms increasingly reject VoIP-flagged numbers (Google
   Voice, TextNow, generic burner apps), and users have no way to know in advance which numbers
   will work. `SmsNumberRouter::compareOperators()` **already computes a `success` rate per
   operator** (`'success' => $op['rate'] > 0 ? round($op['rate'], 1) : null`) — but nothing in
   `Wizard.php` or its Blade view ever reads or displays it. Surface this success-rate signal
   at the service/review step in the Wizard (and confirm it's shown on the dedicated Numbers
   page too) — e.g. "94% success rate for WhatsApp verification in this country" — this is a
   genuine, ready-to-ship differentiator against the exact complaint pattern competitors are
   failing on, and the backend data already exists.
3. **Support response time (12–24h+ email/ticket) is a recurring complaint at Airalo** —
   NaaraCare + the WhatsApp Autopilot channel (built in NAARA-BUILD-4) already positions Naara
   to beat this if response times are actually fast; no new build item here, just confirm
   NaaraCare's live-chat path is reachable in ≤2 taps from anywhere in the Wizard's error/topup
   states (add a "Talk to NaaraCare" link on the Wizard's `error` state if one doesn't already
   exist — inspect the Blade view to confirm).
4. **Expired/orphaned items cluttering the dashboard is a recurring Airalo complaint** ("3–4
   expired eSIMs sitting in the list, none removable without a per-entry flow"). Confirmed by
   direct inspection: **neither `SmsOrder`, `VirtualNumber`, nor `EsimPlan` has an `archived_at`
   or `is_archived` column**, and the dashboard-reorganization design in
   `docs/ROADMAP-NAARASIM-WIZARD.md` §9 (grouped tabs, archive state, renewal badges) was never
   built — it's still a design doc, not shipped code. Build it now:
   - Add `archived_at` (nullable timestamp) to `sms_orders`, `virtual_numbers`, and whatever
     table represents a user's owned eSIMs.
   - Auto-archive on real expiry (a scheduled command), and let the user manually archive a
     completed/expired item in one tap, not a per-entry hunt.
   - Reorganize the customer dashboard into **eSIMs** / **Numbers** top tabs, numbers grouped by
     type (Permanent / Rental / OTP) with a Model badge, active items separated from an
     "Archive" section — this is roadmap §9 exactly, now confirmed as directly addressing a
     real, current competitor complaint, not just a nice-to-have.

**Sources checked for this section:** Cybernews' Holafly-vs-Airalo comparison, Findingtheuniverse's 2026 Airalo review, Unstar.app's 2026 review-pattern analysis across four eSIM apps, Quackr's 2026 burner-number guide, and TwoLine's 2026 verification-success testing across four burner apps — current as of mid-2026, checked live rather than assumed.

### A.8 Missing configuration/logic list — what to add for a stronger Wizard

- **Admin-configurable Model copy:** `ProviderModels::MODELS` is currently a PHP const — name,
  tagline, and icon per Model can't be edited without a code deploy. Move name/tagline/icon
  into `Setting` rows (keyed `models.{key}.name` etc.) with the const as the default fallback,
  so admin can rename/re-tagline a Model the same way other admin-editable copy works elsewhere
  in this codebase.
- **Wizard analytics:** no tracking exists today of where users drop off in the wizard funnel
  (purpose picked → country picked → abandoned, etc.). Add a lightweight event log
  (`wizard_funnel_events` or reuse `Auditor`) so admin can see the real bottleneck data over
  time, not just this one-time audit.
- **The `needs_key` / `coming_soon` states are invisible to the user.** `ProviderModels::status()`
  distinguishes "no provider configured" from "not wired yet," but `purposes()` only surfaces
  `available()` (status = live) — a Model that's `needs_key` or `coming_soon` simply doesn't
  appear, with no explanation. If Frank wants a "more coming soon" teaser row at the bottom of
  the purpose list (common competitor pattern — shows product breadth even before every product
  is live), that's a small addition; otherwise no action needed, just flagging that the data to
  support it already exists.
- **§A.6's clarify-question extension** (already covered above).
- **§A.2's naara_connect fix** (already covered above) — this is the one item in this list that
  is a genuine bug, not a nice-to-have.

---

## PART B — PAGE BUILDER: WHY PUBLISHED PAGES NEVER GO LIVE

### B.1 Confirmed root cause

The Section Builder (`app/Livewire/Admin/PageBuilder.php`,
`app/Services/Builder/PageBuilderService.php`, `app/Support/PageSections.php`,
`app/Support/SectionLibrary.php`) is a complete, well-built system all the way through
publishing: `publish()` correctly snapshots the draft sections, flips a new
`PageSectionVersion` to `is_live = true`, busts the page's cache, and writes an audit log entry
— this part works exactly as designed.

The break is the very last step: **no public-facing route, controller, or Blade view anywhere
in the codebase calls `PageSections::live()`.** Confirmed by exhaustive grep — the only caller
of `PageSections::live()`/`hasLive()` in the entire app is the admin's own preview
(`page-builder.blade.php`, and `hasLive()` for the admin's own status badge). A ready-made
renderer partial already exists — `resources/views/partials/sections/render.blade.php` — whose
own doc comment says *"This is the single seam both the public site and the admin preview
render through, so they always look identical."* That comment describes the intended design;
it was never actually wired into `home.blade.php`, `about.blade.php`, `contact.blade.php`, or
any "how it works" view, all of which were confirmed (by direct inspection) to contain zero
reference to `partials.sections.render` or `PageSections::live`.

**In plain terms: publishing succeeds, the toast says "it is now live," but the public page a
customer actually visits never asks the database whether a built version exists — it just keeps
rendering whatever was already hardcoded in that Blade file.** This matches Frank's symptom
exactly — the appearance of an "error" is most likely the disconnect between "admin says
published" and "site shows no change," not a thrown exception in `publish()` itself, which is
correctly written.

### B.2 Fix

1. For each of the `STARTER_PAGES` (`home`, `about`, `how-it-works`, `contact`) plus any
   additional `page_key` an admin has actually built content for (query
   `PageBuilderService::pages()` for the live list), add this pattern to the top of that page's
   existing Blade view/controller:
   ```php
   $builtSections = \App\Support\PageSections::live('home'); // the real page_key
   ```
   and in the Blade view, at the point in the layout where built content should appear:
   ```blade
   @if (!empty($builtSections))
       @include('partials.sections.render', ['sections' => $builtSections])
   @else
       {{-- existing hardcoded content stays exactly as-is --}}
   @endif
   ```
   This preserves the existing "empty = fall back to hardcoded" contract that
   `PageSections::live()`'s own doc comment already promises, so nothing breaks for a page an
   admin has never touched.
2. Confirm the controller/route feeding each of these views actually passes `$builtSections`
   into the view (or compute it directly in the Blade file via the facade call above — match
   whichever pattern the rest of the marketing views already use for other dynamic data).
3. **Test end-to-end:** in the admin Section Builder, add a section to a page, hit Publish,
   then load that actual public URL in a fresh, uncached request (not the admin preview) and
   confirm the new section renders. This is the test that was never actually possible before
   this fix — do not skip it.
4. Audit whether any other admin "publish"-style feature in this codebase has the same
   disconnect (built a full CMS-style backend with no confirmed public consumer) — if you find
   one, report it in `docs/PLATFORM-STATE.md` rather than silently fixing it in this pass.

---

## PART C — SOCIAL-FOLLOW-TO-EARN + BRAND PARTNER HUNT (new feature)

This is a new, real-money-adjacent feature (grants NaaraCredits, which reduce what a user pays
on the platform) — build it with the same discipline as `CreditService`'s existing check-in and
referral rewards. Reuse `CreditService::earn()` directly; do not build a second credit ledger.

### C.1 Data model

1. **`social_follow_handles` table** (admin-managed, the platform's own official accounts):
   `id`, `platform` (enum: instagram, tiktok, x, facebook, youtube, linkedin, threads, snapchat
   — extensible list, not hardcoded to a fixed 5), `handle_label` (e.g. "Naara Nigeria"),
   `handle_url`, `credit_reward` (decimal, admin-set per handle — this is the "surprise" amount,
   never shown to the user before they follow), `is_active`, `sort_order`. Admin can add
   **multiple rows of the same platform** (e.g. two Facebook handles, four YouTube channels) —
   this is just multiple rows with the same `platform` value, not a schema constraint; the admin
   UI needs a clear "+ Add another {platform}" affordance rather than one field per platform.
2. **`social_follow_claims` table** (the "signed contract"): `id`, `user_id`, `handle_id`,
   `claimed_at`, unique constraint on `(user_id, handle_id)`. This is the mechanism that makes a
   follow permanent and unrepeatable — the same idempotent-reference pattern
   `CreditService::earn()` already uses elsewhere in this codebase (e.g.
   `"referral:{$referrer->id}:{$referredId}"`), applied here as
   `"social_follow:{$user->id}:{$handle->id}"`.
3. **`brand_partners` table** (admin-managed, the "extra layer" — other brands' handles):
   `id`, `brand_name`, `short_description`, `fallback_image` (shown before any handle-specific
   image, or if a handle has none), `background_color` (hex, admin-set, drives the scroll-tied
   section background per C.4), `sort_order`, `is_active`.
4. **`brand_partner_handles` table**: `id`, `brand_partner_id`, `platform`, `handle_label`,
   `handle_url`, `credit_reward`, `sort_order`, `is_active`. Same duplicate-platform-allowed
   pattern as C.1.1. Claims for these reuse the **same** `social_follow_claims` table (add a
   nullable `brand_partner_handle_id` alongside the existing nullable `handle_id`, with a check
   that exactly one of the two is set) — one claims table, not two, since the "can't repeat"
   logic is identical for both.

### C.2 Follow validation — real, not honor-system

Do not ship a "trust the click" version — Frank was explicit that this must genuinely confirm
the follow happened, not just that the user tapped a link. Research and use each platform's own
follow-confirmation mechanism where one exists, and be honest in the UI about the ones that
don't:

- **X (Twitter)**: OAuth 2.0 with the `users.read`/`follows.read` scope allows checking whether
  the authenticated user follows a given account via the API. Requires the user to connect their
  X account once (OAuth), not just tap a link.
- **YouTube**: Google OAuth + YouTube Data API's `subscriptions.list` can confirm a
  subscription to a specific channel for the authenticated user.
- **Instagram, TikTok, Facebook, LinkedIn, Threads, Snapchat**: research each platform's current
  (2026) official API for follow/subscription confirmation before assuming one exists — several
  of these platforms do **not** expose a public "does user X follow account Y" endpoint to
  third-party apps at all, especially Instagram and TikTok for non-business use cases. Where no
  reliable API exists, do not fake it — build a graceful, honest fallback: open the handle in a
  new tab/app, then on return show "Tap to confirm you followed" as a manual confirmation step,
  clearly distinct in the UI from the platforms with real verification (e.g. a small "Verified
  automatically" vs. "Self-confirmed" tag) — Frank should decide per-platform whether
  self-confirmed claims are acceptable or whether that platform should be admin-excluded until a
  real check exists. Document the findings per platform in
  `docs/SOCIAL-FOLLOW-VERIFICATION.md` before building the claim flow, so this decision is made
  deliberately per platform rather than uniformly guessed.
3. Whichever mechanism applies, the actual credit-grant must happen **server-side**, triggered
   by the confirmed signal (API callback/webhook or the manual confirm tap), never by the mere
   act of opening the link — the click itself proves nothing.

### C.3 The "signed contract" — one-time, tamper-resistant

1. On confirmed follow, call `CreditService::earn($user, $handle->credit_reward, 'social_follow',
   "social_follow:{$user->id}:{$handle->id}", "Followed {$handle->handle_label}")` — the
   existing idempotent-reference check in `CreditService::apply()` already guarantees this can
   never double-grant even under a retried request or a double-tap, with zero new logic needed
   for that guarantee.
2. Insert the `social_follow_claims` row in the same transaction as the credit grant (wrap both
   in `DB::transaction`), so a claim row and a credit grant can never exist independently of
   each other.
3. Once claimed, that handle's button becomes permanently disabled/greyed for that user —
   visually distinct from "not yet followed," never re-clickable, no client-side-only disabling
   (always re-check `social_follow_claims` server-side on render, since a public Livewire
   property re-enabling a "claimed" button client-side would be a real exploit surface).
4. The reward amount (`credit_reward`) is **never shown before the user follows** — it's a
   surprise, revealed only in the success message after the claim lands. The handle list itself
   can be shown, but not the amount attached to each.

### C.4 Brand Partner Hunt — dedicated page

1. Entry point from the main Rewards page: a distinct, separately-styled CTA block — title
   ("Want more NaaraCredit? Begin the hunt"), short description, no handle list or brand names
   visible here — this is the "disclosed with a title, CTA and description" teaser Frank
   described, not a preview of what's inside.
2. Dedicated page (`/rewards/hunt` or similar), Apple-inspired visual style (generous
   whitespace, large confident typography, one idea per screen-height section — match the
   design language already used for this codebase's premium/hero surfaces rather than inventing
   a new visual system).
3. One scroll-snapped or scroll-revealed section per active `brand_partner`, in `sort_order`:
   brand name as the section title, `short_description`, `fallback_image` (or the brand's own
   image once admin sets one), then a carousel of that brand's `brand_partner_handles` — each
   handle card shows the platform icon + `handle_label`, follows the same C.2/C.3
   validation-and-one-time-claim logic as the main Naara handles.
4. **Scroll-tied background color:** as each brand's section enters the viewport, transition the
   page background to that brand's `background_color` (smooth CSS transition, not an abrupt
   cut) — use an intersection-observer pattern (Alpine's `x-intersect` is already used elsewhere
   in this codebase for scroll-triggered behavior — reuse that pattern here rather than a new
   scroll library).
5. Credits earned here spend exactly like check-in/referral NaaraCredits — same
   `CreditService::quoteRedemption()` margin-capped logic already in place elsewhere, so this
   can **never** cut into the platform's minimum profit floor no matter how many credits a user
   accumulates. No new redemption logic needed — confirm the existing redemption path already
   covers credits regardless of source (it should, since `CreditService::balance()` sums the
   whole `naara_credits` column, not per-source) and note in your build report if it doesn't.
6. Admin CMS for both `social_follow_handles` and the full `brand_partners` +
   `brand_partner_handles` structure: add/edit/remove/reorder, image upload (route through
   `MediaStorage`, per NAARA-BUILD-1's storage fix — don't bypass it), color picker for
   `background_color`, and a live preview of the scroll-color-change behavior before publishing
   changes live.

---

## PART D — LOTTIE ANIMATION WIRING

Five `.lottie`/dotLottie export zips have been provided (each contains a `manifest.json` +
`animations/{uuid}.json`), named precisely to indicate placement. Follow the same wiring
pattern already used for the existing `resources/js/animations/reward.json` (inspect that
existing integration first — component name, how it's loaded/played — and match it exactly for
consistency, rather than introducing a second animation-loading pattern).

1. **Merchant V1 Premium Badge** (`Merchant_V1_Premium_Badge_lottie.zip`) — place at
   `resources/js/animations/merchant-v1-badge.json` (extract the inner `animations/*.json`,
   rename to this path). Wire into wherever a Merchant V1 badge/status indicator is shown —
   dashboard header, pricing comparison (NAARA-BUILD-4 §3), and the admin promotion action
   (NAARA-BUILD-4 §4.2)'s success confirmation.
2. **Merchant V2 Premium Badge** (`Merchant_V2_Premium_badge_lottie.zip`) →
   `resources/js/animations/merchant-v2-badge.json` — same wiring pattern as V1, for V2-tier
   surfaces specifically. Don't reuse the V1 file for both tiers — they're visually distinct by
   design.
3. **Merchant hero-account illustration** (`Merchant_Illustration_for_hero_account...zip`) →
   `resources/js/animations/merchant-hero-illustration.json` — wire into
   `resources/views/livewire/merchant-dashboard.blade.php` (confirmed to exist), specifically to
   visually differentiate a merchant's dashboard from a normal customer dashboard at a glance —
   likely near the dashboard header/welcome area; inspect the current merchant-dashboard layout
   before placing it so it doesn't collide with existing header content.
4. **Refer & Earn / Affiliate Link** (`Refer_and_earn_by_sharing_your_Affiliate_Link...zip`) →
   `resources/js/animations/refer-and-earn.json` — wire into
   `resources/views/livewire/referrals.blade.php` (the dedicated Refer & Earn page).
5. **Rewards coin-claim animation** (`Rewards_with_Coin_animation...zip`) →
   `resources/js/animations/reward-claim-coin.json` — this one is explicitly reusable across
   **every** reward-claim moment, per Frank's own file naming: the existing `Rewards.php`
   `checkIn()` success toast, **and** the new C.3 social-follow claim success moment, **and**
   the Brand Partner Hunt claim success moment, **and** the Referrals page's first-referral
   reward moment if one exists. Build this as one shared Blade/Alpine component (e.g.
   `<x-reward-claim-popup :amount="$earned" />`) triggered by a single Livewire/JS event (e.g.
   `dispatch('credit-claimed', amount: $earned)`) that any of these four flows can fire, rather
   than four separate copies of the same popup — this is exactly the kind of "one component, many
   triggers" pattern the rest of this codebase already favors (see `AlertAdminJob` reused across
   every alert type in earlier builds).

**Verification:** trigger each of the five placements manually (a real daily check-in, a real
social-follow claim, a real referral, viewing a merchant dashboard, viewing the pricing
comparison) and confirm the correct animation plays at the correct spot, at correct size, with
no layout shift.

---

## E. WHEN THIS PROMPT IS DONE
- [ ] `naara_connect` fully wired in the Wizard (device-check step, Checkout hand-off, Claude description) — confirmed it can never reach `SmsNumberRouter`/throw the "Unknown number type" error
- [ ] Stale "Permanent numbers are coming soon" comment in `SmsNumberRouter::order()` corrected
- [ ] Wizard success-rate signal (`compareOperators()`'s existing `success` field) surfaced to the user at the service/review step
- [ ] NaaraCare reachable in ≤2 taps from the Wizard's error/topup states — confirmed, not assumed
- [ ] Dashboard reorganization (roadmap §9) actually built: `archived_at` on relevant models, auto-archive on expiry, eSIMs/Numbers tabs, Model-badge grouping
- [ ] Claude sprinkle extended with an optional `clarify` follow-up (still fully whitelisted, still off the money path)
- [ ] Public marketing pages (home/about/contact/how-it-works, and any other built page) now actually render `PageSections::live()` via `partials.sections.render` — tested end-to-end with a real publish, in a fresh uncached request, not the admin preview
- [ ] Social-follow-to-earn built: admin-manageable handles (duplicate-platform-capable), real per-platform follow verification researched and documented in `docs/SOCIAL-FOLLOW-VERIFICATION.md`, server-side-only credit grant via `CreditService::earn()`, one-time-claim enforced server-side and unbypassable client-side
- [ ] Brand Partner Hunt page built: Apple-inspired scroll sections, scroll-tied background color per brand, same validation/one-time-claim logic, credits spend through the existing margin-capped redemption path
- [ ] All 5 Lottie animations wired to their named destinations; the coin-claim animation built as one shared, reusable component across all four claim moments
- [ ] `docs/PLATFORM-STATE.md` updated: this build's completions moved to "Done," the eSIM in-widget-purchase-vs-handoff decision (§A.5) logged as an open product decision for Frank, and any new hardcoded/admin-gap items found while building added to "Flagged but not yet built"
