# react-bits / bespoke component catalog — parked reference

Pasted by the owner 2026-09-08. Two different sources, handled differently:

- **Most of these are published `react-bits` components** (react-bits.dev),
  each with its own `npx shadcn@latest add @react-bits/<Name>-JS-CSS` install
  command shown below — the full current source is one command away whenever
  one is actually adopted, so it isn't hand-vendored here (their source runs
  hundreds of lines each of Three.js/OGL/WebGL shader code; re-typing it now
  would just drift from upstream). **This is a React component library —
  this codebase is Blade/Livewire/Alpine with no React runtime**, so adopting
  any of these means either (a) porting the logic to vanilla JS/Alpine, or
  (b) mounting a small isolated React island just for that one widget. Don't
  pull in a whole React runtime for one hero background.
- The **3D Book Showcase** is bespoke (owner-supplied/found elsewhere, not a
  react-bits package) — no install command, React + Three.js, ~600+ lines.
  Full source is in this session's transcript (2026-09-08) if it gets
  shortlisted; not re-vendored here for the same reason as above.

## Catalog

| Component | Install | What it does | Owner's stated candidate use |
|---|---|---|---|
| **3D Book Showcase** | (bespoke, not npm) | Three.js interactive 3D book covers with hover/open animation, procedural or image-based covers, chapter index page | Not yet mapped to a NaaraSim surface — review before use |
| **Light Tunnel** | `npm install ogl` (custom component, code in transcript) | OGL/WebGL animated "fiber optic tunnel" with pulses, mouse interaction | Hero background candidate |
| **Option Wheel** | `npx shadcn@latest add @react-bits/OptionWheel-JS-CSS` | Draggable/scrollable curved vertical option picker (like a slot-machine list), optional tick sound | Possible picker UI (country/plan selection?) — not yet mapped |
| **Scroll Expand** | `npx shadcn@latest add @react-bits/ScrollExpand-JS-CSS` | A framed image/video that expands full-bleed as the user scrolls past it, title fades out, overlay content fades in | Marketing page hero section candidate |
| **Card Swap** | `npx shadcn@latest add @react-bits/CardSwap-JS-CSS` | GSAP-driven stacked card auto-rotation (front card drops back, others promote) | Feature/testimonial carousel candidate |
| **Circular Gallery** | `npx shadcn@latest add @react-bits/CircularGallery-JS-CSS` | OGL curved/bent horizontal image gallery with drag/wheel/keyboard scroll, per-image title labels, optional custom web font | Brand Directory or gift-card catalogue gallery candidate |
| **Color Bends** | `npx shadcn@latest add @react-bits/ColorBends-JS-CSS` | Three.js animated flowing color-band shader background, mouse-reactive | Hero background candidate |
| **Dome Gallery** | `npx shadcn@latest add @react-bits/DomeGallery-JS-CSS` | Drag-to-rotate 3D dome of images (CSS 3D transforms, not WebGL) with click-to-enlarge | Alternative gallery treatment to Circular Gallery |
| **Gooey Nav** | `npx shadcn@latest add @react-bits/GooeyNav-JS-CSS` | Nav with a gooey/liquid metaball particle-burst transition between active tab states | Owner named it for **"ZornBelladon platform"** — not a name found anywhere in this codebase; confirm with owner what this refers to before assuming it's NaaraSim's own nav |
| **Gradual Blur** | `npx shadcn@latest add @react-bits/GradualBlur-JS-CSS` | Configurable edge-fade blur overlay (top/bottom/left/right/page-header/footer presets) for scrollable content | Polish candidate for long scrollable panels (e.g. API logs, invoice lists) |
| **Laser Flow** | (custom component, code in transcript) | Three.js volumetric "laser beam + fog + fiber wisps" shader, mouse-reactive, includes an interactive image-reveal mask pattern | Hero background / premium-feature showcase candidate |
| **Magic Rings** | `npx shadcn@latest add @react-bits/MagicRings-JS-CSS` | Three.js animated concentric glowing rings, hover-scale + optional click burst | Hero section accent candidate |
| **Magnet Lines** | `npx shadcn@latest add @react-bits/MagnetLines-JS-CSS` | Grid of lines that all point toward the cursor (pure CSS transform + JS pointer tracking, no WebGL — cheap) | Lightweight decorative background, good performance profile |
| **Plasma Wave** | (custom component, code in transcript) | OGL raymarched plasma-tube shader background | Owner: "best for AI hero bg" — candidate for an AI/Claude-feature-adjacent section |
| **Prismatic Burst** | (custom component, code in transcript) | Three.js raymarched multi-ray prism/burst shader with 3 animation modes (rotate, rotate3d, hover-follow) | Alternative hero background |

## Performance note (binding on any future adoption)

Several of these are WebGL/Three.js/OGL shader components running a continuous
`requestAnimationFrame` loop. The preloader hang fixed 2026-09-08
(`resources/views/components/brand-preloader.blade.php` — see PROGRESS.md) is
the concrete lesson here: an animation that seems "just visual" can silently
cost real page-responsiveness if it isn't gated. Before shipping any of these:

1. Pause the render loop when the element scrolls off-screen
   (`IntersectionObserver`, same pattern the Book Showcase snippet already
   uses for `isVisible`/`tryStart`/`tryStop`).
2. Pause on `document.hidden` (`visibilitychange`), same pattern.
3. Respect `prefers-reduced-motion` — skip straight to the static end state.
4. Cap device-pixel-ratio (most of these already do, e.g. `Math.min(dpr, 2)`)
   — never render at full DPR on a hero background.
5. Never load one of these ahead of the actual page content — lazy-mount
   after first paint, same principle as the `wire:navigate`/preloader fix.
