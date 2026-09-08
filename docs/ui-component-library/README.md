# UI Component Library — parked for later wiring

Owner dropped a batch of Uiverse.io / react-bits components (2026-09-08) to review
and selectively wire into the official Naara theme, marketing pages, and
recommendation-style sections. **Nothing here is wired into the app yet** — this is
a reference dump, saved verbatim per the owner's explicit instruction to park it
for a later pass rather than build against it now.

## Files

- `loaders.md` — 15 CSS/HTML preloader/spinner snippets (Uiverse.io). Candidate
  replacements/additions for the Preloader Studio preset library once the
  preloader system overhaul (see PROGRESS.md) is scoped. None of these are wired
  yet — Preloader Studio already has its own preset system
  (`App\Support\PreloaderSettings`, `resources/views/components/preloaders/`);
  any of these that get adopted should become new presets there, not a parallel
  system.
- `react-components.md` — React/Three.js/OGL components (3D book showcase, light
  tunnel, option wheel, scroll-expand, card swap, circular gallery, color bends,
  dome gallery, gooey nav, gradual blur, laser flow, magic rings, magnet lines,
  plasma wave, prismatic burst). These are React components; this codebase is
  Blade/Livewire/Alpine — each would need a vanilla-JS/Alpine port (or a
  contained web component) before it could be used anywhere, they are not
  drop-in. Owner's stated use case: hero backgrounds (Plasma Wave, Prismatic
  Burst, Magic Rings), the eventual Naara marketplace-style gallery pages
  (Circular Gallery, Dome Gallery, Card Swap), nav (Gooey Nav for "ZornBelladon"
  — a named platform/brand not yet identified in this codebase, confirm with
  owner before assuming it means NaaraSim's own nav), and general polish
  (Gradual Blur, Scroll Expand) on marketing pages.

## Next step (not started)

When picked up: for each candidate, (1) confirm it solves a real, currently-ungood
section rather than replacing something already fine, (2) port to
Alpine.js/vanilla JS + Tailwind (no React runtime exists in this app), (3) verify
performance cost — several of these (Three.js/OGL/WebGL heroes) are expensive and
must never block the pages they sit on; the preloader-hang bug fixed 2026-09-08
(`resources/views/components/brand-preloader.blade.php`) is a cautionary example
of a "small" animation choice silently costing seconds on every page. Any GL/canvas
hero must have an IntersectionObserver gate (pause off-screen) and a
prefers-reduced-motion fallback, matching the existing `RM` check pattern already
used in the 3D book showcase snippet.
