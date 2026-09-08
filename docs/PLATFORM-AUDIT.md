# NaaraSim — Platform Audit, Pen-Test & Documentation

> Reviewed on 2026-07-17 against branch `claude/naara-sim-foundation-ji6jym`.
> Method: automated test suite (315 tests), dependency advisory scan, a live
> black-box pen-test of the running app (guest + authed), a code-level security
> review of every sensitive surface, and a runtime smoke of all 14 user pages.
> **No application code was changed for this audit** — findings are documented
> here for a follow-up build pass.

---

## 1. Executive summary

**Verdict: the platform is solid and launch-safe on the fundamentals.** Auth,
authorization, money-safety, webhook verification, security headers, CSP,
rate-limiting, IDOR protection and input handling all pass. The test suite is
green (315/315) and there are **zero** known dependency advisories.

Nothing found is a live exploit. The items below are (a) one real correctness
bug on a rarely-hit refund path, (b) a few low-severity polish items, and
(c) intentionally-deferred features that are documented so they don't get lost.

| Area | Result |
| --- | --- |
| Authentication / session | ✅ Pass |
| Authorization (guest/user/admin/staff) | ✅ Pass |
| Money-safety (cost hiding, MarginGuard, atomic wallet) | ✅ Pass |
| Coupon + NaaraCredit floors | ✅ Pass |
| Webhook signature verification | ✅ Pass |
| CSP + security headers | ✅ Pass |
| IDOR (exports, voice clips, orders) | ✅ Pass |
| Rate-limiting (orders, login, contact) | ✅ Pass |
| File uploads (mime + SVG sanitisation) | ✅ Pass |
| Runtime smoke (14 user pages) | ✅ 0 JS errors / 0 HTTP 500s |
| Refund amount on discounted orders | ⚠️ **F1** (see §3) |

---

## 2. Pen-test results (what was actually exercised, live)

### 2.1 Authorization matrix — user-facing first, synced to admin

Tested as an unauthenticated guest against the running server:

| Route | Expected | Actual |
| --- | --- | --- |
| `/dashboard`, `/wallet`, `/rewards`, `/numbers`, `/catalogue`, `/referrals`, `/account`, `/checkout/{plan}`, `/support` | redirect → `/login` | ✅ 302 → /login |
| `/adminmaster` and every `/adminmaster/*` | redirect → `/login` (guest) | ✅ 302 → /login |
| `/api/user` | not accessible to guest | ✅ 302 → /login (see F2) |
| `/horizon` | admin-only in production | ✅ gated by `viewHorizon` (see F3) |

Sync to admin (from the test suite + code review):
- An **authenticated non-admin** hitting `/adminmaster*` gets a **plain 404**
  (not a redirect) — the panel is invisible to normal users (`AdminPanelTest`,
  `AdminSecurityTest`).
- **Staff** see a scope-limited panel: no cost/profit figures, and every admin
  action is `abort_unless(hasAnyRole|can)`-guarded inside each Livewire component
  (verified across Banners, Coupons, Credits, Branding, SiteChrome, LegalEditor,
  Posts, Pricing, Security).
- Staff **cannot delete users** (Section 27) — deletion is super-admin approval
  only (`AccountDeletions`).

### 2.2 Webhook signature verification (live)

| Endpoint | Unsigned / invalid request | Result |
| --- | --- | --- |
| `POST /webhooks/payments/paystack` | bad signature | ✅ **401**, not processed |
| `POST /webhooks/payments/{unknown}` | unknown gateway | ✅ **404** |
| `POST /webhooks/offerwall` | bad HMAC | ✅ **403/401**, not credited |
| `POST /webhooks/getatext` | no token / bad payload | ✅ **422** (token verified via `hash_equals`) |

All money/credit webhooks verify **before** touching the payload, are
**idempotent** on the provider/txn reference, and log to `webhook_logs`.
Payment credit runs in a queued `CreditWalletJob` (`ShouldBeUnique`). The
offerwall postback is HMAC-verified with `hash_equals` (constant-time),
idempotent on the network txn id, and daily-capped per user.

### 2.3 Security headers + CSP (live, on `/login`)

```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none';
  frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https:;
  font-src 'self' data:; style-src 'self' 'unsafe-inline';
  script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self'
```

- No external script origins are allowed (the main XSS-injection defence). The
  Cloudflare Turnstile origin is added to `script-src`/`frame-src` **only while
  Turnstile is active** (verified in `TurnstileTest`).
- `'unsafe-inline'` (styles) + `'unsafe-eval'` (Alpine/Livewire) are a known,
  documented TALL-stack requirement. A future hardening step is a nonce-based
  policy — noted, not blocking.

### 2.4 IDOR / access-scope (code review)

- **Data export** (`AccountExportController`) streams `request()->user()->data_export_path`
  — always the caller's own file, never a request parameter. No IDOR.
- **Support voice clips** (`SupportVoiceController`) — `abort_unless($isOwner || $isAgent)`,
  where owner = `conversation->user_id === user->id`. No cross-user access.
- **Orders / wallet / credits** are always queried `where('user_id', auth id)`.

### 2.5 Money-safety & cost exposure (code review + tests)

- `cost_price_usd`, `provider_cost`, `wholesale_cost`, `profit`, `airalo_min_price`,
  `payout_usd` are all in `$hidden` on their models → never serialise into a
  user payload. Verified live: catalogue/checkout never render cost
  (`CustomerUiTest`).
- **PricingEngine** is the only price calculator; **MarginGuard** floor (cost +
  min profit) cannot be disabled.
- **CouponEngine** clamps every discount to the same floor — a 90%-off code can
  never sell below wholesale (`CouponsAndBannersTest`).
- **NaaraCredits**: credit ledger is atomic (per-user lock + DB txn + balance_after),
  idempotent by reference; the redemption cap + margin floor are specced for
  checkout (see F1/§ deferred).
- Wallet debits/credits/refunds are atomic with `lockForUpdate` + idempotency
  and write `balance_before/after` (`WalletServiceTest`, incl. a real concurrent
  double-spend test).

### 2.6 Rate-limiting

| Flow | Limit |
| --- | --- |
| eSIM checkout / number order | 10 / min / user |
| Login | 5 / min / (email+IP) — Fortify |
| Two-factor, passkeys | throttled |
| Contact form | 3 / 10 min + honeypot |

### 2.7 Runtime smoke

Logged in as a real customer and visited all 14 user surfaces
(`/dashboard`, `/wallet`, `/rewards`, `/numbers`, `/catalogue`, `/referrals`,
`/account`, `/account/security`, `/support`, `/data-estimator`, `/pricing`,
`/blog`, `/legal`, `/legal/privacy`) and performed a live rewards check-in.
**Result: zero JavaScript errors, zero HTTP 500s, check-in credited correctly.**

---

## 3. Findings & documented items

### F1 — `ProviderRouter::orderPlan` over-refunds a discounted eSIM order  ⚠️ (correctness, low frequency)

**What:** On total provider failure, `orderPlan` refunds `final_retail_usd`
(the plan's full list price), but `Checkout` may have debited **less** (a coupon,
and — once built — a NaaraCredit redemption reduce the charged amount). So on the
rare "wallet debited → every provider fails" path with a discount applied, the
user is refunded slightly **more** than they paid.

**Why it matters:** small money leak, and it blocks safe credit-redemption at
checkout (redemption makes the mismatch larger).

**Fix (documented, to bundle with credit redemption):** pass the **actual amount
charged** into `orderPlan(...)` (or read it from the debit reference) and refund
exactly that. Add a regression test: coupon-applied order + all-providers-fail →
refund equals the debited amount, not list price. This is why "spend credits at
checkout" was intentionally deferred — the two ship together as one careful
money-path change.

### F2 — `/api/user` returns a 302 HTML redirect for guests instead of 401 JSON  (low)

**What:** the Sanctum `/api/user` route sits under `web` auth and redirects
guests to `/login` (302) rather than returning `401 Unauthorized` JSON.

**Why it matters:** cosmetic/API-correctness only — no data leaks (guest still
can't read it). If a mobile/SPA client is ever added, it should get 401.

**Fix:** put `/api/user` behind `auth:sanctum` (returns 401) rather than the web
guard, or add an `Accept: application/json` unauthenticated handler.

### F3 — Horizon is open in the `local` environment (by framework design)  (deploy note, not a bug)

**What:** `/horizon` returns 200 to a guest **locally** because Laravel Horizon
bypasses its gate in `APP_ENV=local`. The `viewHorizon` gate is correctly
admin-only and is enforced in production (`FoundationTest` locks
guest/user/staff = deny, admin/super = allow).

**Action:** **deploy checklist** — ensure `APP_ENV=production` in production so
the gate is enforced. (Consider also pinning Horizon behind `/adminmaster`-style
obscurity, optional.)

### F4 — Getatext webhook signature  ✅ RESOLVED (verified during this audit)

Confirmed: `GetatextWebhookController` verifies the `X-Webhook-Token` shared
secret with **`hash_equals`** (constant-time) when `GETATEXT_WEBHOOK_TOKEN` is
configured, and logs every webhook before processing. The 422 seen in the live
test was input validation firing first. **Note (shared with all webhooks):** if
the secret is left unset the handler processes unverified — so the deploy
checklist must include setting every webhook secret before going live.

### Leftover / intentionally-deferred (documented so they aren't lost)

| Item | State | Note |
| --- | --- | --- |
| **Spend NaaraCredits at checkout** (margin-capped) | **done** | shipped with the F1 refund fix as one money-path change |
| **Admin-overridable payment-gateway icons** | to build | see §5 (requested) |
| **Animated favicon preloader** | to build | see §7 (requested) |
| **Big success/failure transaction toaster** | **done** | hero variant on the one toast engine; wired into checkout, numbers, credits & top-up — see §8 |
| Real logo image files | pending owner | upload via Admin → Branding (SVG or transparent PNG) |
| Product reviews / ratings | deferred | UI-kit star rating exists |
| Full i18n + multi-currency | deferred | NGN/USD already live |
| Passkey management UI | partial | Fortify passkey routes exist; needs a settings screen |
| Nonce-based CSP | future hardening | remove `unsafe-inline`/`unsafe-eval` |

---

## 4. API gateways — supply methodology, terms & best practices (per provider)

> All keys are pasted in **Admin → API keys** (encrypted at rest, never echoed),
> and each product flips **Active** only when its required keys are present
> (`ProviderStatus`). Every external call is a **queued job** with retry/backoff
> — never synchronous in the request cycle (money-safety rule 8).

### 4.1 eSIM data (failover chain — interchangeable)

| Provider | Role | Auth | Supply methodology | Key terms / gotchas | Extras |
| --- | --- | --- | --- | --- | --- |
| **eSIM Go** | PRIMARY | `X-API-Key` (+ `x-sandbox`) | Catalogue → order bundle → get QR/LPA | Wallet-funded; keep balance topped up | Webhook HMAC; sandbox flag |
| **Airalo** | SECONDARY | OAuth2 client-credentials (token cached) | Catalogue sync maps `net_price`→cost, **`minimum_selling_price`→`airalo_min_price`** | **Never sell below `minimum_selling_price`** — contractual. Never use Airalo's own retail `price` | token auto-refresh |
| **Quibity / eSIM.sm** | TERTIARY | Bearer (+ `x-sandbox`) | reseller catalogue → order | reseller margins | sandbox flag |

**Router discipline (`ProviderRouter`):** picks the cheapest provider that
covers country + data + validity and **still clears cost + min profit**; never
fulfils a margin-eating fallback; refunds + alerts on total failure. See **F1**
for the refund-amount fix.

### 4.2 Numbers & SMS (capability routing — NOT interchangeable)

> **Lane rule:** match country + type to the owning provider; fall back only
> **within the same lane**, never across lanes.

| Provider | Lane | Auth | Supply methodology | Terms / gotchas |
| --- | --- | --- | --- | --- |
| **Getatext** | US (SMS/OTP + long rental) | API key | order number → poll for OTP | **wallet key — guard it**; webhook |
| **5sim** | GLOBAL (180+ incl. NG/GH/KE/ZA) | JWT | activation + hosting; buy OTP/rental → poll | **wallet key**; rating discipline (price swings) — router skips if live cost > charged − min-profit |
| **SMS-Activate** | global backup | API key | order → poll | backup only |
| **Twilio** | permanent numbers + voice (PRIMARY calls) | Account SID + Auth Token | provision permanent line | "Permanent numbers coming soon" gate in code |
| **Telnyx** | permanent/voice backup | API key | provision | backup |

**Honesty rule enforced in code + Terms:** verification numbers receive SMS
(5sim can also receive a voice OTP) — they are **not** full phone lines; only
permanent numbers make/receive calls. Each product is sold as exactly what it is.

### 4.3 Payment gateways

| Gateway | Auth | Supply methodology | Webhook verification | Terms |
| --- | --- | --- | --- | --- |
| **Paystack** | Secret key | init → redirect → webhook credit | `x-paystack-signature` HMAC (secret key also signs webhooks) | NGN-first |
| **Flutterwave** | Secret key + **Secret hash** | init → redirect → webhook credit | `verif-hash` header == stored secret hash | cards/mobile-money/banks |
| **Stripe** | Secret key + **Webhook secret** (`whsec_…`) | init → redirect → webhook credit | Stripe-Signature (signing secret) | international USD |

**Verified live:** an unsigned/mis-signed payment webhook is rejected **401** and
never credits the wallet. Credit is applied by a unique, idempotent queued job.

### 4.4 Rewarded-ad / offerwall (loyalty)

- **Use a rewarded/offerwall network (AdGate, AdGem, BitLabs, CPX, Pollfish,
  Tapjoy, Adscend) — NOT Google AdSense** (AdSense forbids incentivised views and
  bans the account).
- Credit is granted **only** via the server-to-server postback
  (`/webhooks/offerwall`), HMAC-verified (`hash_equals`), idempotent on the
  network txn id, with a per-user daily cap. The browser never grants its own
  reward. **No self-clicking / auto-clicking** — that is click fraud and was
  deliberately not built.
- Two credentials, documented in-panel: `OFFERWALL_POSTBACK_SECRET` (env) + the
  postback URL/HMAC recipe shown on **Admin → NaaraCredits**.

### 4.5 Other integrations

| Integration | Purpose | Auth |
| --- | --- | --- |
| **Anthropic** | NaaraCare support agent + pricing education + maintenance loop | API key + model id |
| **ElevenLabs** | spoken support replies (paying customers) | API key + voice id + model |
| **Google OAuth** | social sign-in | client id + secret (redirect `/auth/google/callback`) |
| **Cloudflare Turnstile** | bot challenge on login/register | site + secret key |
| **Wasabi S3** | all file storage (falls back to public disk on cPanel) | key/secret/bucket |

---

## 5. Admin-overridable payment-gateway icons  (requested — to build)

**Goal:** the admin can override the logo shown for each payment gateway
(Paystack, Flutterwave, Stripe) exactly like the existing service-icon override
for WhatsApp/Google/etc. — so gateway branding is never "left in the dark".

**Recommended implementation (reuse the proven pattern):**
- Extend `App\Support\ServiceIcons` (or a small sibling `PaymentIcons`) to resolve
  a payment-gateway mark in the same order it already uses:
  **admin override → bundled brand SVG → letter avatar.**
- Add `paystack`, `flutterwave`, `stripe` (and `wallet`, `naaracredits`) marks to
  the bundled service sprite (`partials/service-icon-sprite.blade.php`).
- Add a "Payment gateways" group to the existing **Admin → Service icons** page
  (`ServiceIconsPage`) so uploads flow through `MediaStorage` (mime-validated,
  SVG-sanitised) and cache-flush exactly like today.
- Render via the existing `<x-service-icon slug="paystack" …>` on the Wallet
  top-up gateway rows (currently a letter chip "P/F/S"), the checkout, and
  anywhere a gateway is shown.
- **Effort:** small — no new infra, it's the Module 27.5 icon system extended.
- **Doc for the operator:** upload a square, transparent PNG or an SVG; keep the
  brand's official mark; light/dark handled by the icon frame.

---

## 6. UI/UX structure (reference)

**Layouts (`resources/views/components/layouts/`)**
- `app` — base shell: `<head>` (favicon, fonts, CSP-safe pre-paint theme script,
  injected brand-colour `<style>`), icon sprites, splash, toast-stack, preloader.
- `customer` — app shell with Apple-style desktop side-menu + mobile bottom-nav
  with a centre "More" sheet; WhatsApp live-help FAB.
- `admin` — same shell, role-scoped nav.
- `marketing` — sticky glassy nav + assignable footer.
- `auth` — two-column (admin media panel + form), collapses on mobile + slim footer.

**Design system**
- Brand palette is **runtime-themeable** via CSS variables
  (`--brand-primary/-dark/-accent/-navy/-action`, `--brand-radius`) — admin
  recolours everything with no rebuild (Module 26).
- Component kit in `resources/css/ui-elements.css` (`nx-*`): buttons, switch,
  checkbox, tag, alert, loader, skeleton, toast, upload, plus the adapted
  premium cards (aurora, 3D glass, animated-border, donut, collapsible top-up,
  floating-light, theme scene). Tokens follow the brand vars, so they recolour too.
- **Icons: SVG sprite only — no emoji anywhere** (enforced by `IconSystemTest`).
- **Dark mode on every element**; **loading state on every action**; money
  actions disable their button in flight.
- Fonts: "Supreme Display" (headings) + "Didact Gothic" (body), self-hosted.

**Motion:** GSAP (npm-bundled, CSP-safe, reduced-motion-aware) for the marketing
hero/parallax/pinned-products/timeline/count-ups; IntersectionObserver reveals.

---

## 7. Preloader — best-practice spec (requested)

**Vision:** the NaaraSim **favicon logo** animates in an "impulse" (pulse/heartbeat)
way as a solid full-screen preloader, matching the best of the vendored Uiverse
loaders; it **dismisses the moment the section/action finishes**, in sync.

**Current state:** `<x-brand-preloader>` exists (Module 26) — a brand-coloured
spinner overlay, admin-toggleable, reduced-motion-aware, self-removing on
`window.load` with a 4 s hard fallback. It does **not yet** use the logo mark or
pulse, and it only covers first paint (not per-action).

**Recommended upgrade (to build):**
1. **Logo mark, not a ring** — drop the brand favicon/product SVG into the
   overlay centre; animate with an *impulse* keyframe (scale 1 → 1.12 → 1 +
   opacity pulse, ~1.1 s ease-in-out), plus a subtle brand-accent glow ring.
   Respect `prefers-reduced-motion` (static logo, no pulse).
2. **Two modes:**
   - **Page preloader** (existing) — shown until `window.load`, then fades out in
     sync (already implemented; swap the ring for the pulsing logo).
   - **Action preloader** — a lightweight, scoped variant tied to a Livewire
     action: show on `wire:loading`, **dismiss exactly when the action resolves**
     (`wire:loading.remove` / `livewire:navigated`), so "the loader dismisses
     alongside" the finished section/action. Use the same pulsing-logo motif at
     smaller size for in-card loads.
3. **No theme flash** — the overlay uses `--brand-navy`, painted before Alpine
   boots (like the existing pre-paint theme script).
4. **Never trap the page** — keep the hard-timeout fallback; the action variant
   also auto-clears on error.
5. **Admin controls** (extend Admin → Branding): on/off (exists), style =
   `pulse-logo | spinner | bars`, and it uses the uploaded favicon automatically.

**Acceptance:** logo pulses; overlay fades in ≤150 ms after load / action
completion; reduced-motion shows a static logo; no theme flash; never persists >4 s.

---

## 8. Transaction success / failure toaster — spec (requested)

**Vision:** a prominent ("huge") toaster for successful transactions, successful
activities, and failed transactions/activities.

**Current state:** a global toast engine exists (`<x-ui.toast-stack>`, dispatched
via `nx-toast` from Livewire or a JS event) and is mounted once in the base
layout — used today for admin saves. It is a **small** corner toast.

**Recommended upgrade (to build):**
1. **A "hero" toast variant** — larger, centered-top or centered card, with a
   big status glyph (animated check / x / spinner), a bold headline
   ("Payment successful", "Top-up complete", "Order confirmed",
   "Payment failed — you were not charged"), a one-line detail, and an optional
   CTA ("View my eSIM", "Try again"). Auto-dismiss ~5 s for success, sticky until
   dismissed for failure.
2. **Variants:** `success` (brand teal + gold accent, confetti-lite optional),
   `error` (action-coral), `pending` (neutral + spinner), `info`.
3. **Money-path wiring** — dispatch the hero toast from:
   - wallet **top-up** success/failure (already emits an email; add the toast),
   - **checkout** success/failure (eSIM + number),
   - **refund** issued,
   - **NaaraCredit** earned / redeemed.
   Reuse the same `nx-toast` dispatch with a `variant: 'hero'` flag so it's one
   engine, no duplication.
4. **Accessibility:** `role="status"` (success/info) / `role="alert"` (error),
   focus-safe, reduced-motion (no confetti/scale), dark-mode parity.
5. **Server-anchored** where it represents a real transaction (dispatched only
   after the money action commits), so the user never sees a false "success".

**Acceptance:** every money action ends in exactly one hero toast reflecting the
true committed outcome; failures are sticky and reassuring ("you were not
charged"); success auto-dismisses; SVG glyphs only (no emoji); dark-mode + reduced-motion honoured.

**Built (this pass).** The existing `<x-ui.toast-stack>` engine now carries a
second shape — a centred hero card — behind a `variant: 'hero'` flag, so it is
still one engine, one dispatch (`nx-toast`). Drawn SVG glyphs (animated check /
cross / spinner), bold headline + detail + optional CTA, `role="alert"` for
errors / `role="status"` otherwise, dark-mode + `prefers-reduced-motion`
parity. Success/info auto-dismiss (~5.5 s); errors stay until dismissed.
Server-anchored dispatches were wired into:

- **eSIM checkout** — success ("Order confirmed" + *View my eSIM*), and every
  refunded-failure branch (low balance, all-providers-failed, order-save
  failure) with "you were not charged" copy.
- **Number checkout** — success ("Number reserved"), low balance, and the
  reserve-failed-then-refunded branch.
- **NaaraCredits check-in** — hero success on the credits actually committed.
- **Wallet top-up** — hero error when the gateway can't be reached (the user
  was not charged).

**Deliberate exclusion — top-up *success*.** A successful top-up is credited
**asynchronously by the payment webhook**, not on the browser's return from the
gateway. Firing a "success" hero on return would be a *false* success (the money
may not be credited yet), which violates the server-anchored rule above. Top-up
success therefore stays confirmed by email + the live wallet balance, exactly as
the acceptance ("never a false success") requires. If a real-time success toast
is wanted later, the correct place to originate it is the webhook (e.g. via a
broadcast/echo channel), never the redirect return.

---

## 9. Recommended next actions (prioritised)

1. **Money-path pass (highest value):** fix **F1** (refund the actual charged
   amount) **and** ship NaaraCredit **redemption at checkout** (margin-capped) in
   one careful, well-tested change.
2. **Transaction hero toaster** (§8) — high UX value, low risk, reuses the toast
   engine; wire it into every money path.
3. **Animated favicon preloader** (§7) — upgrade the existing preloader to the
   pulsing-logo + per-action variant.
4. **Admin-overridable payment-gateway icons** (§5) — small, reuses the icon
   system.
5. **F2** (`/api/user` → 401 JSON) and **F4** (confirm Getatext HMAC) — quick.
6. **Deploy checklist:** `APP_ENV=production`, real provider keys last (after
   this pass), rotate keys every 90 days, upload real logo files.

_Nothing here blocks continued development; F1 is the only correctness item and
it is contained to a rare discounted-order failure path._
