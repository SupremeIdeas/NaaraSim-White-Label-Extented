# NAARA BUILD 12: HOMEPAGE "WHO NAARA IS FOR" AUDIENCE-TABS SECTION
**Run after NAARA-BUILD-6 (Page Builder publish fix) is verified working — this section is
built as a real, admin-orderable homepage section through that system, not a hardcoded insert.**
Give this whole file to Claude Code as one message.

---

## 0. HOW TO WORK
- Inspect `resources/views/marketing/home.blade.php` and its `resources/views/marketing/home/`
  section partials before building anything — the homepage already renders through an
  admin-configurable `$sections` loop (`@includeIf('marketing.home.'.$key, ['s' => $s])`), with
  only `_flags` and `_wizard` as fixed-anchor decorative exceptions. This new section is **not**
  a fixed anchor — it's a real, admin-orderable section like `products`, `features`, `how`, etc.
- Consult `/mnt/skills/public/frontend-design/SKILL.md` before writing any component.
- Commit in logical stages — clean rollback points.
- This file is self-contained — do not open or reference any other file for context.

---

## 1. PLACEMENT

Register a new section key — `audiences` — into the same section-registry mechanism the
existing homepage sections (`hero`, `products`, `features`, `how`, `coverage`, `pricing`,
`testimonials`, `faq`, `cta`) already use. Default position: **between `products` and
`features`** — a visitor has just seen what Naara sells, this section immediately answers "is
this for someone like me," before the more detailed feature/how-it-works content. Since the
section order is already admin-configurable, this is a sensible default, not a hard requirement
— confirm the admin can reorder it afterward the same as any other section, exactly as intended.

---

## 2. THE TIMING — VERIFIED, NOT GUESSED

Frank's instinct of 12 seconds per tab was checked against actual word counts for all six
panels (headline + body + "Best for" line): they range from 33–41 words each, which at a
realistic 180–200 words-per-minute reading pace for marketing copy works out to roughly
10–14 seconds per panel. **12 seconds sits almost exactly in the middle of that range across
every panel** — confirmed correct, use it as a flat duration for all six tabs rather than
over-engineering a per-panel dynamic timer; the variance between panels (33 vs. 41 words) is
small enough that a single consistent duration reads naturally for all of them.

---

## 3. COMPONENT SPEC — TAB-BASED, PROGRESS-BAR-DRIVEN SWITCHER

1. Six tabs, one per audience (§4), auto-advancing every **12 seconds**.
2. Each tab's trigger shows a thin progress bar that fills left-to-right using Naara's brand
   gradient (reuse the exact gradient definition already used elsewhere in this codebase — e.g.
   the same one behind the Nia glow effect from NAARA-BUILD-3 — don't invent a new gradient),
   animating smoothly across the full 12 seconds, then resetting as the next tab activates.
3. **Manual interaction always takes priority over the auto-advance:** tapping/clicking a
   different tab jumps straight to it and restarts its own 12-second timer from zero — never
   fights the user's own navigation.
4. **Pause on hover (desktop) and on touch-hold/focus (mobile/keyboard)** — this is both good UX
   and an accessibility requirement (auto-advancing content that can't be paused is a real
   WCAG concern) — resume the timer from where it left off, not from zero, once
   hover/focus/touch ends.
5. **Keyboard accessible:** arrow-key navigation between tabs, visible focus states, and the
   progress-bar animation respects `prefers-reduced-motion` (fall back to a simple non-animated
   active-state indicator instead of the traveling bar for users with that preference set).
6. **Mobile layout:** on small screens, the tab triggers should not force six cramped labels
   into one row — either a horizontally scrollable tab strip or a simplified swipeable card
   layout (swipe to move between audiences, same 12-second auto-advance and progress indicator
   logic applies either way) — whichever reads cleaner at actual phone width; build and
   eyeball both before committing to one.
7. Each tab's content panel: the image (§5) on one side, headline + body copy + "Best for" tag
   row on the other (stack vertically on mobile, side-by-side on desktop) — image swaps with a
   smooth crossfade/transition on tab change, not an abrupt cut.
8. This section, like every other homepage section, goes through the Page Builder image-hint
   system from NAARA-BUILD-10 §4 — if a future audience is added or an image needs replacing,
   admin should be able to do it from the panel, with the same "recommended dimensions" hint
   shown for this section's specific image slot (landscape, matching the ~1280×768 proportion of
   the images provided now).

---

## 4. THE SIX AUDIENCES — CONTENT, VERBATIM

Use this content exactly as written — this is finished, approved copy, not a draft to rephrase:

1. **International Travelers** — *"Travel Smarter. Stay Connected Everywhere."* — From weekend
   getaways to round-the-world adventures, Naara keeps you connected in over 190 countries with
   instant eSIM activation, affordable data plans, and reliable mobile services before you even
   land. **Best for:** eSIM • Travel Data • Regional Plans
2. **Business Professionals** — *"Power Your Business Across Borders"* — Run your business from
   anywhere with reliable mobile connectivity, dedicated virtual numbers, secure verification
   services, and communication tools designed for modern professionals expanding beyond one
   country. **Best for:** Virtual Numbers • eSIM • Verification Numbers
3. **Digital Entrepreneurs & Freelancers** — *"Build Without Boundaries"* — Whether you're
   serving international clients, working remotely, or launching digital products, Naara
   provides the connectivity and communication tools you need to work confidently from anywhere.
   **Best for:** eSIM • Virtual Numbers • Verification Numbers
4. **Creators, Influencers & Digital Nomads** — *"Create Without Losing Connection"* — Travel,
   stream, upload, collaborate, and engage your audience from anywhere. Naara keeps your content
   flowing with premium mobile data, flexible phone numbers, and dependable connectivity
   worldwide. **Best for:** eSIM • Virtual Numbers
5. **Privacy & Online Security** — *"Protect Your Identity Online"* — Keep your personal number
   private while registering for services, managing online accounts, verifying platforms, or
   communicating professionally using secure virtual and verification numbers. **Best for:**
   Verification Numbers • Virtual Numbers
6. **Families & Global Communities** — *"Stay Close Across Every Border"* — Whether you're
   visiting loved ones, supporting family abroad, or sending digital gifts across continents,
   Naara helps people stay connected through affordable connectivity and instant digital
   services. **Best for:** eSIM • Gift Cards • Virtual Numbers

---

## 5. IMAGE MAPPING

Seven images were provided (all 1246–1280px wide × 768px, landscape) for six tabs — mapped by
closest semantic fit below, with one spare left for admin to swap in later via the image-hint
system rather than discarded:

| Audience | Image file |
|---|---|
| International Travelers | `naara-leisure-traveler.webp` |
| Business Professionals | `naara-business-professionals.webp` |
| Digital Entrepreneurs & Freelancers | `naara-software-engineer.webp` |
| Creators, Influencers & Digital Nomads | `naara-travel-creator.webp` |
| Privacy & Online Security | `naara-secure-professional.webp` |
| Families & Global Communities | `naara-staying-connected.webp` |
| *(spare, unused — available in admin for a future swap)* | `naara-business-traveler.webp` |

Route all six through `MediaStorage` (and NAARA-BUILD-11's compression pipeline once that's
live) — do not reference the raw uploaded files directly from a public path.

---

## 6. WHEN THIS PROMPT IS DONE
- [ ] New `audiences` section registered into the existing admin-orderable homepage section system, defaulted between `products` and `features`, confirmed reorderable by admin like any other section
- [ ] Six tabs auto-advance at a flat 12 seconds each, with a brand-gradient progress bar (reusing the existing gradient definition, not a new one)
- [ ] Manual tab interaction always overrides auto-advance and restarts that tab's timer from zero
- [ ] Pause-on-hover/focus/touch-hold implemented and resumes from where it left off, not from zero
- [ ] Keyboard-navigable, visible focus states, `prefers-reduced-motion` respected with a non-animated fallback indicator
- [ ] Mobile layout tested as both a scrollable tab strip and a swipeable card approach, cleaner option kept
- [ ] All six panels use the approved copy verbatim, correctly mapped to their images per §5, images routed through `MediaStorage`
- [ ] `docs/PLATFORM-STATE.md` updated noting this build's completion and that one spare image (`naara-business-traveler.webp`) is available in admin for future use
