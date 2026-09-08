# NaaraSim — Theme Visual Rebuild Blueprint & Principles Prompt

> **Purpose.** This is the single, self-contained brief for continuing the
> theme visual rebuild (batches 4–8, the remaining ~25 of 40 presets) with
> exactly the discipline that produced batches 1–3. Read this file, plus
> the `THEME VISUAL REBUILD RULES` section of `/CLAUDE.md`, before touching
> a single blade file. Everything you need to brief a fresh session or a
> background agent is here.
>
> Owner: Frank Charles Ebubedike · Supreme Ideas Agency · Onitsha, Nigeria.
> Tagline: *"Stay Connected. No Borders. No Swaps."*
> Brand: Deep Teal `#0A6E6E` · Warm Gold `#D4A017` · Midnight Navy `#0D1B2A`.

---

## 0. What "done" looks like for one theme (the full suite)

A theme is at **full-suite status** only when ALL of these exist for its
slug and are genuinely distinct from every other theme (not a recolour):

1. `header` — swappable header partial
2. `bottom_nav` — swappable authenticated bottom navigation
3. `login` — swappable split/other login screen
4. `login_bg` — an assigned login-background effect (aurora / mesh-grain /
   dot-grid / none / …)
5. `landing_hero` — a custom marketing landing page (its own hero + section
   composition)
6. `about_page`
7. `how_it_works_page`
8. `contact_page`
9. `footer` — swappable footer (never the shared straight default once a
   theme has its own header/landing)

**Status as of 2026-09-07: 15 of 40 themes are full-suite** (naara-official's
built-in suite + neon-vertex, midnight-signal, aries-contrast, paperwhite,
origin-bold, solar-flare, noir-reserve, aurora-shift, sunset-transit,
fintra-clean, capable-mono, waitlisty-soft). ~25 remain. See
`ThemePresetSeeder` for the full 40-slug roster and pick the next 5 per batch.

---

## 1. The non-negotiable rules (from direct owner correction)

These came from the owner rejecting an early batch that shipped a recolour
instead of a redesign. They apply **from the first commit of every batch**,
never as a later cleanup pass. (Canonical copy lives in `/CLAUDE.md` under
`THEME VISUAL REBUILD RULES` — this is the working summary.)

1. **No shared section skeleton across themes.** Never reuse the same
   arrangement (e.g. "2-card band → 3-card grid → centred card") recoloured
   for a different theme. Each theme's header/footer/landing/about/
   how-it-works/contact must be a genuinely different structural
   composition. **Research a real reference** (Dribbble / Behance /
   Awwwards-calibre) per persona — browser-fetch and look at actual pages,
   don't guess from memory — and adapt ITS layout DNA to NaaraSim's content.
   If two themes end up sharing a pattern, recolour is not enough; vary the
   arrangement, not just the palette.
2. **No empty image placeholders, ever.** Every image slot gets a real,
   on-brand stock photo (or a genuinely-fitting existing
   `public/images/themes/shared/*.webp`). Never a bare gradient box or a
   blank "fill later" slot. Exception: a placeholder standing in for a
   SPECIFIC named real person with no real photo — use an initials avatar,
   not a mislabelled stranger.
3. **Images are committed WebP assets, never live hotlinks.** Download the
   photo once (verify the network path with `curl` first), check whether it
   needs background removal (only cutout/isolated-subject use — a photo in a
   rounded card frame does NOT), confirm the actual photo content matches
   the copy by opening it, convert to WebP (~quality 80, width-capped to
   tens of KB), and commit under `public/images/themes/{theme-slug}/…` (or
   `public/images/themes/shared/…` if reused by >1 theme). Reference with
   `asset('images/themes/…')`, never an external URL. `rembg` (Python) is
   installed for cutouts; Pillow handles WebP. Parallelise the fetch/process
   step for a batch with many images.
4. **Footer is swappable too**, on the same `SECTION_STYLE_ALLOW` pattern
   (`'footer' => ['default', 'theme-slug', …]`). Same content (brand blurb,
   product/company columns, legal links), reskinned to the persona.
5. **No flat straight-line section dividers.** Where two stacked sections
   meet with different background colours, give the boundary either (a) a
   ~30px rounded-top "sheet" (`rounded-t-[30px]` + small negative top margin
   like `-mt-8` on the later section so the curve reveals the earlier
   colour) or (b) another deliberate shape (wave, angled cut, notch, punched
   perforation) fitting the persona. **Divider shapes already taken** (pick
   something new per theme): SVG sine wave (aurora-shift), punched circular
   perforations via CSS `mask-image` (sunset-transit), tear-off statement
   perforation (fintra-clean), 1px neon hairline on a flush seam
   (capable-mono), full-width soft SVG wave crest (waitlisty-soft), diagonal
   `clip-path` (solar-flare), asymmetric single-rounded-corner seam
   (noir-reserve). **Caution:** if a divider uses `clip-path` on a section
   that also renders in a short "slim" variant (e.g. a login-page footer),
   ensure the slim variant has enough padding to clear the cut, or give the
   slim variant a different, non-clipping treatment — a clip-path slicing
   through real content is a real bug we hit.
6. **Use the actual tooling, don't guess.** Verify a candidate image by
   opening it; verify an icon exists in
   `resources/views/partials/icon-sprite.blade.php` before referencing it
   (add it properly, lucide-style stroke paths, if missing — never
   substitute a mismatched icon or an emoji — no emoji anywhere, SVG only);
   run `npm run build` before ANY screenshot pass (a brand-new
   arbitrary-value class like `grid-cols-[1fr_260px]` or `max-w-[240px]`
   silently no-ops in a stale build and looks exactly like a layout bug);
   browser-verify every new page at BOTH mobile (390×844) and desktop
   (1440×1000) viewports before calling a batch done.

Plus the platform-wide UI rules that always apply: **SVG icons only, no
emoji; every element has a `dark:` variant; every action shows a loading
state (money actions disable their button while in flight).**

---

## 2. The architecture (where each piece is wired)

All theme-section resolution is schema-driven. To add a theme you extend
registries and add blade files — you do **not** touch the resolver logic.

- **`app/Support/ThemePreset.php`**
  - `SECTION_STYLE_ALLOW` — the whitelist mapping each section
    (`header`, `bottom_nav`, `login`, `login_bg`, `landing_hero`,
    `about_page`, `how_it_works_page`, `contact_page`, `footer`) to the set
    of allowed style slugs. **Add your new theme's slug to each section it
    implements.**
  - `sectionStyle($section)` / `sectionStyleFor($sectionStyles, $section)` —
    resolvers (do not modify).
  - `HEADER_BLUR_DEFAULTS` — per-header-style default glassmorphism blur
    (px). Add your slug (usually `0`, or `8`/`12` if the header is a glass
    bar). Header colour / corner-radius / blur are all admin-editable at
    runtime via the header editor — see `resolveHeaderSettings()`,
    `headerStyleCss()`, `headerColorHex()`; you only set the default.
  - `browserThemeColor()` / `headerColorHex()` — drive the
    `<meta name="theme-color">` (browser chrome). Nothing to add per theme.
- **`app/Support/LandingHeroLibrary.php`** — `styles()` registry. Add one
  entry keyed by your slug with the full field schema
  (eyebrow / headline / description / cta_label / image / image_radius /
  image_position / stat_value / stat_label) and persona-matched copy.
- **`app/Support/ThemePageLibrary.php`** — `registry()` with nested
  `about_page` / `how_it_works_page` / `contact_page` entries per slug, each
  with its full field schema + persona-matched copy.
- **`database/seeders/ThemePresetSeeder.php`** — extend the
  `batch1SectionStyles()` match statements (`$loginBg`, `$landingHero`,
  `$fullSuitePage`, and the outer selector) so a **fresh install** carries
  the identical section assignments.
- **A new additive migration** —
  `database/migrations/YYYY_MM_DD_HHMMSS_assign_theme_batchN_section_styles.php`
  mirroring the batch-2/batch-3 template exactly. Additive only: it assigns
  section_styles for the new slugs, **never overwrites** admin tuning
  (same discipline as the `accent_dark` backfill).
- **Blade partials** (8 files per theme):
  - `resources/views/components/theme-sections/header/{slug}.blade.php`
    (carry the `data-header-root` attribute on the bg-carrying element so
    the header editor can target it)
  - `resources/views/components/theme-sections/bottom-nav/{slug}.blade.php`
  - `resources/views/components/layouts/theme-sections/login/{slug}.blade.php`
  - `resources/views/components/theme-sections/footer/{slug}.blade.php`
    (carry `data-footer-style="{slug}"`)
  - `resources/views/marketing/theme-landing/{slug}.blade.php`
  - `resources/views/marketing/theme-pages/{slug}/about.blade.php`
  - `resources/views/marketing/theme-pages/{slug}/how-it-works.blade.php`
  - `resources/views/marketing/theme-pages/{slug}/contact.blade.php`
- **Tests** — mirror `ThemeBatch3VisualRebuildTest.php` for the new batch
  (resolver correctness + login/header/about/how-it-works/contact/home
  rendering per theme + naara-official-unaffected) and extend
  `ThemeFooterStylesTest::footerThemes()` with the new slugs.

> Note (dashboard vs marketing): the authenticated dashboard app uses
> `app-shell.blade.php`'s swappable header/bottom-nav; the public marketing
> pages use the `marketing/theme-landing/*` and `marketing/theme-pages/*`
> custom pages. Verify header changes on `/dashboard`, and landing/about/etc
> on `/`, `/about`, `/how-it-works`, `/contact`.

---

## 3. The build workflow that worked cleanly in batches 1–3

**Do infra sequentially yourself, parallelise only the blade files.** This
avoids file conflicts entirely.

1. **Pick 5 personas** from the remaining ~25 slugs in `ThemePresetSeeder`.
   For each, decide a *distinct real-world layout DNA* (an airline boarding
   pass, an accounting statement, a terminal, a warm consumer waitlist page,
   a fintech dashboard, an editorial magazine, a museum page, …). Research a
   real reference for each before writing anything.
2. **Orchestrator (you) does ALL shared-file infrastructure first, in one
   pass, sequentially:** extend `SECTION_STYLE_ALLOW`, `HEADER_BLUR_DEFAULTS`,
   `LandingHeroLibrary::styles()`, `ThemePageLibrary::registry()`, the
   seeder match statements; write the additive migration; run it. Commit.
3. **Image pipeline** for any new photos (rule 3): fetch → verify content →
   (bg-remove only if cutout) → WebP → commit under
   `public/images/themes/{slug}/`. Parallelise across themes.
4. **Parallelise the per-theme blade work** across 5 background
   `general-purpose` agents, one per theme. Each agent's brief must include:
   the persona + its researched reference, the EXACT 8 file paths, the EXACT
   registry field keys it must satisfy, the reference partials to study, the
   footer divider shape to use (and the explicit list of shapes already
   taken — see rule 5), image-reuse rules, icon-verification rules, and an
   explicit **"do not touch any .php file"** instruction (only the
   orchestrator edits shared PHP). Agents write directly to the working
   directory — do NOT use worktree isolation for this.
5. **Commit incrementally** as agent files land (run `php -l` on each first)
   so the branch stays buildable and the stop-hook stays satisfied.
6. **Verify** (see §4). Fix any bugs found (e.g. batch 3 hit a fintra-clean
   login overlap — two competing centred layers in one panel; fixed by
   re-anchoring the decorative layer). Re-verify after each fix.
7. **Update PROGRESS.md** (move the batch to DONE, point NEXT at the next
   batch) and this file's §0 status line.

If agents hit a transient session rate-limit, verify no partial files were
written (`git status --short`), wait for the reset window, and relaunch with
byte-identical prompts.

---

## 4. Acceptance checklist for a batch (all must pass)

- [ ] Each of the 5 themes resolves all 8 sections to its own slug
      (asserted by the batch test).
- [ ] `php artisan test` — full suite green (was 1774 passing at last merge;
      never let a batch drop it).
- [ ] `npm run build` succeeds AND is re-run before the screenshot pass.
- [ ] Every new page browser-verified at mobile (390×844) + desktop
      (1440×1000): home, about, how-it-works, contact, login.
- [ ] No two themes share a section skeleton; each divider shape is
      distinct and non-flat.
- [ ] Every image is a committed local WebP whose content matches its copy;
      no hotlinks, no blank placeholders.
- [ ] Every referenced icon exists in the sprite; no emoji anywhere.
- [ ] `vendor/bin/pint` clean on the files YOU created/edited (the repo has
      pre-existing baseline pint debt across untouched files — do not
      mass-fix it in a theme batch; CI does not gate on pint).
- [ ] Active theme reset to `naara-official` and dev server stopped before
      finishing.

---

## 5. Money-safety guardrails that still bind theme work

Theming is presentation only. It must never touch pricing/wallet logic, and
in particular must never surface cost/net figures. Keep these in mind even
in blade:

- **Never render `cost_price_usd` / `net_price`** or any cost/margin field
  in a themed template — user-facing = retail only.
- All prices already flow through `PricingEngine`; don't add price literals
  in a theme.
- Loading states on money actions are a UI rule, not optional.

---

## 6. Quick reference — commands

```bash
# switch the active theme to preview it
php artisan tinker --execute="\App\Models\Setting::setValue(\App\Support\ThemePreset::SETTING_KEY, 'your-slug');"

# rebuild assets before screenshots (mandatory after new arbitrary classes)
npm run build

# full suite
php artisan test

# lint only the files you touched
vendor/bin/pint path/to/file.php

# reset to default when done
php artisan tinker --execute="\App\Models\Setting::setValue(\App\Support\ThemePreset::SETTING_KEY, 'naara-official');"
```

---

*This blueprint is the standing brief for batches 4–8. Update §0's status
line and the "shapes already taken" list in §1 rule 5 after every batch so
the next one never repeats a layout or a divider.*
