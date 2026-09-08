# NAARA BUILD 9 of 9: BRAND DIRECTORY — SELF-SERVICE PAID LISTING SUBSCRIPTIONS
**Run after NAARA-BUILD-6 (which built the admin-only `brand_partners`/`brand_partner_handles`
tables and the Brand Partner Hunt page) is verified working. This build extends those exact
tables into a self-service, paid subscription product — it does not replace or duplicate them.**
Give this whole file to Claude Code as one message.

**Note on the device-compatibility "realtime decorator":** already fully specified in
NAARA-BUILD-8 §6 — best-effort client-side auto-detection pre-filling the existing searchable
device list, with the manual confirmation always still available. Nothing further needed here;
this file is the Brand Directory expansion only.

---

## 0. HOW TO WORK
- Inspect the actual file/component before assuming its contents or editing it.
- Do not create duplicate components, tables, or systems. Extend `brand_partners`,
  `brand_partner_handles`, and `social_follow_claims` from NAARA-BUILD-6 — do not build a
  second brand table. Reuse `WalletService`, `CreditService`, `MediaStorage`, and the recurring
  monthly-billing pattern already used for Naara Line renewals — do not build a second
  subscription-billing mechanism.
- After each numbered section, verify manually before moving to the next.
- Commit after each numbered section — clean rollback points.
- This file is self-contained — do not open or reference any other file for context.
- **Money-safety discipline applies at full strength here — this section moves real wallet
  balance every month.** Every debit goes through `WalletService` with the same
  transaction+lock discipline as everywhere else in this codebase. Every credit grant (follow,
  video-watch) goes through `CreditService::earn()` with an idempotent reference, exactly like
  NAARA-BUILD-6's social-follow claims.

---

## 1. WHAT THIS EXTENDS, EXACTLY

NAARA-BUILD-6 built `brand_partners` (admin-only: `brand_name`, `short_description`,
`fallback_image`, `background_color`, `sort_order`, `is_active`) and `brand_partner_handles`
(`platform`, `handle_label`, `handle_url`, `credit_reward`, `sort_order`, `is_active`), with
claims flowing through the shared `social_follow_claims` table. That system stays exactly as
built for **admin-curated** brands. This build adds a **second, self-service path** onto the
same tables — a real business can now apply, pay, and manage their own listing — while admin
keeps the ability to hand-place brands directly, always pinned above the self-service listings.

---

## 2. SCHEMA EXTENSIONS

### 2.1 Extend `brand_partners`
Add: `owner_user_id` (nullable FK to `users` — null means admin-placed/featured, set means
self-service), `category` (string, from the taxonomy in §7), `listing_status` (enum:
`pending_setup`, `active`, `paused_billing`, `disabled`), `is_featured` (boolean — always `true`
and locked for admin-placed brands, never settable by a self-service owner), `hero_image_path`
(webp, routed through `MediaStorage`), `priority_score` (integer, default 0 — see §6),
`current_plan_id` (nullable FK to `brand_subscription_plans`).

### 2.2 New table `brand_subscription_plans` (admin-configurable pricing tiers)
`id`, `name` (admin-editable, friendly — e.g. "Starter Reach," "Growth," "Spotlight" — pick
names that sound like a real growth product, not a generic "Tier 1/2/3"), `price_usd_per_month`,
`handles_included` (how many social handles this plan allows), `guaranteed_followers_per_handle_
per_month` (the real number this plan promises to deliver per handle, per month — this is the
transparency mechanism §6 is built around), `video_previews_allowed` (0, 1, or 2 — only the
higher tiers unlock video), `is_active`, `sort_order`. Ship with three or four real starter
plans an admin can immediately edit — do not leave this empty at build time.

### 2.3 New table `brand_subscriptions` (the billing record)
`id`, `brand_partner_id`, `plan_id`, `status` (enum: `active`, `past_due`, `cancelled`),
`started_at`, `cancelled_at` (nullable), `next_billing_at`, `last_charged_at`,
`grace_reminders_sent` (integer, counts reminder nudges during a past-due period — see §5.3).

### 2.4 New table `brand_partner_videos` (premium-tier video previews)
`id`, `brand_partner_id`, `video_url`, `platform` (enum: `youtube`, `vimeo`), `sort_order`.
Enforce `video_previews_allowed` from the brand's current plan as a hard cap — never let a
downgrade leave more videos live than the new plan allows (see §5.4).

### 2.5 Extend `social_follow_claims`
Already has a nullable `brand_partner_handle_id` from NAARA-BUILD-6 — no change needed, this
build's follow-claims flow through the exact same row.

### 2.6 New table `brand_video_watch_claims`
`id`, `user_id`, `video_id` (FK to `brand_partner_videos`), `watched_at`, unique constraint on
`(user_id, video_id)` — same one-time-claim shape as `social_follow_claims`, for the exact same
reason: a user earns from a given brand's video once, ever, not on every replay.

---

## 3. WATCH-TIME VERIFICATION — SERVER-CONFIRMED, NOT CLIENT-CLAIMED

**Do not grant the video-watch credit on a single client-side "I watched 60 seconds" message.**
That's trivially fakeable by calling the Livewire method directly from the browser console,
bypassing the player entirely — a real gap Frank didn't ask about but which would break the
"transparency" promise he was explicit about elsewhere in this request.

1. Use the real YouTube IFrame Player API (`onStateChange`/`getCurrentTime()`) or Vimeo's
   Player.js (`timeupdate` event) — both already give genuine playback-position events, not
   just a play/pause flag.
2. While a brand's preview video is playing, the client sends a lightweight heartbeat to the
   server every ~10 seconds, each carrying the player's actual reported `currentTime` and a
   short-lived signed token (minted per video-view session) so a replayed/forged heartbeat
   can't be trivially scripted.
3. Server-side, accumulate confirmed watch-time per `(user, video)` session from these
   heartbeats — only once the accumulated, server-confirmed watch-time reaches 60 seconds does
   `brand_video_watch_claims` get written and `CreditService::earn()` fire, with reference
   `"brand_video:{$user->id}:{$video->id}"`.
4. This is a real-time signal, same spirit as the rest of this platform's "confirm, don't trust
   the click" discipline (matches NAARA-BUILD-6's honest per-platform follow-verification
   research) — it doesn't need to be cryptographically bulletproof, just meaningfully harder to
   fake than a single client message.

---

## 4. DAILY CREDIT CAP — HARD STOP AT 100, ACROSS BOTH EARNING TYPES

1. Before granting **any** credit from a follow claim (§2.5) or a video-watch claim (§3), sum
   that user's `CreditLedger` entries for the current day where `source` is `social_follow` or
   `brand_video` (both the NAARA-BUILD-6 admin-curated handles and this build's self-service
   brand handles count toward the same cap — it's one daily ceiling across the whole Hunt
   experience, not per-brand).
2. If that sum is already ≥ 100, **block the claim entirely** — do not grant a partial amount.
   Show: "You've hit today's NaaraCredit limit from following and watching. Come back tomorrow
   for more." Do not let the button silently do nothing — the message is part of the product,
   per Frank's own framing of this as a strategic, transparent system.
3. If granting a claim's reward would cross 100 without exceeding a reasonable cap-respecting
   rule, decide simply: cap check happens *before* the grant, using the sum *before* this claim
   — so a user can complete a claim that pushes them slightly over 100 once, but the *next*
   claim attempt is blocked. This avoids fiddly partial-credit math while still keeping the cap
   meaningful. Document this rule in a code comment since "slightly over on the last claim of
   the day" is a deliberate simplicity choice, not a bug.
4. Reset boundary: UTC midnight, admin-configurable if a `Setting` for this already exists
   elsewhere in the codebase for daily resets — otherwise a simple UTC day boundary is fine.

---

## 5. ONBOARDING, BILLING, PAUSE/RESUME, CANCELLATION

### 5.1 Onboarding flow — plan first, then profile
1. Landing page (§9) → business picks a plan from `brand_subscription_plans` → immediate
   `WalletService` debit for the first month, `brand_subscriptions` row created
   (`status = active`, `next_billing_at` = today + 1 month) → `brand_partners` row created with
   `listing_status = pending_setup`, `owner_user_id` = the authenticated business user.
2. Immediately redirect into a guided setup wizard (multi-step, not one long form):
   - **Brand name + hero image.** Standard YouTube-thumbnail proportions (1280×720), minimum
     1MB upload — validate both dimensions and minimum file size client-side before upload,
     server-side again on receipt. Route through `MediaStorage`.
   - **Social handles**, up to `plan.handles_included`. For each: a platform selector, then a
     handle URL field with **live format validation** per platform (e.g. Instagram:
     `instagram.com/{username}`, X: `x.com/{username}`, YouTube: `youtube.com/@{handle}` or
     `youtube.com/channel/{id}`, TikTok: `tiktok.com/@{username}`) — show a clear green
     check/red-X indicator as the business types, so they know immediately if a URL is in a
     recognized, followable format before submitting. This is a format check, not a live
     existence check — reuse §3's honest "verified vs. self-confirmed" distinction from
     NAARA-BUILD-6 for what happens once real users try to follow it.
   - **Category** — single-select from the taxonomy in §7 (required — this drives directory
     placement).
   - **Video previews** (only shown if `plan.video_previews_allowed > 0`) — up to that many
     YouTube/Vimeo URLs, validated as embeddable (reachable oEmbed response) before saving.
3. Once every required field is complete, flip `listing_status` to `active` — this is the
   moment the brand becomes visible and followable in the directory (§8).

### 5.2 Monthly billing — reuse the existing recurring-charge pattern
1. A scheduled command (same shape as the existing Naara Line monthly-renewal job — reuse that
   pattern, don't invent a second one) runs daily, finds `brand_subscriptions` where
   `status = active` and `next_billing_at <= today`, and attempts a `WalletService` debit for
   `plan.price_usd_per_month`.
2. On success: extend `next_billing_at` by one month, update `last_charged_at`, reset
   `grace_reminders_sent` to 0.
3. On insufficient balance: **do not cancel anything yet.** Set `brand_subscriptions.status =
   past_due`, set the linked `brand_partners.listing_status = paused_billing` (hidden from the
   directory and no longer followable — existing follows/credits already earned are untouched),
   and send a top-up reminder to the business owner (reuse the WhatsApp Autopilot / email
   notification channels already built — this is exactly the kind of proactive nudge that
   infrastructure exists for).
4. Retry the debit daily while `status = past_due`, incrementing `grace_reminders_sent` on each
   reminder sent (cap reminder frequency sensibly — e.g. once daily, not once per retry attempt
   if retries run more often). The moment a retry succeeds, flip back to `status = active` and
   `listing_status = active` automatically — no manual re-approval needed, matching Frank's
   "reminds them to top up... for their listing to be enabled again."
5. No automatic hard cancellation after some fixed grace period unless Frank wants one — leave
   it as an indefinite `past_due`/reminder loop for now, since he didn't specify a cutoff, and
   flag this as an open question in `docs/PLATFORM-STATE.md` rather than inventing a cutoff
   value.

### 5.3 Cancellation — explicit, business-initiated
1. A clear "Cancel listing" action in the business's own management dashboard (§5.5).
2. On cancel: `brand_subscriptions.status = cancelled`, `cancelled_at` = now, no further billing
   attempts, `brand_partners.listing_status = disabled`, and the brand's listing moves out of
   the active directory into an admin-visible archive (filtered out of §8's public directory
   entirely, not just deprioritized).
3. **Resubscribing reactivates the same `brand_partners` row** — don't create a duplicate brand
   record. Picking a plan again creates a new `brand_subscriptions` row linked to the same
   brand, sets `listing_status` back to `active` (assuming setup was already complete — skip
   straight past onboarding for a returning brand), and the listing leaves the archive.

### 5.4 Plan changes / downgrades
If a brand downgrades to a plan with fewer `handles_included` or `video_previews_allowed` than
they currently have live, do not silently delete the excess — surface a clear "you have more
handles/videos than your new plan allows, choose which to keep" step at the moment of downgrade,
so a business never loses content without an explicit choice.

### 5.5 Business-facing management dashboard
A dedicated area (separate from the customer-facing directory) where a business owner can see:
current plan, next billing date, real follower delivery this month vs. their plan's guarantee
(§6 — this is the transparency Frank was explicit about), edit their profile/handles/videos
within their plan's limits, change plan, and cancel.

---

## 6. THE FOLLOWER-GUARANTEE / PRIORITY-BOOST MECHANISM

This is the trust mechanism Frank was explicit must be "very solid" and "calculative," not
generic — build it as a real, auditable calculation, not a cosmetic reorder.

1. For each active `brand_partner_handles` row belonging to a self-service brand, track real
   follow claims (`social_follow_claims` scoped to that handle) that landed within the current
   billing month (`brand_subscriptions.last_charged_at` to `next_billing_at`).
2. At the end of each billing cycle (triggered by the same billing command from §5.2, right
   after a successful charge — evaluating the *just-completed* month before starting the new
   one), compare actual follows delivered against `plan.guaranteed_followers_per_handle_per_month`
   for each of that brand's handles.
3. If any handle fell short of its guarantee, increase that brand's `priority_score` by a
   calculated amount proportional to the shortfall (e.g. a simple, transparent formula:
   `shortfall_pct * plan.guaranteed_followers_per_handle_per_month` — keep the exact formula
   simple and documented in a code comment, since this number directly drives real placement
   and should be auditable/explainable if Frank or a business ever asks "why is this brand
   boosted").
4. §8's directory sort order uses `priority_score` (descending) as a tiebreaker **within** each
   category, after admin-featured brands (always first) — so a brand that under-delivered last
   month gets genuinely more visibility this month, a real, working promise rather than a
   marketing claim.
5. `priority_score` decays back toward 0 gradually once a brand consistently meets its guarantee
   (e.g. reduce by a fixed step each successful on-target month) — so boosted placement is a
   temporary correction, not a permanent advantage that never resets.
6. Log every priority adjustment (`brand_priority_log` table: `brand_partner_id`, `handle_id`,
   `month`, `guaranteed`, `actual`, `adjustment`, `new_priority_score`) so this is fully
   auditable later, matching the "transparency is the ultimate key" requirement directly.

---

## 7. BUSINESS CATEGORY TAXONOMY — RESEARCHED, READY TO USE

A clean, directory-appropriate taxonomy sized for a creator/small-business follower-growth
context (not a generic enterprise B2B directory) — broad enough to sort meaningfully, narrow
enough to stay simple, matching Frank's "nothing gets complicated" instruction. Use this list
directly as the seed data for `category` — admin can add more later via a simple settings list,
this isn't meant to be permanently fixed in code:

`Fashion & Apparel` · `Beauty & Skincare` · `Food & Beverage` · `Restaurants & Cafés` ·
`Fitness & Wellness` · `Health & Nutrition` · `Music & Artists` · `Comedy & Entertainment` ·
`Film & Media Production` · `Photography & Videography` · `Gaming & Esports` · `Tech & Apps` ·
`Finance & Fintech` · `Real Estate & Property` · `Travel & Tourism` · `Automotive` ·
`Education & Coaching` · `Business & Entrepreneurship` · `Fashion Retail & E-commerce` ·
`Home & Interior Design` · `Beauty & Hair Services` · `Events & Nightlife` ·
`Sports & Athletics` · `Parenting & Family` · `Nonprofit & Community` · `Faith & Spirituality` ·
`Art & Design` · `Podcasts & Talk Shows` · `News & Commentary` · `Other`.

`Other` always exists as a fallback so onboarding never blocks on a category not yet listed —
admin can add a real category later and recategorize.

---

## 8. THE PUBLIC DIRECTORY — HOW IT ALL COMES TOGETHER

1. This lives inside NAARA-BUILD-6's Brand Partner Hunt page as a real, categorized directory —
   not a flat scroll of every brand. Group by `category` (§7), each category showing its brands
   as a filterable section or its own sub-page (match whichever pattern reads cleaner at real
   volume — start with grouped sections, add a category filter/tab bar if the brand count grows
   large).
2. **Sort order within each category:** admin-placed brands (`owner_user_id = null`, always
   `is_featured = true`) first, always — then self-service brands ordered by `priority_score`
   descending (§6), then by `brand_subscriptions.started_at` as a stable tiebreaker (rewards
   longer-standing subscribers slightly, all else equal).
3. Only brands with `listing_status = active` ever appear — `pending_setup`, `paused_billing`,
   and `disabled` brands are invisible to the public directory, matching §5's state machine
   exactly.
4. Each brand's card keeps the scroll-tied background-color behavior from NAARA-BUILD-6, its
   handle carousel with the same one-time-claim follow logic, and now also its video previews
   (§3) where the brand's plan includes them.

---

## 9. THE "GET LISTED" CTA + LANDING PAGE

1. A clear, professional CTA placed within the Hunt page (not hidden in a menu) —
   "Grow your following. List your brand on Naara." — distinct in style from the credit-hunter-
   facing content around it, since this is a business pitch, not a consumer feature.
2. Landing page content, written to genuinely explain the value, not just list prices:
   - What this is: real Naara users actively following brands to earn credit, meaning genuine
     engagement from people already motivated to follow, not passive impressions.
   - What it gets a business: real follows/subscribers on the specific handles they list, plus
     (on eligible plans) real watch-time on a featured video.
   - The guarantee mechanism from §6, explained honestly and simply — what happens if a target
     isn't hit (boosted priority next cycle), so businesses understand this is a real,
     accountable system, not a flat fee for a static listing.
3. **Plan comparison table**, pulled live from `brand_subscription_plans` — price per month,
   handles included, guaranteed followers per handle per month, video previews included — laid
   out so the "per-follower" value is obvious at a glance (e.g. show the effective cost per
   guaranteed follower per handle as a small calculated line under each plan, computed from the
   plan's own numbers, not a separate hardcoded figure).
4. Tapping a plan starts §5.1's onboarding flow directly — no separate "contact us" step in
   between; Frank was explicit this should "take effect immediately they subscribe."

---

## 10. ADMIN CONTROL

1. Manage `brand_subscription_plans` — add/edit/archive plans, adjust pricing and guarantees.
2. View every self-service brand with its current `listing_status`, plan, billing history, and
   `priority_score` history (from `brand_priority_log`, §6.6) — a real operational view, not
   just the public directory.
3. Admin can still place a brand directly (the original NAARA-BUILD-6 path) — always
   `is_featured = true`, always sorts first in its category, never subject to billing or the
   priority mechanism at all, exactly as before.
4. Admin can manually override a brand's `listing_status` (e.g. suspend a brand for a policy
   violation) independent of the billing state machine — this override always takes precedence
   over whatever the billing/priority automation would otherwise set.

---

## 11. WHEN THIS PROMPT IS DONE
- [ ] Schema extensions built on top of NAARA-BUILD-6's existing `brand_partners`/`brand_partner_handles` tables — confirmed no duplicate brand system was created
- [ ] Video watch-time credit is server-confirmed via real player-API heartbeats, not a single client-side claim — tested that calling the claim method directly without real playback does not grant credit
- [ ] Daily 100-credit cap enforced across both follow claims and video-watch claims combined, blocking cleanly with a clear message once hit
- [ ] Onboarding flow built: plan selection → immediate wallet debit → guided profile setup (hero image with real size/dimension validation, handle format validation with live pass/fail indicator, category selection, video URLs on eligible plans)
- [ ] Monthly billing reuses the existing recurring-charge pattern; insufficient balance pauses (not cancels) the listing, sends a reminder, and auto-resumes on next successful charge with no manual re-approval
- [ ] Explicit cancellation flow: disables and archives the brand, distinct from a billing pause; resubscribing reactivates the same brand record, not a duplicate
- [ ] Follower-guarantee tracking and priority-boost mechanism built, calculated from real claim data, logged in an auditable `brand_priority_log`, and decaying back to baseline after sustained on-target delivery
- [ ] Business category taxonomy seeded and used to drive directory grouping and onboarding category selection
- [ ] Public directory sorts admin-featured brands first, then self-service brands by priority score, shows only `active` listings, and preserves NAARA-BUILD-6's scroll-tied background-color and one-time-claim behavior
- [ ] "Get Listed" CTA + landing page built with a live plan-comparison table and an honest explanation of the guarantee mechanism
- [ ] Business-facing management dashboard built: current plan, billing date, real delivery-vs-guarantee this month, edit profile within plan limits, change plan, cancel
- [ ] `docs/PLATFORM-STATE.md` updated: this build's completions moved to "Done," the open question of whether `past_due` subscriptions should ever hard-cancel after a fixed grace period logged for Frank's decision, and the taxonomy noted as admin-extensible, not fixed
