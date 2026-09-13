# CLAUDE.md — NaaraSim Project Brief

> Claude Code reads this file automatically at the start of every session.
> It is the permanent context for this project. Do not delete it.
> The full specification is `NaaraSim-Master-Build-Blueprint-v5.docx` in this folder (Sections 0–32).

---

## HOW WE BUILD (read this first)

- **You are the single developer.** We are NOT using Ruflo or any multi-agent orchestration. Ignore all Ruflo / swarm / agent / SPARC references in Section 23 of the blueprint. Use Section 23 ONLY for the module order and the acceptance checks.
- **Build ONE module at a time**, in the order listed in PROGRESS.md. Never attempt to "build the platform" in one go.
- **Never start a module** whose dependency module isn't finished and passing its acceptance check.
- **After every task, update PROGRESS.md** (move finished work to DONE, put the exact next step at the top of NEXT).
- **Test money paths on SANDBOX keys only.** Real provider/payment keys go in last, after the hardening module.

---

## GITHUB ACTIONS BUDGET (non-negotiable until usage resets — 2026-10-01)

The SupremeIdeas GitHub account hit 90% of its 2,000 included Actions
minutes/month (owner alert, 2026-09-13). Until the allowance resets on
2026-10-01, conserve Actions minutes hard:

- **CI is manual-only.** `tests.yml` and `deploy.yml` in `.github/workflows/`
  are set to `workflow_dispatch` — the daily test `schedule` cron and a full
  PHP-matrix re-run on every push to an open PR were the drain. Do NOT
  re-enable `push:` / `pull_request:` / `schedule:` triggers before the reset.
- **Test locally, never on CI.** Run `vendor/bin/phpunit` (and `npm run build`
  for view changes) in the dev environment — that is the acceptance gate while
  we build. A manual CI pass (Actions tab / `gh workflow run Tests`) is only
  for a deliberate milestone check the owner asks for.
- **Push with `[skip ci]`** in the commit subject as a belt-and-suspenders
  guard. Never kick CI with empty commits, re-runs, or close/reopen.
- Applies to all three repos (master + both white-labels). Restore normal
  push/PR CI triggers after 2026-10-01 only if the owner asks.

---

## What we are building

**NaaraSim** — a Pan-African travel-connectivity SaaS selling:
1. **eSIM data plans** for 190+ countries
2. **Virtual phone numbers** (permanent, voice + SMS) and **SMS verification numbers** (disposable OTP + rentals)
3. It uniquely sells BOTH data AND numbers in one app — most competitors are data-only. Lean into that.

Owner: Frank Charles Ebubedike, Supreme Ideas Agency, Onitsha, Nigeria.
Tagline: *"Stay Connected. No Borders. No Swaps."*
Brand: Deep Teal `#0A6E6E`, Warm Gold `#D4A017`, Midnight Navy `#0D1B2A`.

---

## Tech stack (do not substitute)

- **Laravel 12** (PHP 8.2+), TALL stack. (Upgraded from Laravel 11 on 2026-07-14 — L11 was past its 2026-03-12 security-EOL. Keep on a security-supported release.)
- **Livewire 3** + **Alpine.js 3** + **Tailwind 3.4** (`darkMode: 'class'`)
- **MySQL 8** (utf8mb4, strict) · **Redis** (queue/cache/session) · **Horizon**
- **Sanctum** + **Fortify** (2FA/TOTP) + **Spatie Permission** (roles)
- **Wasabi S3** for all file storage (never local disk)
- Deploy: BOTH VPS and shared cPanel, installable like a CodeCanyon product
- Scale target: 1M+ users

---

## Providers (exact roles)

**eSIM (interchangeable -> failover chain):**
- eSIM Go (PRIMARY) · Airalo (SECONDARY — honor minimum_selling_price) · Quibity/eSIM.sm (TERTIARY)

**Numbers (NOT interchangeable -> capability routing by country + type, never blind failover):**
- Getatext -> US (SMS/OTP + long rental)
- 5sim -> GLOBAL, 180+ countries incl. Nigeria/Ghana/Kenya/South Africa (activation + hosting; rating discipline required)
- HeroSMS (primary) / VirtSMS (fallback) -> global backup, same legacy handler_api.php protocol (SMS-Activate shut down 2025-12-29 and was replaced by these two) · Telnyx -> permanent/voice backup
- Twilio -> permanent numbers + voice (PRIMARY for calls)
- **Lane rule:** match country+type to the owning provider; fall back only WITHIN the same lane. Never cross lanes.

---

## MONEY-SAFETY RULES (non-negotiable — Sections 1 & 13)

1. **Golden Rule:** Retail = Provider Cost + Margin. User ALWAYS pays retail; NaaraSim ALWAYS pays cost. Never reversed.
2. **Never expose cost.** cost_price_usd / net_price never appears in ANY user-facing response, template, or payload.
3. **All prices flow through PricingEngine.** No price literals anywhere. Number/SMS costs fetched live before quoting.
4. **MarginGuard mandatory.** Retail never at/below cost + min profit. Auto-correct upward + log. Airalo min-price guard too.
5. **Wallet debits/credits atomic.** DB transaction + lockForUpdate(); write wallet_transactions with balance_before/after.
6. **Never charge without delivering.** Orphan-charge guard: charged but downstream save failed -> auto-refund + release.
7. **Money actions never blind-retry.** Failed order/debit -> refund + alert. Idempotency keys on order jobs.
8. **Every external API call is a queued job** (Horizon, retry+backoff) — never synchronous in the request cycle.
9. **Verify every webhook** with HMAC + hash_equals() before touching the payload. Idempotency on provider refs.
10. **No secrets in code.** Keys in .env via config(). Rotate provider/wallet keys every 90 days.

## UI RULES (non-negotiable)

- **SVG icons only — no emoji anywhere.** Inline sprite (Section 16) + admin custom-icon override.
- **Dark mode on every element.** Every bg/text/border/shadow has a `dark:` variant.
- **Every action shows a loading state.** Money actions disable their button while in flight.

## PLATFORM STANDARD (Sections 24–32)

- **S24 Splash screen:** admin-configurable, product logo + "from Supreme Ideas", auto dark/light, no theme flash.
- **S25 Admin at `/adminmaster`:** env-driven, plain 404 for non-admins, zero links from user UI.
- **S26 Account lifecycle (GDPR):** self-deactivate; data export (filters third-party PII); deletion = super-admin approval only; erasure reaches backups.
- **S27 Staff + scoped roles:** granular permissions. Staff can do almost anything EXCEPT delete users.
- **S28 DB backup:** spatie/laravel-backup to Wasabi; on shared cPanel where mysqldump is blocked, FALL BACK to ifsnop/mysqldump-php (needs SELECT + SHOW VIEW).
- **S29 Claude maintenance loop:** reads error log, proposes diff, on approval commits to a branch + PR (CI-gated). Never straight to prod; secrets never touched; one-click rollback.
- **S30 Security matrix:** every OWASP mistake + attack class mapped to a defense.
- **S31 UI kit:** star rating, ONE modal engine, theme toggle, server-anchored countdown, search+debounce — themed + accessible.
- **S32 Niche edge:** manual LPA install fallback, device-compat check BEFORE purchase, clear refund policy, live chat + WhatsApp, NaaraCredits loyalty, data estimator, i18n + multi-currency.

---

## THEME VISUAL REBUILD RULES (non-negotiable — every batch, every theme)

The platform is mid-way through giving each of the 40 theme presets its own
full "swappable section" suite (header, bottom nav, login, login_bg, footer,
landing page, about/how-it-works/contact pages — see `App\Support\ThemePreset`
`SECTION_STYLE_ALLOW`, `LandingHeroLibrary`, `ThemePageLibrary`). These rules
came from direct owner correction after an early batch shipped a recolour
instead of a redesign — read them before touching ANY theme batch, and apply
them from the first commit, not as a later cleanup pass.

1. **No shared section skeleton across themes.** Never reuse the same
   section arrangement (e.g. "2-card band → 3-card grid → centred card")
   recoloured for a different theme. Each theme's header/footer/landing
   page/about/how-it-works/contact must be a genuinely different structural
   composition — research a real reference (Dribbble/Behance/Awwwards-
   calibre layout patterns; browser-fetch and look at actual pages, don't
   guess from memory) and adapt ITS layout DNA to NaaraSim's content, not a
   generic template. If two themes end up sharing a pattern, recolour is not
   enough — vary the arrangement, not just the palette.
2. **No empty image placeholders, ever.** If a themed section calls for an
   image and no real asset exists yet, pick a real, on-brand stock photo —
   never ship a bare gradient box or an obviously blank slot "to fill in
   later." Exception: a placeholder that stands in for a SPECIFIC named
   real person (e.g. the owner) without a real photo on file — use an
   initials avatar or similar honest placeholder instead of a stock photo
   of a stranger mislabelled with their name.
3. **Images are committed assets, never live hotlinks.** Download the
   chosen photo once (through whatever network path actually works in the
   current environment — verify with `curl` first), evaluate whether it
   needs background removal (only for cutout/isolated-subject use, e.g. a
   floating mascot or device cutout with no card frame around it — a normal
   photo shown inside a rounded card frame does NOT need bg removal), fit
   it to brand messaging (does the actual photo content match what the
   copy/section is about — verify by looking at the downloaded image, not
   just the source description), convert to WebP, and commit it under
   `public/images/themes/{theme-slug}/...` (or `public/images/themes/shared/`
   for a photo reused by more than one theme) — same convention as the
   existing `public/images/audiences/` and `public/images/steps/` assets.
   Target similar file sizes (tens of KB, not hundreds) via reasonable
   width caps and WebP quality ~80. Reference the committed file with
   `asset('images/themes/...')`, never an external URL, so no page ever
   depends on a third-party host being reachable — "so we don't see a
   stale section ignorantly seeing blank areas." `rembg` (Python) is
   installed in dev environments for background removal when a cutout
   actually needs it; Pillow handles the WebP conversion. For a batch with
   many images, parallelize the fetch/process step (e.g. one agent or one
   script pass per theme) rather than doing it one photo at a time.
4. **Footer is swappable too, like header/bottom_nav.** Every full-suite
   theme gets its own footer treatment via the same `SECTION_STYLE_ALLOW`
   pattern (`'footer' => ['default', 'theme-slug', ...]`) — never assume
   the shared straight default footer is "good enough" once a theme has a
   custom header/landing page/page suite. Same content (brand blurb,
   product/company link columns, legal links), reskinned to match that
   theme's persona.
5. **No flat straight-line section dividers.** Wherever two stacked
   sections meet with genuinely different background colours, give that
   boundary either (a) a ~30px rounded-top-corner "sheet" treatment
   (`rounded-t-[30px]` on the later section, pulled up with a small
   negative top margin, e.g. `-mt-8`, so the curve reveals the earlier
   section's colour underneath) or (b) another deliberate divider shape
   (wave, angled cut, notch) — pick whichever fits that theme's persona.
   A plain flush line where two different-coloured sections touch reads as
   generic and is not acceptable. (Sections that already share the same
   background colour have no seam to decorate — don't invent one.)
6. **Use the actual tooling, don't guess.** Verify a candidate image's
   real content by opening it (not just trusting the source URL's
   description), verify a candidate icon exists in
   `resources/views/partials/icon-sprite.blade.php` before referencing it
   (add it properly, lucide-style stroke paths, if it doesn't — never
   substitute a mismatched icon or emoji), and run `npm run build` before
   any final screenshot verification pass — Tailwind only compiles
   classes present in blade files at the moment of the build, so a brand
   new arbitrary-value class (e.g. `grid-cols-[1fr_260px]`) silently does
   nothing in a stale build and can look exactly like a real layout bug.
   Browser-verify every new page at both mobile and desktop viewports
   before calling a batch done.

---

## Graphify Rules

Graphify maps this codebase into a queryable knowledge graph under `graphify-out/`
(regenerated per-machine with `graphify update .` — no API cost, no LLM). Use it as
the primary architectural navigation layer for this large, highly-interconnected
platform.

- Before broad multi-file exploration or cascading searches, use Graphify when
  architectural relationships would materially speed up the investigation.
- Consult `graphify-out/GRAPH_REPORT.md` for system entry points, architectural
  boundaries, dependency relationships, and the most-connected files ("God Nodes").
- Use `graphify query "..."`, `graphify explain "X"`, and `graphify path "A" "B"`
  when the graph answers an architectural relationship question faster than a broad
  repository search.
- Use Graphify to decide **where** to start, then read the actual source directly.
  It is never a substitute for reading implementation code, tests, config, DB
  schemas, API definitions, or docs.
- Do not blindly modify a highly-connected file. Before changing one, inspect its
  callers, dependencies, side effects, tests, and downstream impact — this is
  doubly true for the money-path hubs (`WalletService`, `PricingEngine`) and the
  `User`/`Setting` god nodes.
- Prefer the smallest relevant search scope when the target file/function/class is
  already known. Don't query the graph for simple, localized tasks.
- When the graph is stale (code changed since it was built — compare
  `git rev-parse HEAD` to the report's build commit), run `graphify update .`
  before relying on it for architectural decisions.
