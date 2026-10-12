# Guided-tour engine: decision record

Date: 2026-10-12. Status: built (custom engine).

## Options considered
- **Driver.js / Shepherd.js** (MIT, widely used) versus a **custom engine**. I could not check their current licence, release and bundle facts against the
  official repositories from this environment, so they are listed here as unverified rather than asserted.
- Intro.js was ruled out without needing evidence: its terms differ for commercial use and the owner's platform is commercial.

## Decision
A small custom engine (`resources/js/tours/engine.js`, ~300 lines, no dependency):
1. Full brand control (spotlight glow, bottom-anchored mobile card, skin tokens, dark mode) without fighting a library's CSS.
2. The polite-behaviour rules (never during a call, modal, upload or open keyboard; one auto-tour per session; cool-down) and the "skip a missing anchor and report it" rule are
   engine behaviour, not wrappers around someone else's lifecycle.
3. Nothing to licence-check, patch or upgrade; cPanel installs need no extra package.

The engine sits behind one API, `window.NaaraTour.start(key, { mode, resume, auto })`, so it can be swapped for a library later without touching tours, the hub or the admin.

## Where the pieces live
- Data: `tours`, `tour_steps`, `tour_progress`, `tour_user_settings`, `tour_events`; seeded from `config/tours.php` (+ `config/tours_n2n.php`, `config/tours_business_suite.php`, master only).
- Anchors: `data-tour="section.element"` on real elements; the shared composer builds its own DOM and declares its anchors in a `TOUR` map.
- Integrity: `App\Support\Tours\TourIntegrity` (static, runs anywhere), `naara:verify tours` (master), Admin > Guided tours > Check all tours.
- Replay hub: Account > Guided tours. Admin: Admin > Guided tours (Tour Studio).

## Open owner question
"The hub disappears after one month": does that mean the whole page, or only the automatic prompts? One switch decides it (`tours.hub_visibility_after_window` = `hide` | `keep`, default `hide`).
