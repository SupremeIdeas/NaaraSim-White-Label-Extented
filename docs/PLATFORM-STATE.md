# Platform State — notable fixes & their root causes

A running log of platform-level fixes that are worth *understanding*, not just
patching — so the next person (or the next Claude session) knows why a file
exists and must not be removed again.

---

## Merchant deferred-verification + merchant/rewards visuals (BUILD-4 start) — 2026-08-03

### Done
- **§1 — merchant KYB deferred to payout time.** Removed the KYB (KYC-L3) hard
  gate from `MerchantService::apply()` — a user now becomes a merchant on
  eligibility alone (spend / paid enrollment / referrals), no identity check up
  front. `BecomeMerchant` + view dropped the blocking "Verify identity" stage
  (now Unlock → Apply) and show a "you'll verify when you first cash out" note.
  Verification is enforced at payout: `MerchantWithdrawalService` already requires
  a verified (KYC-L2) payout account, and now optionally requires **business KYB
  (L3) for a single payout above an admin threshold**. That rule ships **OFF**
  (`merchants.kyb_over_threshold_enabled=false`, threshold `merchants.kyb_threshold_usd=$500`),
  both editable on Admin → Merchants — the mechanism is ready but dormant until
  Frank sets the compliance stance. **Open product decision:** the threshold
  value / whether to enable it.
  - Tests updated to the new flow (an eligible, unverified user can apply) + two
    new payout tests (large payout clears with the rule off; blocked without KYB
    when on). Full suite green (1018).
- **§2.1 — global, data-driven merchant business-registration form.** New
  `BusinessRegistration` catalogue: full ISO country list (names via intl) +
  per-country registration-identifier types (US EIN, UK Companies House CRN,
  BR CNPJ, AU ABN, NG CAC/RC, …) with a generic fallback. Replaced the hardcoded
  4-country / CAC-TIN merchant KYB picker; business verification is now an
  OPTIONAL step (not a gate, per §1) with country-scoped reg-type selects.
- **§2.2 — global KYB provider: RESEARCHED, integration pending Frank's choice.**
  `docs/KYC-GLOBAL-PROVIDER.md` documents current (2026) coverage and recommends
  **Sumsub** (220+ country KYB registry, single KYC+KYB vendor) with **Persona**
  as the flexible alternative, keeping Dojah/Smile ID for African markets. The
  integration seam (a `KycProviderInterface` impl + country routing in
  `KycService`, mirroring `PayoutAccountService`) is documented and ready; it
  needs a vendor pick + API keys before coding. Per §12, research-only is an
  accepted completion for this pass.
- **Merchant/rewards/referral Lottie visuals.** Five dotLottie exports wired
  through the existing `lottie-web` pipeline (V2 badge's bundled PNGs inlined as
  data-URIs): refer-earn hero, merchant premium hero + V1/V2 tier badge chip
  (contained so the medal can't overflow), rewards confetti on check-in.

### Not yet built (BUILD-4 remaining, in order)
- §2.2 global KYB provider integration (blocked on vendor pick + keys); §3
  visible V1/V2 pricing + referral-margin lock-in; §4 promotion tools; §5
  payout-gate UI; §6 messaging entry points; §7 WhatsApp Autopilot; §8 payout
  ranking; §9 App Builder compile backend; §10 preloader; §11 welcome screen.

---

## eSIM region/country navigation + Control Center (BUILD-8) — 2026-08-03

Executed against Frank's 22 real Airalo reference screenshots (not a placeholder
design). The premium 4-image eSIM hero slider was left **exactly as-is** by
explicit instruction — this build only adds *beneath* it.

### Done
- **§2 Schema.** `esim_plans` gained `coverage_type` (local|regional|global) +
  `region_slug` (both nullable, indexed) and `ai_tooltip` / `ai_tooltip_override`
  / `ai_tooltip_generated_at`. New `esim_country_images` (ISO2) and
  `esim_region_images` (region slug) tables, icon + detail banner both nullable
  → clean flag/glyph/gradient fallback when unset. All backward-compatible.
- **§1/§2 Sync — research-aware coverage derivation.** `CatalogueSyncService`
  now derives coverage_type + region_slug in each mapper from that provider's
  REAL signal only, normalised through `EsimRegions` to one canonical slug set;
  it never invents a region a provider doesn't supply. Fallback when no region
  name exists: country count (1 = local; ≥100 = global; else regional), with
  region_slug left null.
- **§3 Customer navigation.** Search + Popular/Local/Regional/Global segmented
  control nested INSIDE the existing data/full lines (never mixed). Country/
  region/global tiles (image or fallback + name + live "from $X.XX" teaser +
  count) → selected banner + plan list → dedicated plan-detail view with honest
  facts + AI tooltip. All served by the new cache-only `EsimCatalogue` — **zero
  live provider calls in the browsing path** (busted on sync / image change).
- **§4 Admin eSIM Control Center** at `/adminmaster/esim`, gated by the new
  `esim.manage` staff scope (admin/super_admin bypass): per-provider sync status
  + trigger, Popular curation toggle, per-plan + bulk margin editing
  (`override_markup_pct` → PricingEngine, MarginGuard still floors), Claude
  margin *suggestions* (suggestion-only, audit-logged, never auto-applied),
  AI-tooltip view/regenerate/override, and country/region image management via
  `MediaStorage`.
- **§5 AI tooltips.** Queued `GenerateEsimTooltipsJob` fires after a sync for
  only the plans whose data/validity/coverage changed (cost control). Masking
  discipline holds — no price/cost/provider in the prompt. Manual override always
  wins via the `display_tooltip` accessor. Fails safe to null.
- **§6 Device auto-detect.** The existing brand-grouped, DB-backed compatibility
  modal (already built in "esim_upgrade Part 2") gained best-effort client-side
  detection (UA / Client Hints, Android model-code → marketing-name map),
  pre-selecting the device and showing a supported/unsupported/likely banner. The
  list stays fully interactive; the authoritative `DeviceCompat::check()` gate is
  unchanged. iOS stays generic — Safari never reveals the iPhone model.
- **§7 Seed imagery.** 118 country cutouts + 9 region maps shipped under
  `public/images/esim/…`, wired by `EsimImageSeeder` (idempotent; never clobbers
  an admin upload).

### Provider research findings (region / image / feature fields)
Concept of Local/Regional/Global was pre-confirmed for 6 of 7 providers; this
build confirms the mapping approach and documents what is/isn't available:
- **Airalo (live provider):** operator `type` = local|global and a `slug`
  (europe/world) are the real signals — used directly. Regional/global packages
  carry the slug, locals carry `country_code`.
- **eSIM Go:** real distinct `region` filter — mapped from `region`.
- **Zendit:** categorised by country AND region — mapped from `region`/`regions`.
- **Quibity / Monty Mobile / 1GLOBAL:** regional/global concept confirmed;
  exact field names are partner-gated, so the mapper reads a `region` field
  defensively and otherwise falls back to the country count. Confirm the precise
  field names against each authenticated partner response before relying on them.
- **Gigs:** MVNO-in-a-box — may expose no browsable region taxonomy at all. The
  mapper degrades cleanly to the count-based fallback (region_slug null); this is
  expected, not a gap.
- **Feature badges (5G / hotspot / top-up):** NOT surfaced. None of the current
  providers' confirmed responses expose these fields, so the plan-detail view
  shows only honest, derivable facts (data, validity, voice, coverage) rather
  than a fabricated feature list. Add per-provider once a real field is confirmed.

### Not runnable here
`composer install` could not complete in this sandbox (proxy timeouts cloning
aws-sdk-php), so the full test suite / `migrate` could not be exercised — every
new PHP file passes `php -l`, and the code follows the existing, tested patterns
(updateOrCreate sync, PricingEngine.recompute, MediaStorage uploads, cached
catalogue lists, queued AI jobs). Run `php artisan migrate` + `db:seed` +
`php artisan test` once dependencies install to confirm end-to-end.

---

## Chat / mobile / branding (BUILD-3) — 2026-08-03

### Done so far
- **§2 Wizard no longer overlaps Nia.** The global buttons-only Wizard is not
  mounted on the `support` route, so it never floats over the AI conversation.
- **§3 Unified chat input + real in-browser voice recording.** The NaaraCare
  composer is one rounded container (text + attach + mic) plus send. The mic now
  records in-page via `getUserMedia`/`MediaRecorder` (`resources/js/support-voice.js`,
  an Alpine data component) instead of opening the native file picker. It
  **explains first** — a branded "needs mic access" panel before the OS prompt —
  and handles granted / denied / unsupported explicitly, then hands the blob to
  the existing `voiceNote`/`sendVoice` pipeline via `$wire.upload`. This
  explain-first pattern is the template for any future camera/mic/location ask.
- **§4 Section-aware Wizard float.** On eSIM/Number it swells into "Confused?
  Use The Wizard" with a thin electric edge; in Gift it fades out; on Home/else
  it rests as "Ask NaaraSim". Transform/opacity only, reduced-motion-safe.
- **§5 Nia glow + 3-phase paced reveal (SupportChat only, no new component).**
  Brand glow (`resources/css/nia-glow.css`, colours Deep Teal `#0A6E6E` / Warm
  Gold `#D4A017` / Bright Teal `#2dd4bf`) via `<x-nia-glow-wrapper>` — wraps ONLY
  the Nia input and AI/typing bubbles, on for focus/input/typing, off 300ms
  after, paused off-screen. The reply is still persisted synchronously by the
  server (history/tests unaffected); the client (`resources/js/nia-chat.js`, an
  Alpine store) reveals the newest reply with Reading delay (4s+rand) → Typing
  indicator (1.2s+rand) → human char-by-char stream (~200 WPM, clamped 2–5s,
  ±8ms jitter, occasional punctuation pause), queuing replies so none is dropped.
  `streamMessageId` marks which bubble to animate.

### §6 mobile UX — Done
- §6.1 notification panel is a viewport bottom-sheet on mobile (dropdown from
  sm up); §6.5 More sheet is height-bounded + scrolls; §6.7 grid/list toggle
  persisted (`nx_more_layout`); §6.8 distinct `users` referral icon; §6.3 hero
  buttons own full-width row on mobile; §6.10 rewards title/desc beside the
  Lottie; §6.11 bento badges get distinct per-meaning/per-card gradients.
- Already satisfied in prior code (verified): §6.2 Catalogue `$search`; §6.4
  wizard picker (NumberCatalogue lists already `Cache::rememberForever`, rows
  render content directly, search bound to `x-model`); §6.6 More rows already
  opaque (no glass); §6.9 single `nx-grad-icon` gradient definition.

### §7 Global glass sidebar — Done
- `<x-global-sidebar>` slide-out beside the bell + theme toggle, premium
  glassmorphism. Contents are server-rendered (cached via `SidebarMenu`), so the
  legal/compliance pages are reachable with no live network path — works offline
  for app-store review. Always shows the legal docs (from `LegalContent`), social
  handles (`SocialLinks`), and a fast, prominent **Delete my account**. Admin CMS
  (Admin → Sidebar menu) adds custom links, a reviews URL, list/grid display
  mode, and a live blog widget.

### §8 Homepage video — Done (see "Homepage media" build below)
### §9 Homepage story — Done (see below)

### §10 Logo management — already built (verified)
- `<x-brand-logo>` variants: `family` (Naara, header → dashboard), `product`
  (NaaraSim, eSIM/Number sections), `gift` (Naara Gift, gift section). All admin-
  managed via Admin → Branding; the header mark routes to the dashboard.

### §11 Social-login icons — already built (verified)
- Real bundled brand glyphs exist for all six providers (`svc-google/facebook/
  apple/x/microsoft/discord`), rendered by `<x-service-icon>`, and every icon is
  admin-overridable via Admin → Service icons (reusing the MediaStorage upload
  path). No generic placeholders remain.

---

## Marketing homepage hero — showcase image (BUILD-13, corrected target) — 2026-08-03

### Done
- The earlier "Build 13" draft targeted the logged-in dashboard (see the section
  below — still valid and shipped). This corrected build targets the **logged-out
  marketing hero** (`marketing/home/hero.blade.php`).
- New **`showcase_image`** field on the `home.hero` CMS section — independent of
  the existing full-bleed `image` backdrop. Renders as a real, centered `<img>`
  (capped `max-h`, `data-reveal` at `.20s`) directly below the subheadline and
  above the CTA row; optional, and all four backdrop/showcase combinations render
  cleanly. It plugs into the existing `*_image` upload path in the Site editor
  (auto-surfaced control + a clarifying "shown below the description, not as a
  background" hint), routed through `MediaStorage` (so BUILD-11 compression
  applies).
- **CTA row locked to two columns** (`grid grid-cols-2`, `max-w-md`) so it can
  never stack; compact `!px-4 !text-sm` on mobile → `sm:!px-8 !text-base`, so both
  buttons fit at 375px without overflow.

### Dashboard hero — admin on/off toggle (owner request) — Done
- `HeroBackground` gains an `enabled` flag (`dashboard.hero.enabled`, default ON
  so existing installs are unchanged) + `enabled()` / `showsOnDashboard()`. The
  dashboard image now shows only when an image is uploaded AND the switch is on;
  the title/description/buttons are untouched. A "Show hero image on dashboard"
  switch sits in Admin → Branding beside the hero uploader — turning it off hides
  the image WITHOUT deleting the uploaded art (cache bumped to `v3`).

---

## Dashboard home hero (BUILD-13) — 2026-08-03

### Done
- **Real visible image, not a faded background.** The customer dashboard home
  (`livewire/dashboard.blade.php`) now renders `HeroBackground` art as a genuine
  `<img>` in normal flow — a fixed **2:1** band with a `max-h` cap — directly
  under the "My Connectivity" title, instead of the old `absolute inset-0`
  gradient-faded layer behind the greeting. The greeting bubble kept its own
  logic but dropped that background art so the image never renders twice.
- **New admin-editable description line** under the title, extending
  `HeroBackground` (new `DESC_KEY` + `description()`, `DEFAULT_DESCRIPTION` =
  "Your eSIMs, numbers, and wallet — all in one place."). Never empty — a blank
  admin value falls back to the default. Edited on Admin → Branding, saved as a
  `brand`-group Setting, cache-busted on save (hero cache bumped to `v2`).
- **Strict two-column CTAs.** `Buy eSIM` / `Get number` moved directly below the
  image into `grid grid-cols-2` — side by side at EVERY width, never collapsing
  to one column.
- **Viewport-fit + clean degradation.** Title/description one line each, image
  capped to a short band, compact buttons → the whole block fits a 375×667 phone
  on first paint. With no image set it degrades to title + description + the same
  two-column buttons, no empty gap. Admin preview switched to a 2:1 block with a
  "1600×800px recommended, will crop to fit" hint so it matches what renders.

---

## Media pipeline build (BUILD-11) — 2026-08-03

### §2 Browser-side compression — Done
- **One document-level interceptor, every upload surface** (`resources/js/
  image-compress.js`). A capture-phase `change` listener preempts Livewire's own
  handler on any file `<input>`, resizes the image on-device (longest edge →
  2000px) and re-encodes at the SAME mime type/filename, then hands the smaller
  files to Livewire via a fresh `change` event. Because the hook is global, it
  covers every `WithFileUploads` surface with no per-form wiring — nothing can be
  missed the way editing 30 templates could.
- **Bandwidth optimisation only, not authoritative.** Keeps the original mime so
  server validation and the §3 WebP pass are unaffected; only replaces the file
  when the re-encode is actually smaller.
- **Graceful degradation (§2.3).** If the browser lacks `createImageBitmap`,
  `canvas.toBlob`, or `DataTransfer` (old browsers / in-app webviews) the listener
  doesn't intercept at all — Livewire uploads the original and the server-side
  pass is the safety net. SVG/GIF/non-images pass straight through.

### §3 Server-side WebP compression — Done
- **`CompressImageJob` (queued, never inline).** `MediaStorage::storePublic()`
  stores the original and returns its URL immediately, then dispatches the job —
  zero added latency on the upload response. The job converts to WebP with raw
  **GD** (`imagewebp`), no new Composer dependency: GD-with-WebP is universal on
  cPanel, and shelling out to a binary (which the spec warned against) is avoided.
- **~80KB target, quality floor 40.** Steps WebP quality 82 → 40; stops the
  moment it lands at/under 80KB, and accepts a slightly larger file at the floor
  rather than compressing a complex image into mush. Resizes to the upload
  context's real display cap FIRST (avatars/logos 512px, brand/splash 1024px,
  heroes/banners/covers 1600px) — no 4000px avatars.
- **Overwrites in place → the saved URL stays valid.** WebP bytes replace the
  same path, so none of the ~30 `storePublic` callers that persist the URL need
  to change. Cloud disks get an explicit `image/webp` content-type; on the local
  disk `<img>` decodes by bytes, so the `.png`/`.jpg` extension is harmless. Only
  overwrites when the WebP is actually smaller — a rare image WebP can't beat
  keeps its original.
- **Failure-safe (§3.4).** No GD/WebP, an unreadable file, or a decode error →
  the job logs and returns, leaving the original upload exactly as it was. Proven
  by a test that feeds it non-image bytes and asserts they're untouched.
- **KYC/quality-sensitive contexts excluded** (`documents`/`identity`/`kyc`/
  `exports`) and **GIFs skipped** (GD would flatten animation). KYC docs don't
  currently flow through `storePublic` at all — the exclusion is belt-and-braces.
- Applies uniformly to every existing image upload surface (heroes, banners,
  avatars, gift-card logos, blog covers, chat/MMS attachments, …) because the
  hook lives in `storePublic`, not in each caller.

### §4 Cloudflare R2 as a third storage option — Done
- **R2 disk added** (`config/filesystems.php`) — same `s3` driver, `region: auto`,
  endpoint `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`. The bucket's S3
  endpoint needs auth, so a separate public URL (`R2_PUBLIC_URL` → disk `url`)
  is what SERVES files; R2 can hold PRIVATE files (signed URLs) with no public
  URL at all.
- **Runtime disk resolution** (`MediaStorage::resolveDisk()`) — one code path on
  cPanel and VPS, nothing environment-specific. Priority is **R2 → Wasabi →
  server** ("auto"), or an explicit admin choice via the `media.primary_disk`
  Setting. A public context requires R2's public URL before R2 can win; a chosen
  store that isn't actually configured safely falls through to auto, so uploads
  can never break. `disk()` (public) and `privateDisk()` both route through it.
- **Admin, no file editing** — R2 credentials are pasted in Admin → API keys →
  *Media storage* (encrypted at rest, overlaid onto `filesystems.disks.r2.*` at
  boot via `ProviderKeys::applyToConfig()`), and a *Primary media store* chooser
  writes the `media.primary_disk` Setting. The panel shows what media actually
  resolves to right now ("Serving from: …").
- Tests: `MediaStorageTest` (R2 wins by default, admin can pin Wasabi, R2 with no
  public URL serves private-only) + `ProviderKeysTest` (pasted R2 creds configure
  the disk without echoing the secret; primary-store pin is audited).

---

## Payments build (BUILD-2) — 2026-08-02

### Done
- **Crypto signature verification, doc-verified + fixed.** Confirmed each
  provider's current scheme against live docs. Found + fixed a real money bug:
  **Cryptomus** was signing with `JSON_UNESCAPED_SLASHES`, but the canonical is
  slash-escaped — this broke payment creation (the `sign` we send includes
  `url_callback`/`url_return`) and webhook verification for any payload with a
  `/`. Fixed to the documented flags; verification now also accepts the
  unescaped variant defensively. **NOWPayments** verification made tolerant to
  the same slash-escaping nuance. **CoinPayments** HMACs the raw body — correct
  as-is. Regression test: a slash-containing Cryptomus webhook now credits.
- **Paystack credit proven end-to-end against the real `database` queue** —
  queued (not inline), drains to a single credit + ledger row, idempotent on a
  retried delivery.
- **Global sandbox/test-mode indicator** — `PaymentSandbox` detects test keys
  (Stripe/Paystack `sk_test_`, Flutterwave `FLWSECK_TEST-`, PayPal/NOWPayments
  sandbox host); admin-dashboard banner + honest per-gateway checkout tag; never
  falsely flags a gateway it can't tell.
- **`docs/PAYMENT-GATEWAYS.md`** — per-gateway base URLs, signature methods,
  inline-vs-redirect state, refund/dispute status, and the Paystack sandbox test
  steps. All nine gateways confirmed wired to the unified webhook controller.

### The reported "top-up didn't credit" (SEV-1) — root cause is operational
The credit path is proven correct in code. The live failure is therefore
configuration: **(a)** the Paystack **webhook URL must be registered** on the
Paystack dashboard (`/webhooks/payments/paystack`) — no registered webhook means
no credit; and **(b)** the **queue worker/cron must run** on the cPanel host. The
admin dashboard's env-guard banner already warns if the queue is misconfigured.
Frank: verify both on the live host.

### Refunds & disputes — built 2026-08-02 (BUILD-2 §7)
- **Admin-triggered refunds** (Admin → Payments → Refunds & Disputes):
  `RefundService` is provider-first, ledger-reversing, idempotent, and refuses to
  refund already-spent funds (no silent loss). `RefundableGateway` contract wired
  for **Paystack**; the other card gateways drop in by implementing it (Stripe,
  Flutterwave, PayPal endpoints noted in docs/PAYMENT-GATEWAYS.md). Crypto →
  manual task + alert.
- **Dispute/chargeback handling**: `DisputeService` freezes the disputed amount
  from the wallet on open (reserve earmark → unspendable + unwithdrawable),
  releases on win, releases + debits on loss; idempotent. Wired for **Paystack**
  via the shared signed webhook; `DisputeAwareGateway::parseDispute` is the seam
  for Stripe/Flutterwave/PayPal (payloads differ — verify each before wiring).
  The USD reserve is the only wallet earmark, so non-USD disputes are recorded +
  alerted for manual handling.

### Card-gateway refunds/disputes extended — 2026-08-02
- **Refunds** now wired for **Stripe** (`/v1/refunds` on the payment_intent),
  **Flutterwave** (`/v3/transactions/{id}/refund`), and **PayPal**
  (`/v2/payments/captures/{id}/refund`) — alongside Paystack. Each needs the
  provider's own charge id, so it's captured at webhook time into a new
  `payment_charges` table and handed to the refund call.
- **Disputes** now wired for **Stripe** (`charge.dispute.created/closed`) and
  **PayPal** (`CUSTOMER.DISPUTE.CREATED/RESOLVED`), which cite the provider charge
  id — mapped back to our reference + user via `payment_charges`. Freeze/claw-back
  reuses the same money-safe `DisputeService`.

### Still flagged (the remaining payments follow-up — money-moving, do with care)
- **Flutterwave chargeback webhooks** — an on-request, variable-payload feature;
  the `DisputeAwareGateway` seam is ready but the payload must be verified against
  a real event before wiring it onto the fund-freezing path.
- **Per-gateway admin schema (§3) — DONE 2026-08-02.** Admin → Payments →
  Gateways: sandbox/live mode toggle (swaps the base URL for PayPal/NOWPayments;
  informational + key-prefix detection for the rest), read-only webhook + callback
  URLs with copy buttons, and a live test-connection ping per gateway. Keys stay
  on Provider Keys (not duplicated). Remaining nicety (not built): storing
  separate sandbox AND live key sets simultaneously so the toggle swaps keys too —
  today the operator pastes the matching key. `PaymentGatewayConfig::applyToConfig`
  swaps hosts at boot.
- **Inline/embedded checkout** where supported (Paystack Inline, Stripe Payment
  Element, Flutterwave modal). Everything is redirect/hosted today.
These are grouped so they get one careful, reviewed pass rather than being rushed
onto a live money path.

---

## Foundation build + provider-timeout hotfix — 2026-08-02

### Done
- **Provider HTTP timeouts (root cause of the "whole app froze" reports).** All
  13 provider integrations now set `connectTimeout(3)` + `timeout(8)` on their
  client builder (purchase confirmations `timeout(15)`); Twilio/Telnyx keep
  their longer voice/permanent ceilings but gained `connectTimeout(3)`. A hung
  provider now fails over within ~11s instead of pinning a worker for 30–60s+.
  Proven by a test that injects a `ConnectionException` and asserts lane failover.
- **Session-lock contention.** `SESSION_DRIVER=database` (documented WHY `file`
  is forbidden: its per-request lock lets one slow request freeze a user's whole
  session). Provider timeouts cap how long the lock can be held at all.
- **Wasabi-safe uploads sitewide.** Livewire's temp-upload disk is pinned at boot
  to `MediaStorage::disk()` (Wasabi-or-local), so uploads work with zero Wasabi
  keys and upgrade automatically once keys are set. `.env.example`
  `FILESYSTEM_DISK` default changed `wasabi → public`.
- **Queue.** `QUEUE_CONNECTION=database` by default; verified `queue:work
  --stop-when-empty` drains cleanly. A production `sync`/debug-on misconfig is
  now flagged (see EnvironmentGuard).
- **Brand error pages** 404/419/500/503 — one shared shell, zero technical leak.
- **Migration collision + installer drift** — fixed earlier the same day (see the
  next section); the duplicate-timestamp guard lives in
  `bin/check-install-integrity.php` (a CI gate).
- **Admin never locked out of login** — super_admin/admin get 200/min, everyone
  else 5/min, off the same role source of truth.
- **cPanel installer footguns documented** (username truncation; non-transactional
  DDL).

### Flagged but not yet built (BUILD-1 §3 admin-auth — remaining, needs a focused pass)
These are the heavier/riskier admin-hardening items from BUILD-1 §3. They are
deliberately **not** rushed in alongside the foundation work because several can
lock the owner out or break the production build if done carelessly, and they
deserve their own reviewed pass:
- **`SecurityLog` table + auto-temp-ban** on repeated failed logins/403s/429s
  (§3.7). Admin accounts must be ban-exempt — get this wrong and you lock out the
  owner, so it needs care + tests.
- **`anti-inspect.js`** devtools/context-menu blocking that **skips admins**, with
  `window.isAdmin` exposed to JS (§3.4).
- **`StripSecrets` middleware** for `_secret`-prefixed Livewire props + signed
  routes for sensitive admin actions (§3.6).
- **Vite production obfuscation** via `rollup-plugin-obfuscator` (§3.5) — build-
  time; verify it doesn't break the bundle before committing.
- **`X-Frame-Options`** is currently `SAMEORIGIN` (blocks cross-origin clickjacking
  already); BUILD-1 §3.2 requests `DENY`. Confirm nothing uses same-origin iframes
  before tightening.
- **SecurityHeaders / rate limiting already exist** (CSP, HSTS, nosniff, frame-
  options; `api`/`orders`/`admin` limiters) — extend, don't duplicate.

### Needs Frank's input / can't be done from here
- **Live `.env` audit (BUILD-1 §2.3).** I can't read the deployed server's `.env`
  from this environment. Frank: confirm the LIVE `.env` has `QUEUE_CONNECTION=`
  `database` (or `redis`) and `APP_DEBUG=false`. If the queue was on `sync`, that
  is a likely contributor to any past "payment didn't credit" symptom. The admin
  dashboard now shows a banner if either is misconfigured in production.
- **SupportChat voice-recording upload (BUILD-1 §4.5).** When that feature is
  built, re-verify its upload against the Wasabi-safe temp-disk fix above.

### Admin-configurable settings map (this batch)
- **Production misconfig banner** — automatic, no setting: shows on the admin
  dashboard (Overview) whenever `QUEUE_CONNECTION=sync` or `APP_DEBUG=true` in a
  production environment. Also logged at boot.

### Standing rule (do not regress)
- **Every provider integration MUST set an HTTP timeout from day one** —
  `connectTimeout(3)` + a bounded `timeout()` on its client builder, purchase
  calls no more than ~15s. No `Http::` call to an external provider may ever be
  unbounded. A timed-out provider must fail over (be caught), never bubble up and
  freeze the request.

---

## 2026-08-02 — Installer drift: four install-critical files re-synced with the known-working package

### What was wrong
A file-by-file diff between the GitHub repo and a known-working 28 MB installable
package (1,645 files compared) found four concrete, install-blocking differences.
Every one of them broke a **fresh** install while leaving the running dev/test
environment perfectly healthy — which is exactly why they went unnoticed.

### The four fixes (each its own commit)

1. **Migration timestamp collision.** `create_partners_table` and
   `create_partner_earnings_table` both sat at `2026_07_27_130955`.
   `partner_earnings` has a foreign key into `partners`; with equal timestamps
   the order falls back to alphabetical, which runs `partner_earnings` **first**
   → hard foreign-key failure on the first `php artisan migrate`. Fixed by
   re-timestamping the earnings migration to `2026_07_27_130956` (contents
   unchanged). The unrelated `2026_07_12_141334` pair (two-factor columns +
   personal access tokens) is order-independent with no cross-FK and is left as
   the one allowed collision.

2. **Missing DB-free install layout.** The four installer steps had been switched
   to `<x-layouts.app>` and the dedicated `<x-layouts.install>` layout was gone.
   `x-layouts.app` renders the splash screen, brand preloader, toast stack, PWA
   manifest link and tracking pixels — all backed by the `settings` table, which
   does not exist during the pre-migration install steps. (Every lookup is
   try/catch-guarded, so it degrades rather than 500-ing, but it fires failed
   queries and paints the wizard with post-install chrome.) Restored the minimal
   `resources/views/components/layouts/install.blade.php` and repointed the four
   views to it — they now match the known-working package byte-for-byte.

3. **Missing `config/view.php`.** Without it Laravel uses the framework default,
   whose compiled path is `realpath(storage_path('framework/views'))`.
   `realpath()` returns `false` when that directory doesn't exist yet — the state
   of a fresh clone/export — leaving Blade with no valid cache path and failing
   **every** render with "Please provide a valid cache path". Restored
   `config/view.php`, which uses `storage_path(...)` **without** `realpath()`, so
   the path resolves before the directory exists. `VIEW_COMPILED_PATH` stays
   env-overridable for locked-down cPanel hosts.

4. **Runtime directory placeholders — already covered on GitHub.** The reference
   package ships `.keep` files in `bootstrap/cache` and `storage/framework/*`.
   The GitHub repo already preserves every one of those directories with tracked
   **`.gitignore`** files (the standard Laravel convention), verified by a real
   fresh clone — so they are *not* missing and no `.keep` files were added
   (redundant). `public/storage` is intentionally **not** committed: it is the
   symlink created by `php artisan storage:link` at install, and materialising it
   as a real directory would break that symlink and stop uploaded media from
   being served. The reference's committed `public/storage/.gitignore` is a
   build-time artifact, not a source-repo file (the root `.gitignore` ignores
   `/public/storage` in both repos).

### How it was verified
- Fresh `git clone` of the patched repo: all six runtime dirs present,
  `public/storage` correctly absent, `config/view.php` + install layout present,
  partner migrations correctly ordered.
- Fresh `migrate` on a clean database: 92 migrations, 0 failures, `partners`
  before `partner_earnings`.
- `php artisan view:cache` compiles every Blade view (the install layout
  resolves), and the install `welcome`/`setup` views render through the DB-free
  layout with **no** splash, preloader, manifest or toast markup.

### Why it happened, and what stops it recurring
Install-critical files can drift out of the repo without breaking day-to-day
development, so nothing surfaces the loss until someone runs a true fresh install.
Two guards now close that gap:

- **`bin/check-install-integrity.php`** — a pure-filesystem check (wired into CI
  as the *Install integrity* job in `.github/workflows/tests.yml`) that fails if
  any of these files is missing, if an install view stops using
  `x-layouts.install`, or if a new migration timestamp collision appears.
- **`scripts/package-release.sh`** — a first-party, repeatable packager that
  builds a cPanel-ready ZIP from a clean checkout (integrity check → `composer
  install --no-dev` → `npm ci && npm run build` → ensure runtime dirs → zip), so
  a working installable package can always be produced from GitHub without a
  third-party tool. Documented in `docs/CPANEL-INSTALL.md`.

---

## Operational hardening (BUILD-5)

### Provider health + alerting (§2, §3)
- **Health** covers every Active provider in both stacks (`App\Support\ProviderHealth`),
  not just the three wallet-funded ones. The balance probe doubles as a
  reachability check — a provider whose API throws shows as `down` and alerts.
  Admin dashboard "Provider health" widget renders it. Failover in
  `ProviderRouter` (eSIM) and `SmsNumberRouter` (numbers) is confirmed real
  (per-provider try/catch, continues down the chain/lane).
- **Alert delivery** (`AlertAdminJob`) now fans out to every admin via web push
  (instant) + email (guaranteed once a mailer is configured), throttled per
  alert code (10-min cooldown) so a flapping alert can't storm admins. The
  durable `error_logs` row is always written regardless.

### Recommended, not yet enforced — operational items for Frank
- **Sentry (or equivalent) in production.** `SENTRY_LARAVEL_DSN` exists in
  `.env.example` but is blank. Configure a real DSN in production so *uncaught*
  exceptions surface somewhere an admin sees — not only the specific conditions
  this codebase remembers to `AlertAdminJob`. (BUILD-5 §3.)
- **Backup test-restore.** `spatie/laravel-backup` is scheduled and working, but
  a backup nobody has ever restored from is unverified. Someone should perform
  one real test-restore and record the date here. (BUILD-5 §1.)

### Reconciliation, scaling, compliance (§4, §5, §6, §7)
- **Financial reconciliation** view at `/adminmaster/reconciliation`
  (`App\Support\FinancialReconciliation`): money in by gateway vs. wallet credits
  (with a flagged gap), payouts, provider costs, outstanding liabilities.
- **`docs/SCALING-TRIGGERS.md`** — concrete signals for moving to VPS + Redis +
  Horizon (queue backlog, order latency, WhatsApp volume, …).
- **`docs/APP-STORE-PAYMENTS-COMPLIANCE.md`** — Apple 3.1.1 / 3.1.3(e) checked
  live (2026-08-04); per-product-line verdict; iOS-only structural fix
  (tie top-ups to a service purchase; route Wizard/merchant fees via IAP or hide).
  **Must be re-confirmed live before the first iOS submission.**
- **`docs/REGRESSION-SWEEP-LOG.md`** — automated baseline **1053 passing**;
  live-sandbox sweep checklist awaiting operator execution before go-live.

### Open operational items (not code — for Frank)
- [ ] Configure a production **Sentry DSN** (§3).
- [ ] Perform one real **backup test-restore** and log the date (§1).
- [ ] Run the first **live-sandbox regression sweep** and package the baseline
      installer ZIP afterwards (§7, §8).
- [ ] Re-confirm **Apple 3.1.1/3.1.3** wording and apply the iOS payment carve-out
      before submitting to the App Store (§6).

---

## BUILD-12 — homepage "Who Naara Is For" audience tabs
Registered `audiences` as a real, admin-orderable homepage section (SiteContent),
defaulted right after `products`. Six tabs auto-advance at a flat 12s with a
brand-gradient progress bar; manual selection overrides + restarts the timer;
pauses on hover/focus/touch and resumes where it left off; keyboard-navigable
(arrow keys, focus rings); honours `prefers-reduced-motion` (solid active
indicator, no auto-advance). Mobile uses a horizontally-scrollable tab strip.
Panel copy is the approved verbatim text; the six images are admin-swappable
`*_image` fields (SiteEditor inline upload → MediaStorage), shipped under
`public/images/audiences/`. **Spare image `naara-business-traveler.webp` is
shipped and available in admin for a future swap** (unused by the six panels).

---

## Aug-4 live-incident hotfix

### Unifying root cause (so it's never re-diagnosed from scratch)
Two symptoms — Paystack wallet not crediting after a live transaction, and the
provider-health widget empty in admin — both depend on the **live cPanel cron
(`* * * * * php artisan schedule:run`) actually firing and the queue draining**.
Both code paths are correct. If the cron is missing/stopped, both symptoms
appear together. **Admin → System Health** now shows each scheduled task's
last run + an overdue flag, the queue connection, and the backlog — check it
first; if a task is overdue the fix is the cPanel cron, not code.
Also operational: register the Paystack webhook URL
(`/webhooks/payments/paystack`) on the live Paystack dashboard (§3).

### Fixes shipped
- **§2** System Health panel (`SchedulerHealth` + `ScheduledTaskFinished` listener).
- **§4** Dual sandbox/live keys per card gateway (`GatewayCredentials`) — the
  Sandbox/Live toggle now selects the active set; legacy single key auto-migrates
  into the detected slot; **public_key** added for Paystack/Flutterwave/Stripe
  (config + admin) to unblock inline/embedded checkout. Additive — the old key
  path keeps working until the operator populates the new fields.
- **§5/§6/§7** Glass legibility without blur, removed animated backdrop-blur
  artifacts, marketing mobile menu now scrolls instead of clipping.
- **§8** Consistent brand-logo sizing via a named `size` prop.

---

## BUILD-7 + BUILD-9 + scheduler tuning — 2026-08-04

### Done — BUILD-7 (receipts, tax, merchant analytics, sub-referral)
- **§1 Purchase receipts.** `PurchaseReceiptNotification` (email + in-app) fires on
  eSIM/number/permanent-line orders (best-effort, queued — never blocks the money
  path). Customer **Receipts** tab reads the authoritative `wallet_transactions`
  ledger, so the wizard fee shows as its own line; resend-to-email supported.
- **§2 Tax/VAT support layer — shipped OFF.** `TaxService::taxFor()` returns 0
  unless an admin sets a per-country rate (Admin → Tax rates / `TaxRates`). Tax is
  additive, fully separate from `PricingEngine`'s cost+profit math. **Open business
  decision (not a code task):** which countries to enable tax for, at what rate.
- **§3 Merchant earnings analytics.** Reporting-only page over `MerchantEarning`
  (time-series, product-line breakdown, top customers). No change to accrual.
- **§4 Merchant sub-referral.** One-time flat bonus, **single hop, not a downline**
  — a full multi-level/MLM structure was deliberately NOT built (regulatory shape).
  Reuses `Referral` with a `type` column; admin-set bonus, default 0 (off).

### Done — BUILD-9 (self-service brand directory subscriptions)
- Extends BUILD-6's `brand_partners`/`brand_partner_handles` (no second brand
  table). New: plans, subscriptions, videos, video-watch claims, priority log.
- **Money:** first-month + monthly billing via `WalletService` (idempotent per
  month), mirroring the Naara Line renewal command; short wallet **pauses** (never
  cancels) + reminds + auto-resumes. Daily **100-credit cap** across follow +
  brand-follow + video (admin-configurable). Video credit is **server-confirmed**
  (≥60s of heartbeat-accumulated, seek-resistant, token-bound watch-time).
- **Priority engine:** under-delivered handles boost `priority_score` (auditable
  `brand_priority_log`), decaying on on-target months. Directory sorts admin-
  featured first, then priority, only `active` listings.
- **Taxonomy** (`BrandCategories`) is admin-extensible (Setting), not fixed in code.
- **Open product decision:** whether a `past_due` brand subscription should ever
  **hard-cancel** after a fixed grace period — currently an indefinite past_due +
  reminder loop (no cutoff invented), per BUILD-9 §5.2.5.

### Scheduler tuning
- Added `brand-subscriptions:bill` → `dailyAt('05:45')`, `withoutOverlapping()`,
  `runInBackground()` (05:45 is clear, just after the 05:30 merchant sweep). The
  command processes subscriptions in chunks so its runtime stays flat as
  subscriber count grows.
- **Finding:** the BUILD-4 WhatsApp Autopilot has **no scheduled artisan command**
  in this codebase (it's service/webhook-driven — `App\Services\WhatsApp\WhatsAppAutopilot`),
  so the hotfix's `autopilot:process` entry was intentionally NOT added (the hotfix
  said "confirm the real name, don't assume"). Only the brand-billing sweep was added.
- Reminder for future work: default any new recurring command to `runInBackground()`
  unless it specifically must run inline.

---

## BUILD-10 — Preloader Studio + premium header & storytelling sections

Two blueprints (`BLUEPRINT-preloader-studio.md`, `BLUEPRINT-batch1-sections-expansion.md`).

### Preloader Studio (brand-aware, per-page-type, admin-controlled)
- `App\Support\PreloaderSettings` resolves a preloader per **page type** on top of
  the single choke point (`components/layouts/app.blade.php` → `<x-brand-preloader>`).
  `pageType` threads through app → customer/admin/marketing/auth layouts; specific
  pages override (e.g. `GetNumber` sets `preloaderType=numbers`).
- **Zero regression:** until an admin saves an assignment, `forPageType()` returns
  today's behaviour (BrandSettings enabled + legacy style) bit-for-bit; corrupt/
  missing rows degrade to a hard safe default.
- **Portable:** built-in types are domain-agnostic; feature code registers its own
  (`PreloaderSettings::registerPageType('numbers', …)` in AppServiceProvider boot).
- 18 presets ported to `resources/css/preloaders.css` (compiled, never inline),
  every colour a brand-token CSS var (`rgb(var(--nx-pl-cN, var(--brand-*)))`) and
  every duration `calc(base / var(--nx-pl-speed))`; reduced-motion handled once.
- Admin **Preloader Studio** (`admin.preloader-studio`): live gallery, per-type
  assignment + inherit-from-default, brand/manual colours, size/speed/opacity/
  background/blur, per-preset loading text + neutral toggle, live preview. Debug
  `?preloader_preview=slug` (admin-gated).

### Header + storytelling sections
- **Glass header** (`.nx-header-fade`): soft gradient fade, no hard border,
  theme-aware; action order now **bell → theme toggle → hamburger**. The toggle is
  the class-scoped **sun/moon switch** (valid rendered twice; brand-tokenised, sun
  = brand gold), same Alpine contract (localStorage, `theme-changed`, aria).
- **`<x-storytelling-carousel>`** + **`<x-content-modal>`** (the ONE modal engine):
  fixed image crossfade, single text block that slides on gesture / fades on auto,
  per-slide read-time `max(4, ceil(words/3))`, sticky section nav, per-slide FAB
  modal. `resources/js/storytelling-carousel.js` = Alpine component + one shared
  `sectionNav` store (single IntersectionObserver). First slide is server-rendered
  (SEO / no-JS). Supports text-only + `tone="on-dark"`.
- **Product-line CMS** (`App\Support\ProductLineSettings`, admin `admin.product-lines`):
  six products (Data, Connect, Verify, Rent, Line, Gift) with a 3-beat modal arc;
  homepage products section now renders them through the carousel.
- **About** Vision/Mission/Essence uses the same carousel (verbatim SiteContent copy).
- **§6 nav coordination:** the global bottom nav (app-shell) is gated by
  `globalVisible()` — unchanged on pages without a carousel; hidden while a section
  is in view and revealed near a footer sentinel on pages with one.
- **§7:** `storytelling` registered as a Section Builder block (`SectionLibrary`)
  with a `slides` repeater + `_editor-storytelling`, droppable into any custom page.

### Open owner decisions (flagged, not invented)
- **Naara Connect / Naara Rent** marketing copy ships as **draft** (`is_draft`,
  badged in the Product-lines admin) pending owner approval.
- **Naara Rent rental-duration terms** are deliberately left unstated in the draft
  until confirmed.
- The flagged **sun-colour** decision was resolved to the brand's own warm gold
  (`--brand-accent`); change in Branding if a dedicated token is later preferred.

---

## BUILD-11 — Ambient gradient + email verification (403 / soft-gate)

### Marketing ambient gradient (BLUEPRINT-ambient-gradient)
- **Card border (§2):** `.nx-card::before` was `opacity:0` / hover-only — never seen
  on touch (the primary device). Now an ambient floor (`0.35`) by default, full on
  `:hover`/`:focus-within`. Global to every `.nx-card`, both themes.
- **Light hero (§1):** `.nx-sechero.is-light` was a flat `#F6F8FA`. Added soft
  low-opacity brand blobs on the near-white base (bright-tuned, not a copy of the
  dark opacities).
- **Reusable (§1/§3):** `.nx-ambient` utility + `<x-ambient-glow>` wrapper (light +
  dark, brand tokens, reduced-motion). Applied to the homepage product-lines section.
- Opacity values are the blueprint's starting points, pending an eyeball pass on
  real imagery; the structural fix (floor ≠ 0, light gets the mechanism) is shipped.

### Email verification (NAARA-BUILD-20 §1–2)
- **403 fix (§1):** `trustProxies(at: '*')` + production `URL::forceScheme('https')`
  — the shared-cPanel signed-URL scheme mismatch. Live `.env` APP_URL is an operator
  step to confirm (not changed from here).
- **Soft gate (§2):** `MailSettings::verificationMode()` (off | soft | **soft-default**
  | hard). `EnsureVerifiedWhenMailConfigured` only blocks in `hard`; soft nudges with
  a dismissible banner and never blocks a purchase. Three-way choice in Admin → Email.
- **⚠ Behaviour change to flag for Frank:** existing installs now default to `soft`
  (was hard-block once mail configured). Set to `hard` in Admin → Email to restore.

### Still outstanding from this batch (need scope/infra decisions)
- **Email Studio (NAARA-BUILD-20 §3):** template editor (per-notification override
  wiring across ~12 notifications) + broadcast/campaign engine (audience segments,
  batched queue, send history). Large; not started.
- **Laravel Production-Readiness blueprint:** 14 domains. Several are pure-code and
  partly already present (Domain 13 webhook HMAC + idempotency — verify() gaps;
  Domain 10 CI/branch-protection — PR flow already used). Most (edge WAF, DB
  replicas, session replay, Capacitor mobile security, error budgets) are
  infrastructure/ops decisions requiring Frank's environment choices. Its two
  governing rules (confirm-before-build; admin-configurable + self-test) were
  applied to everything built this session.

---

## BUILD-12 — Email Studio + Laravel readiness (pure-code)

### Email Studio (NAARA-BUILD-20 §3)
- **Template editor** (`admin.email-studio`): per-template DB overrides
  (`mail_template_overrides`) for subject / intro / button text / accent, plus a
  `global` accent — Blade files stay the real default; `MailTemplates` merges
  overrides with full fallback. Live preview renders the real email with sample
  data + unsaved edits. Wired into the 6 core notifications + their blades + the
  shared mail layout/button.
- **Broadcast** (`admin.email-broadcast`): compose + audience (all / role /
  account type / segment, reusing existing role/merchant/partner scopes) + real
  recipient count + confirm step; `SendEmailBroadcastJob` chunks recipients and
  each per-user mail is queued (tries=1, never a synchronous blast). History in
  `email_broadcasts` + Auditor log.

### Laravel readiness (pure-code, high-value only)
- Full 14-domain audit in `docs/laravel-readiness-audit.md` (Rule 1). Finding:
  the high-value pure-code controls (auth rate limiting; webhook HMAC +
  per-handler idempotency) are ALREADY present; most remaining domains are infra
  decisions (Cloudflare edge, replicas, Sentry/session-replay, Capacitor).
- **Built (Domain 13/14):** inbound **webhook delivery log** — `webhook_deliveries`
  + a path-scoped `LogWebhookDelivery` middleware recording every `webhooks/*` hit
  AFTER the response (never alters handler logic), surfaced in System Health. This
  directly answers the recurring "is Paystack's webhook reaching us?" question.
- The `platform_capabilities` + verify() framework was intentionally NOT built —
  it would add ceremony over money paths without new protection. See the audit doc
  for the recommended infra actions.

### Component library (batch 2) — reusable UI
- **Button variants** (`ui-elements.css`, shown in the admin UI-kit): `nx-btn--glow`
  (conic border sheen), `nx-btn--get-started` (tilted plate + slide-in arrow),
  `nx-btn--premium` (on-brand teal↔gold shimmer — NOT the source rainbow, which
  would break the brand palette), `nx-btn--pill-reveal`, `nx-btn--danger-confirm`
  (heavier ring for irreversible actions, pairs with wire:confirm),
  `nx-btn--edit-reveal` + new `x-ui.icon-button`. All dark + reduced-motion aware.
  The emoji-morph source pick was deliberately NOT ported (emoji-free rule).
- **Share button** (`x-share-button`): data-driven from admin `SocialLinks` — a
  network shows only when linked AND it supports a web share-intent (X, Facebook,
  WhatsApp, LinkedIn). Native `navigator.share` first on mobile; Copy-link always.
- **Post reactions** (polymorphic `reactions` table + `Reaction` + `HasReactions`
  trait + `PostReactions` Livewire): one reaction per user per subject, toggle
  semantics, SVG glyphs (never emoji), wired into the blog post page.
- **Skipped as already-built (Rule 1):** social-login buttons (`components/auth/
  social-buttons`), the theme toggle switch (batch 1). BUILD-3 (chat/mobile/
  branding) re-confirmed already built — global-sidebar, nia-glow, NotificationCenter,
  support-voice MediaRecorder all present — so skipped.

### App Studio (NAARA-BUILD-21) — full native config surface
- Grounded on a REAL Median-generated Android/iOS export of this platform (the
  operator's uploaded `appConfig.json`), so `AppStudio::medianConfig()` emits the
  genuine Median shape (general / navigation / styling / permissions / services /
  security) — a generated app is Median-compatible, not an invented schema.
- **`AppStudio`** support class owns the surface, every field auto-populated from
  real platform data: initial URL + display name (brand), package/bundle IDs
  (derived, set-once), offline page (branded default HTML, timeout, custom editor
  with upload/URL/reset), link-handling ordered rules (own domain → internal,
  socials → app browser, catch-all → external), sidebar menu (auto from real
  legal/nav routes), permission usage-descriptions (real Naara reasons, never
  blank), security (disallow insecure http, bridge domain-restriction). Push
  **reuses** the existing provider and flags the Firebase gap rather than silently
  adding OneSignal.
- **Admin UI**: one App Studio section inside the existing App Builder page, with
  a live phone-frame offline-page preview; "Generate a build" works with zero
  fields touched. The resolved `medianConfig()` + offline HTML now ride the CI
  build payload (`TriggerAppBuildJob`).

### Hosting-portability hotfix (TrustProxies / Storage audit / Cloudflare)
- **§1 TrustProxies + forceScheme:** confirmed ALREADY present (`bootstrap/app.php`
  `trustProxies(at: '*')`, `AppServiceProvider` prod `URL::forceScheme('https')`).
  No-op — the email-403 pass already closed this.
- **§2 Storage disk-resolution audit:** every user-upload path (KYC export, voice
  notes, chat attachments, data export) already routes through
  `MediaStorage::privateDisk()/disk()`; `CompressImageJob` compresses on the exact
  MediaStorage-resolved disk passed at dispatch. The only raw named-disk usage is
  the backup subsystem (`BackupManager`/`RestoreService`), which must target its
  dedicated backup destination — documented inline as the deliberate exception.
- **§3 Cloudflare runbook:** new `docs/CLOUDFLARE-SETUP.md` (cross-linked from
  `CPANEL-INSTALL.md`) — webhook-source-IP allowlisting (linked live per gateway,
  never hardcoded), `/webhooks/*` excluded from Bot Fight Mode, cache-bypass for
  `/admin/*` `/api/*` `/webhooks/*`, and real-test-webhook verification via the
  System Health delivery log.

### Unified payout system (NAARA-BUILD-22)
- **§1 Referral margin-share:** see the referral-earnings entry above.
- **§5 Payout gateways (2 → 4):** added `PayPalPayoutGateway` (PayPal Payouts —
  self-service email payout for internationally-earning users) and
  `CryptomusPayoutGateway` (single signed-endpoint crypto payout rail; chosen over
  NOWPayments, whose payout API needs a JWT + interactive 2FA handshake). Both
  implement the existing `PayoutGatewayInterface`, are registered in
  `PayoutService`, and are allow-listed in `PayoutWebhookController`. New config:
  `services.cryptomus.payout_api_key` (separate from the collection key).
  - **Collection-only gateways (documented gap):** Stripe, Binance Pay,
    NOWPayments, CoinPayments, and Payssion remain **collection-only** — their
    payout/withdrawal APIs are meaningfully heavier to integrate (Stripe Connect
    onboarding; NOWPayments JWT+2FA; per-vendor payout KYC), so they are
    intentionally deferred. Paystack + Flutterwave (banks/mobile-money, Africa),
    PayPal (global email), and Cryptomus (crypto) cover the real payout lanes today.

- **§2/§3/§4/§6 Unified withdrawal + threshold + shared dashboard + auto run:**
  - Merchant withdrawal (`MerchantWithdrawalService`) already existed; referral
    cash-out added (`ReferralWithdrawalService` + `ReturnReferralEarnings` on
    PayoutReversed) — both on the same PayoutService engine as partners.
  - **Free-payout threshold (§3):** `payouts.free_payout_count` (default 5) +
    `PayoutThreshold` — a per-user, all-earner-type-combined count of settled
    PayoutRequests. First N payouts KYC-free; then KYC-L2. Balance/history always
    visible. Enforced in the merchant + referral withdrawal services and the
    auto run. (The legacy NaaraCredit cash-out page keeps its own L2 gate,
    unchanged per §1.3.)
  - **Shared dashboard (§4):** one `PayoutDashboard` Livewire (`earner-type`
    partner|merchant|referral), embedded on the referrals page; status-focused
    (balance, history, recent payouts, positive-framed KYC prompt). No bespoke
    per-type UI.
  - **Automatic recurring run (§6, Frank's choice):** `payouts:earnings-run`
    (daily 04:45, staggered) pays eligible merchant + referral balances; an
    earner past the free threshold without KYC-L2 is skipped (dashboard prompts),
    never force-paid. Idempotent via holds + unique references.
  - **Payout-account creation KYC decoupling (follow-up):** adding a payout
    account still flows through the legacy kyc:2-gated `/rewards/withdraw` route;
    a fully KYC-free first-payout also needs that account-add path relaxed — noted
    as a deliberate follow-up so the threshold governs the withdrawal act today
    without destabilising the existing verified-account model.

### Staff profit-share compensation (NAARA-BUILD-23)
- Shares from the SAME `PlatformProfitService` figure partners share in — no second
  profit calculation. `staff_compensation_profiles` (rate + is_active +
  effective_from) + `staff_earnings` ledger + `StaffEarningsService` (same accrual
  pattern as partners: per-user lock, idempotent reference, balance_after).
- **Monthly close (§3):** `staff:compensation-close` (1st of month, 03:15) computes
  the prior month's profit ONCE and applies each active staff member's % to that
  single figure; idempotent per profile+period; floors each share at zero on a
  negative-profit month; only a fully-closed month is paid. `effective_from` makes
  a rate change non-retroactive.
- **Admin (§2):** compensation section in Admin → Staff — set %, pause/activate,
  a **combined partner+staff %-of-profit banner that warns over 100%** (and blocks
  a save that would exceed it), and a **live month-to-date projected estimate** per
  staff member, clearly labelled "estimate … not yet payable".
- **Withdrawal (§4):** staff cash out through the SAME shared `PayoutDashboard`
  (`earner-type="staff"`, `/staff/earnings`), on the same engine
  (`StaffWithdrawalService` + `ReturnStaffEarnings` reversal listener). Staff are
  **exempt from the free-payout/KYC threshold** (Frank's decision — already vetted
  at account creation). Included in the automatic `payouts:earnings-run`.

### Wizard picker refresh (wizard+preloader blueprint)
- **§1 (the bug) fixed:** the wizard's country/service step containers had no
  wire:key, so Livewire's DOM-morph could reuse the old Alpine node on a
  forward→back→forward and retain a stale `cq`/`sq` search string that hid every
  item. Added a per-step visit counter (`stepVisits`), bumped on each genuine step
  ENTRY (detected via `lastStep` in render()), which keys each container so Alpine
  re-initialises cleanly. Added visible refresh buttons (`refreshCountries`/
  `refreshServices`) as a manual escape hatch. Tested (WizardPickerRefreshTest).
- **§2 preloader customization:** SKIPPED as already built — the completed
  "Preloader Studio" (`PreloaderSettings` + `PreloaderStudio`, per-page-type
  presets + overrides) already supersedes this blueprint's bg/opacity/blur/speed/
  route-override asks with a richer system.
- **§3 catalogue speed:** the country/service lists are already `Cache::
  rememberForever` and recomputed each render, so they're warm by the time the
  step shows (no separate prefetch needed). The remaining lever — a short timeout
  on `chooseService`'s live provider quote so a slow provider degrades gracefully
  — is a provider-layer change flagged as a follow-up (out of scope for this UI pass).

### Margin-safe discount floor (margin-safe-discount-floor blueprint)
- **The floor is a percentage of margin, not a flat $0.50.** New
  `App\Services\Pricing\DiscountMarginGuard::floor($cost, $adminMargin,
  $merchantMargin?, $absoluteMinProfit?)` returns the single lowest price a
  discount may reach: `max(absoluteFloor, pctFloor)`. Non-merchant lane keeps at
  least `capPct%` of the admin margin (`cost + adminMargin*(1 - capPct/100)`,
  cap default 30%); merchant lane protects BOTH sides
  (`cost + adminMargin*(1 - adminPct/100) + merchantMargin*(1 - merchantPct/100)`,
  defaults 20% / 10%). The absolute floor (`cost + pricing.minimum_profit_usd`,
  or `pricing.sms_min_profit` for numbers) is the backstop for thin-margin items.
- **One floor, computed once, shared by both engines.** `CouponEngine::price()`
  and `CreditService::quoteRedemption()` both take optional `adminMargin` /
  `merchantMargin` and defer to `DiscountMarginGuard`. Checkout computes the
  margins ONCE (`adminMargin = plainRetail − cost`; `merchantMargin =
  retail − plainRetail` on the merchant lane) and threads them into both, so a
  combined coupon **and** credit can never dig past the floor together.
- **Coupon XOR NaaraCredits.** Mutual exclusivity is enforced server-side in
  Checkout (`applyCoupon` clears `useCredits`; `updatedUseCredits` clears the
  coupon; a belt-and-braces guard blocks a request carrying both). No order can
  stack the two discount rails.
- **Merchant-zeroing bug fixed at the source.** Because the merchant-lane floor
  guarantees `walletCharge > plainRetail`, the merchant accrual is always
  non-zero — proven by a named test. New settings
  `pricing.discount_margin_cap_pct` / `discount_margin_merchant_admin_pct` /
  `discount_margin_merchant_pct` are editable in Admin → Pricing. Tested
  (DiscountMarginFloorTest + updated CreditRedemption/CouponsAndBanners expectations).

### Numbers section overhaul (numbers-section-overhaul blueprint)
- **§1 Conversation inbox.** Inbound SMS now has a home. A token-verified,
  idempotent webhook (`/webhooks/sms-inbound/{provider}`, allowlist
  twilio/telnyx/fivesim/herosms/getatext) verifies via `hash_equals` BEFORE
  touching the payload, maps generic provider fields, resolves the owning
  `VirtualNumber`, and queues `RecordInboundMessageJob` (unknown number → 200
  no-op). A `message_threads` summary table (one row per user+counterpart,
  maintained by `created` observers on Inbound/Outbound messages) powers the
  two-pane `Messages` inbox (`/numbers/messages`) without a live UNION; opening
  a thread marks it read; replies reuse the existing send-message modal.
- **§2/§5 Section-scoped chrome.** On `/numbers/*` the global bottom nav and
  standard header are replaced by a Numbers section nav
  (Contacts/Forwarding/Dialer/Messages, Messages carrying an unread badge) and a
  wallet-balance top-up bar — driven by ONE bidirectional
  `request()->routeIs('numbers.*')` condition re-evaluated each render, so it
  survives `wire:navigate`. Everywhere else the global chrome is unchanged.
- **§3 Dialer enhancements.** A searchable country dial-code picker (new
  `App\Support\DialCodes`, home markets NG/GH/KE/ZA/US/GB pinned, opens on the
  caller's own country) lets users pick the country and key only the local
  number instead of hand-typing `+<code>`. Flags render through the self-hosted
  flag-icons SVG set (no emoji). The wallet balance is also surfaced on the
  dialer for desktop, where the /numbers header bar is `lg:hidden`. Connecting
  status and recent-calls were already present; no callback switcher (per
  blueprint).
- **§4 Contact-grid message icon.** The grid-view contact card gained the same
  call **and** message action icons the list row and favourites already had
  (was edit-only), gated on `$ownsLine`.
- **§6 Desktop two-column.** Messages is a two-pane inbox; the Dialer puts the
  keypad card left with the contacts + recent-calls rail right on large screens;
  the Contacts A–Z list flows into two balanced columns (break-inside-avoid) with
  a denser lg:4/xl:5 grid view. The add/edit contact sheet stays on the shared
  modal engine (S31) — no bespoke detail pane. Tested (MessagesInboxTest,
  NumbersNavScopingTest, DialerCountryPickerTest).

### Pre-blueprint batch (My Lines, glow, Platform Health, white-label, Copy Studio)
- **Wizard/Nia glow calmed.** The "Confused? Use The Wizard" launcher stacked two
  blurred rotating gold-teal halos (`.nx-wiz-electric` conic aurora + `.nx-wiz-glow`)
  next to Nia's own conic glow. Dropped the electric halo, toned the remaining
  glow to a faint single-teal (0.12–0.24), de-golded/de-duplicated the
  floating-nav centrepiece glow, and calmed the pulses. The `nx-wiz-swell`
  attention state (and its test) is unchanged.
- **My Lines (route `numbers.lines`).** The "My Connectivity" hub moved off the
  dashboard into a dedicated Numbers-section page: active eSIMs (QR/LPA + data
  meter), numbers grouped by Model with per-line Call/Message, and an Archive.
  New `App\Support\ConnectivityHub` is the single source both the dashboard slim
  summary and My Lines read; markup extracted to `partials/my-connectivity`.
  It's the section nav's centre hub button. Tested (MyLinesTest,
  DashboardOrganisationTest retargeted).
- **Platform Health — worker layer + cache flusher.** New `App\Support\QueueHealth`
  reports the live Redis/Horizon picture the DB-only `SchedulerHealth` backlog
  couldn't (driver, Horizon running + `/horizon` link, Redis reachability,
  per-queue pending via Horizon workload, failed-job count + recent failures) —
  visible on VPS as well as cPanel. The System Health page gains that section, a
  per-queue breakdown, a recent-failures list, and a Caches card (Clear app cache
  / Clear all caches). New `ops:worker-health` command (every 5 min) alerts admins
  (email + push, via `AlertAdminJob`) when the worker layer stops — dispatched
  SYNCHRONOUSLY off the cron, since the queue it watches may itself be down.
  Tested (SystemHealthTest).
- **White-label brand word + 25 palettes.** `BrandSettings::word()` + `rebrand()`
  swap the shipped 'Naara' token for an admin-set business name in product/
  sub-brand names ("Naara Rent" → "{word} Rent"); only the capitalised token
  matches (lowercase asset paths untouched), a strict no-op on a default install.
  `ProviderModels` names/taglines route through it; a `@brand` Blade directive
  covers other central strings. Branding admin gains the brand-word field + 25
  curated `BrandSettings::PALETTES` presets on top of the existing custom
  override, applied sitewide via the brand CSS vars. Static per-template "Naara"
  literals are intentionally NOT globally rewritten (would corrupt Livewire
  snapshots) — they move onto `@brand` incrementally. Tested (BrandWhiteLabelTest).
- **Marketing Copy Studio (route `admin.copy-studio`).** Claude-assisted CMS copy
  populator: a saved brand brief (name defaults to the white-label word) trains
  every generation; pick a builder page, Generate 3 on-brand variations per
  section, Apply writes the chosen one into the draft (publish via Page Builder).
  `App\Support\CopyFields` extracts ONLY copy (skips links/images/icons/enums)
  and splices it back at the same paths, so a variation can never alter a URL or
  break a layout; `MarketingCopywriter` drives the existing `AnthropicClient`.
  Admin-only, synchronous, gated behind an "add Anthropic key" state. Tested
  (MarketingCopyStudioTest).

### NCI — Naara Core Intelligence (BUILD-14 → 15 → 16, architecture locked)
The intelligent-orchestration layer, built as four permanently separated layers.
The **eight amendments** in NAARA-BUILD-14 §0 are binding for every batch here and
after; check new work against them rather than re-deriving the architecture.
- **Layer 1 — Provider Registry (BUILD-14).** One shared `provider_registry` row
  per provider across all three stacks (esim/sms/permanent); product_families +
  stack derived from the existing ProviderModels lanes. `ProviderHealth::checkAll()`
  also upserts each probe (plus a new `latency_ms`) alongside its unchanged cache
  write, so the "Provider wallets" widget is untouched. `ProviderRegistry::snapshot()`
  is the 45s cached read — the ONLY sanctioned read path for router code — busted on
  upsert. Seeder ships onboarding tiers + confirmed portal/docs URLs; unconfirmed
  provider URLs are left null (wrong link > blank) and are the admin's to fill:
  quibity, zendit, oneglobal, montymobile, gigs, getatext, herosms, virtsms (login),
  and most docs URLs. It's a snapshot, never the source of truth (amendment 1).
  Tested (ProviderRegistryTest).
- **Layer 2 — Routing Engine (BUILD-15).** `provider_outcomes` logs every real
  attempt (success|failure + error_code). One shared `CircuitBreaker` (used by all
  three routers): CLOSED→OPEN on 5 consecutive failures OR >50% over 20, OPEN→
  HALF_OPEN after a 5-min cooldown, HALF_OPEN one probe → CLOSED/OPEN; transitions
  run synchronously off real outcomes, state in `circuit_breaker_state` (Layer 2 is
  the only writer), and it refreshes the registry's 24h rolling reliability.
  `CandidateOrdering` reorders each router's static lane from the cached snapshot
  (open circuits excluded; nci_score → latency → success_rate → static priority) —
  reorder only, static fallback when the snapshot is empty. Purchase/idempotency/
  money logic byte-for-byte unchanged — only a pre-check + a post-write wrap the
  loop. Defaults: 5 consecutive / 20-window / 50% / 5-min cooldown (Setting-driven).
  Tested (CircuitBreakerTest).
- **Layer 3 — NCI (BUILD-16).** Separate `app/Services/NCI/` namespace, zero
  synchronous entry points. Five NCI-owned registry columns (nci_score/confidence/
  risk_rating/computed_at/sample_size — NCI is the only writer). Events at real
  trigger points: ProviderOutcomeRecorded, CircuitOpened/Closed, HealthCheckCompleted;
  every NCI listener is `ShouldQueue` (asserted by a reflection test) and no router
  imports NCI (asserted by a boundary test). Scoring: EMA nudge per outcome
  (α=0.05) + daily `nci:recompute` (confidence from sample size; risk from
  failure-rate AND error-code diversity — concentrated timeouts → high, expected
  out-of-stock → gentle); weights are named constants. The Routing Engine benefits
  only by reading nci_score off the snapshot it already consumes. Tested
  (NciLearningTest). **Batch 17 (Ops Center) + 18 (new providers) remain.**
- **BUILD-13 (hero showcase image).** Already built + tested in a prior pass
  (showcase block below the subheadline, 2-column-locked CTA row, admin
  `showcase_image` upload via MediaStorage, all image combos) — verified and left
  as-is (MarketingHeroTest). No rebuild.

### NCI Layer 4 — Operations Center (BUILD-17)
Five admin pages under **Admin → Operations (NCI)** (`/adminmaster/nci/*`),
gated on the new `nci.view` StaffScope (override actions on `nci.override`):
Provider Registry (grouped by family, tier-sorted, one-click dashboard links),
Provider Detail (dashboard button + password-gated credential reveal + manual
circuit Open/Reset via `CircuitBreaker::forceOpen/forceClose` + read-only NCI +
Config links), Health Monitor (shares the System Health data source), Routing
Console (read-only order simulation via the real `CandidateOrdering` + a manual
"prefer Provider X for N hours" override in `App\Support\RoutingPreference`,
honoured as the top ordering tiebreaker), and Wallets (balance + volume-based
burn). Zero business logic of its own. Tested (OperationsCenterTest).

### Provider Expansion — new adapters, shipped DISABLED (BUILD-18)
Eight adapters built against the existing interfaces, registered, and seeded into
`provider_registry` with **`enabled = false`** (Frank enables each deliberately
once real credentials exist — `ProviderExpansionSeeder`):
- **Immediate / self-service:** SMSPool + OnlineSIM (SMS/OTP + rental), Plivo
  (permanent + rental), Bitrefill (gift), eSIM Access / Redtea (data eSIM).
- **Placeholder / gated:** Tillo (gift, enterprise), Ubigi/Transatel (eSIM,
  enterprise), Sonetel (permanent, small-business).
- **Skipped (false redundancy):** VirtualSMS, SMSPVA — shared-pool with HeroSMS,
  no real failover diversity.

**Activation checklist per provider (when Frank onboards one):** (1) add its keys
in Integrations; (2) flip `enabled = true`; (3) **add it to the router lane /
`ProviderModels` family + `ProviderHealth::PROVIDERS`** so it becomes routing-
eligible and health-probed.

**⚠ Architecture flag (BUILD-18 §7):** registration + a registry row makes a
provider VISIBLE in the Operations Center, but NOT yet routing-eligible — the
routers iterate lanes defined in `ProviderModels`/router `$chain`, and
`ProviderHealth` probes a fixed provider list. So a genuinely-new provider needs
those two touch-points edited to route + be probed. This is the decoupling gap
BUILD-18 asks to flag rather than work around; a future batch could make lanes +
health-probing registry-driven (read `product_families`/`enabled` from the
registry) to close it. Left flagged, not patched.

**URLs left null for the admin to fill** (unconfirmed — a wrong link is worse):
eSIM Access login, Tillo login, Ubigi login + docs.

**Future — requires a business relationship first (business-development, NOT an
engineering task):** Blackhawk, ePay/Euronet, Maya Connect+, eSIMCard,
Bandwidth/Flowroute (needs a US EIN). Logged here so they're not lost; no
placeholder adapters built.
Tested (ProviderExpansionTest).

### NCI Safety & Hygiene (BUILD-19)
Nine gaps closed across the NCI layers; every change is additive and stays inside
the existing four-layer separation. Covered by `NciSafetyTest` (8 tests).

- **§1 Kill switch.** `nci.enabled` Setting (default true). When false,
  `CandidateOrdering` ignores `nci_score` entirely and falls back to the pre-NCI
  latency/success-rate order; **circuit breakers stay fully active** either way. A
  prominent ON/OFF banner on the **Routing Console** flips it (nci.override) and
  every toggle is audited (who + when) via `Auditor::log('nci.enabled_toggled')`.
- **§2 Circuit alerts.** `CircuitBreaker` now fires `AlertAdminJob` on every
  open (severity critical) and recover (info) transition — through the *existing*
  alert path — naming the real provider and affected product family/families.
- **§3 Confidence-weighted ordering.** Ordering multiplies `nci_score` by
  `nci_confidence` before it competes, so a score from a handful of outcomes only
  nudges while one from thousands carries weight.
- **§4 Retention.** `nci:prune-outcomes` (weekly, Sundays 02:40, background,
  withoutOverlapping) deletes `provider_outcomes` rows past a 90-day horizon
  (`nci.outcome_retention_days`), in chunks so a big backlog never locks the table.
- **§5 Total-outage last resort.** When EVERY candidate in a lane has an open
  circuit, both `ProviderRouter` and `SmsNumberRouter` now attempt the
  least-recently-failed provider anyway (via `CircuitBreaker::lastResortAmong`)
  rather than hard-failing the customer, with a distinct critical alert
  (`total-outage-esim` / `total-outage-sms`). The routers were refactored to a
  per-provider `attemptProvider`/`attemptSmsProvider` helper so the live-attempt
  vs open-skip bookkeeping that gates this is exact.
- **§6 Margin tie-break.** `NciScorer::recompute` lays a **sub-resolution**
  (≤0.003) margin bonus on top of reliability, read from NaaraSim's own ledgers
  (`OrderLog` + `SmsOrder`) — cost never leaves Layer 3. Reliability always
  dominates; margin only separates near-identical providers.
- **§7 Visibility.** **Admin → System Health** now shows a *NCI learning health*
  panel: failed NCI-listener count (`QueueHealth::nciListenerFailedCount`) and the
  nightly `nci:recompute` last-run/overdue state (both NCI commands added to
  `SchedulerHealth::TASKS`).
- **§8 Fresh-install.** Genuinely tested empty-table behaviour (ordering returns
  the static list, circuits default CLOSED, no last resort, recompute + prune run
  clean) and documented in **CPANEL-INSTALL.md** (NCI needs no seeding; it learns
  from real traffic once the cron runs).
- **§9 PII redaction.** New `App\Support\PiiRedactor` (emails → `[email]`, 7+
  digit runs → `[redacted]`) is applied in `CircuitBreaker::record` before any
  provider error string reaches `provider_outcomes.error_code`.
