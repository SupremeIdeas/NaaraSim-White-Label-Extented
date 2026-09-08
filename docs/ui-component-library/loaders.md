# Preloader / spinner snippets (Uiverse.io) — parked reference

Pasted by the owner 2026-09-08 as raw CSS+HTML for later review against the
existing Preloader Studio system (`App\Support\PreloaderSettings`,
`resources/views/components/preloaders/*`). Kept verbatim; scope each and
namespace classnames (`nx-pl--<slug>`) before wiring any of these in, per the
Studio's existing preset convention.

---

## 1. "Loader by Network" (elijahgummer) — vertical equalizer bars

```css
.middle {
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  position: absolute;
}
.bar {
  width: 10px;
  height: 70px;
  display: inline-block;
  transform-origin: bottom center;
  border-top-right-radius: 20px;
  border-top-left-radius: 20px;
  box-shadow: 5px 10px 20px inset rgba(52, 152, 219, 0.8);
  animation: loader 1.2s linear infinite;
}
.bar1 { animation-delay: 0.1s; } .bar2 { animation-delay: 0.2s; }
.bar3 { animation-delay: 0.3s; } .bar4 { animation-delay: 0.4s; }
.bar5 { animation-delay: 0.5s; } .bar6 { animation-delay: 0.6s; }
.bar7 { animation-delay: 0.7s; } .bar8 { animation-delay: 0.8s; }
@keyframes loader {
  0% { transform: scaleY(0.1); background: transparent; }
  50% { transform: scaleY(1); background: #3498db; }
  100% { transform: scaleY(0.1); background: transparent; }
}
```
```html
<div class="middle">
  <div class="bar bar1"></div><div class="bar bar2"></div><div class="bar bar3"></div>
  <div class="bar bar4"></div><div class="bar bar5"></div><div class="bar bar6"></div>
  <div class="bar bar7"></div><div class="bar bar8"></div>
</div>
```

## 2. "Loader by Global" (paesjr) — wifi/signal rings, "Loading..." text swap

```css
#wifi-loader {
  --background: #62abff; --front-color: #ef4d86; --front-color-in: #fbb216;
  --back-color: #c3c8de; --text-color: #414856;
  width: 64px; height: 64px; border-radius: 50px; position: relative;
  display: flex; justify-content: center; align-items: center;
}
#wifi-loader svg { position: absolute; display: flex; justify-content: center; align-items: center; }
#wifi-loader svg circle {
  position: absolute; fill: none; stroke-width: 6px; stroke-linecap: round;
  stroke-linejoin: round; transform: rotate(-100deg); transform-origin: center;
}
#wifi-loader svg circle.back { stroke: var(--back-color); }
#wifi-loader svg circle.front { stroke: var(--front-color); }
#wifi-loader svg.circle-outer { height: 86px; width: 86px; }
#wifi-loader svg.circle-outer circle { stroke-dasharray: 62.75 188.25; }
#wifi-loader svg.circle-outer circle.back { animation: circle-outer135 1.8s ease infinite 0.3s; }
#wifi-loader svg.circle-outer circle.front { animation: circle-outer135 1.8s ease infinite 0.15s; }
#wifi-loader svg.circle-middle { height: 60px; width: 60px; }
#wifi-loader svg.circle-middle circle { stroke: var(--front-color-in); stroke-dasharray: 42.5 127.5; }
#wifi-loader svg.circle-middle circle.back { animation: circle-middle6123 1.8s ease infinite 0.25s; }
#wifi-loader svg.circle-middle circle.front { animation: circle-middle6123 1.8s ease infinite 0.1s; }
#wifi-loader svg.circle-inner { height: 34px; width: 34px; }
#wifi-loader svg.circle-inner circle { stroke-dasharray: 22 66; }
#wifi-loader svg.circle-inner circle.back { animation: circle-inner162 1.8s ease infinite 0.2s; }
#wifi-loader svg.circle-inner circle.front { animation: circle-inner162 1.8s ease infinite 0.05s; }
#wifi-loader .text {
  position: absolute; bottom: -40px; display: flex; justify-content: center;
  align-items: center; text-transform: lowercase; font-weight: 500; font-size: 14px; letter-spacing: 0.2px;
}
#wifi-loader .text::before, #wifi-loader .text::after { content: attr(data-text); }
#wifi-loader .text::before { color: var(--text-color); }
#wifi-loader .text::after {
  color: var(--front-color-in); animation: text-animation76 3.6s ease infinite;
  position: absolute; left: 0;
}
@keyframes circle-outer135 {
  0% { stroke-dashoffset: 25; } 25% { stroke-dashoffset: 0; } 65% { stroke-dashoffset: 301; }
  80% { stroke-dashoffset: 276; } 100% { stroke-dashoffset: 276; }
}
@keyframes circle-middle6123 {
  0% { stroke-dashoffset: 17; } 25% { stroke-dashoffset: 0; } 65% { stroke-dashoffset: 204; }
  80% { stroke-dashoffset: 187; } 100% { stroke-dashoffset: 187; }
}
@keyframes circle-inner162 {
  0% { stroke-dashoffset: 9; } 25% { stroke-dashoffset: 0; } 65% { stroke-dashoffset: 106; }
  80% { stroke-dashoffset: 97; } 100% { stroke-dashoffset: 97; }
}
@keyframes text-animation76 {
  0% { clip-path: inset(0 100% 0 0); } 50% { clip-path: inset(0); } 100% { clip-path: inset(0 0 0 100%); }
}
```
```html
<div id="wifi-loader">
  <svg viewBox="0 0 86 86" class="circle-outer">
    <circle r="40" cy="43" cx="43" class="back"></circle>
    <circle r="40" cy="43" cx="43" class="front"></circle>
    <circle r="40" cy="43" cx="43" class="new"></circle>
  </svg>
  <svg viewBox="0 0 60 60" class="circle-middle">
    <circle r="27" cy="30" cx="30" class="back"></circle>
    <circle r="27" cy="30" cx="30" class="front"></circle>
  </svg>
  <div data-text="Loading..." class="text"></div>
</div>
```

## 3. "Loader by satyam" (satyamchaudharydev) — rotating edge-glow border

```css
.loader {
  --loader: rgb(49, 180, 255); --loader-size: 30px;
  position: relative; width: 100px; height: 40px; overflow: hidden;
  transition: .5s; letter-spacing: 2px
}
.loader span { position: absolute; }
.loader span:nth-child(1) {
  top: 0; left: -100%; width: 100%; height: var(--loader-size);
  background: linear-gradient(90deg, transparent, var(--loader));
  animation: loader-anim1 1s linear infinite;
}
@keyframes loader-anim1 { 0% { left: -100%; } 50%,100% { left: 100%; } }
.loader span:nth-child(2) {
  top: -100%; right: 0; width: var(--loader-size); height: 100%;
  background: linear-gradient(180deg, transparent, var(--loader));
  animation: loader-anim2 1s linear infinite; animation-delay: .25s
}
@keyframes loader-anim2 { 0% { top: -100%; } 50%,100% { top: 100%; } }
.loader span:nth-child(3) {
  bottom: 0; right: -100%; width: 100%; height: var(--loader-size);
  background: linear-gradient(270deg, transparent, var(--loader));
  animation: loader-anim3 1s linear infinite; animation-delay: .5s
}
@keyframes loader-anim3 { 0% { right: -100%; } 50%,100% { right: 100%; } }
.loader span:nth-child(4) {
  bottom: -100%; left: 0; width: var(--loader-size); height: 100%;
  background: linear-gradient(360deg, transparent, var(--loader));
  animation: loader-anim4 1s linear infinite; animation-delay: .75s
}
@keyframes loader-anim4 { 0% { bottom: -100%; } 50%,100% { bottom: 100%; } }
```
```html
<div class="loader"><span></span><span></span><span></span><span></span></div>
```

## 4. "Loader by Z4drus" — spinning/emerging 3D crystals

```css
.container { display: flex; align-items: center; justify-content: center; }
.loader { position: relative; width: 200px; height: 200px; perspective: 800px; }
.crystal {
  position: absolute; top: 50%; left: 50%; width: 60px; height: 60px; opacity: 0;
  transform-origin: bottom center; transform: translate(-50%, -50%) rotateX(45deg) rotateZ(0deg);
  animation: spin 4s linear infinite, emerge 2s ease-in-out infinite alternate, fadeIn 0.3s ease-out forwards;
  border-radius: 10px; visibility: hidden;
}
@keyframes spin {
  from { transform: translate(-50%, -50%) rotateX(45deg) rotateZ(0deg); }
  to { transform: translate(-50%, -50%) rotateX(45deg) rotateZ(360deg); }
}
@keyframes emerge {
  0%, 100% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
  50% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
}
@keyframes fadeIn { to { visibility: visible; opacity: 0.8; } }
.crystal:nth-child(1) { background: linear-gradient(45deg, #003366, #336699); animation-delay: 0s; }
.crystal:nth-child(2) { background: linear-gradient(45deg, #003399, #3366cc); animation-delay: 0.3s; }
.crystal:nth-child(3) { background: linear-gradient(45deg, #0066cc, #3399ff); animation-delay: 0.6s; }
.crystal:nth-child(4) { background: linear-gradient(45deg, #0099ff, #66ccff); animation-delay: 0.9s; }
.crystal:nth-child(5) { background: linear-gradient(45deg, #33ccff, #99ccff); animation-delay: 1.2s; }
.crystal:nth-child(6) { background: linear-gradient(45deg, #66ffff, #ccffff); animation-delay: 1.5s; }
```
```html
<div class="container">
  <div class="loader">
    <div class="crystal"></div><div class="crystal"></div><div class="crystal"></div>
    <div class="crystal"></div><div class="crystal"></div><div class="crystal"></div>
  </div>
</div>
```

## 5. "Loader by Nawsome" — four interlocking Olympic-style rings

```css
.pl { width: 6em; height: 6em; }
.pl__ring { animation: ringA 2s linear infinite; }
.pl__ring--a { stroke: #f42f25; }
.pl__ring--b { animation-name: ringB; stroke: #f49725; }
.pl__ring--c { animation-name: ringC; stroke: #255ff4; }
.pl__ring--d { animation-name: ringD; stroke: #f42582; }
@keyframes ringA {
  from, 4% { stroke-dasharray: 0 660; stroke-width: 20; stroke-dashoffset: -330; }
  12% { stroke-dasharray: 60 600; stroke-width: 30; stroke-dashoffset: -335; }
  32% { stroke-dasharray: 60 600; stroke-width: 30; stroke-dashoffset: -595; }
  40%, 54% { stroke-dasharray: 0 660; stroke-width: 20; stroke-dashoffset: -660; }
  62% { stroke-dasharray: 60 600; stroke-width: 30; stroke-dashoffset: -665; }
  82% { stroke-dasharray: 60 600; stroke-width: 30; stroke-dashoffset: -925; }
  90%, to { stroke-dasharray: 0 660; stroke-width: 20; stroke-dashoffset: -990; }
}
@keyframes ringB {
  from, 12% { stroke-dasharray: 0 220; stroke-width: 20; stroke-dashoffset: -110; }
  20% { stroke-dasharray: 20 200; stroke-width: 30; stroke-dashoffset: -115; }
  40% { stroke-dasharray: 20 200; stroke-width: 30; stroke-dashoffset: -195; }
  48%, 62% { stroke-dasharray: 0 220; stroke-width: 20; stroke-dashoffset: -220; }
  70% { stroke-dasharray: 20 200; stroke-width: 30; stroke-dashoffset: -225; }
  90% { stroke-dasharray: 20 200; stroke-width: 30; stroke-dashoffset: -305; }
  98%, to { stroke-dasharray: 0 220; stroke-width: 20; stroke-dashoffset: -330; }
}
@keyframes ringC {
  from { stroke-dasharray: 0 440; stroke-width: 20; stroke-dashoffset: 0; }
  8% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -5; }
  28% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -175; }
  36%, 58% { stroke-dasharray: 0 440; stroke-width: 20; stroke-dashoffset: -220; }
  66% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -225; }
  86% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -395; }
  94%, to { stroke-dasharray: 0 440; stroke-width: 20; stroke-dashoffset: -440; }
}
@keyframes ringD {
  from, 8% { stroke-dasharray: 0 440; stroke-width: 20; stroke-dashoffset: 0; }
  16% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -5; }
  36% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -175; }
  44%, 50% { stroke-dasharray: 0 440; stroke-width: 20; stroke-dashoffset: -220; }
  58% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -225; }
  78% { stroke-dasharray: 40 400; stroke-width: 30; stroke-dashoffset: -395; }
  86%, to { stroke-dasharray: 0 440; stroke-width: 20; stroke-dashoffset: -440; }
}
```
```html
<svg class="pl" width="240" height="240" viewBox="0 0 240 240">
  <circle class="pl__ring pl__ring--a" cx="120" cy="120" r="105" fill="none" stroke="#000" stroke-width="20" stroke-dasharray="0 660" stroke-dashoffset="-330" stroke-linecap="round"></circle>
  <circle class="pl__ring pl__ring--b" cx="120" cy="120" r="35" fill="none" stroke="#000" stroke-width="20" stroke-dasharray="0 220" stroke-dashoffset="-110" stroke-linecap="round"></circle>
  <circle class="pl__ring pl__ring--c" cx="85" cy="120" r="70" fill="none" stroke="#000" stroke-width="20" stroke-dasharray="0 440" stroke-linecap="round"></circle>
  <circle class="pl__ring pl__ring--d" cx="155" cy="120" r="70" fill="none" stroke="#000" stroke-width="20" stroke-dasharray="0 440" stroke-linecap="round"></circle>
</svg>
```

## 6. "Loader by ahmedyasserdev" — pulsing rotated cube grid

```css
.loading-container { display: flex; justify-content: center; align-items: center; height: 100%; }
.loader {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; width: 80px; height: 80px;
  transform: rotate(45deg); animation: rotateLoader 2s cubic-bezier(0.6, 0.2, 0.1, 1) infinite;
}
.cube {
  width: 35px; height: 35px; background: linear-gradient(145deg, #00e4ff, #006aff);
  border-radius: 12px;
  box-shadow: 0 0 12px rgba(0, 228, 255, 0.6), inset 0 0 8px rgba(0, 228, 255, 0.8), inset 3px 3px 8px rgba(0, 50, 120, 0.4);
  animation: pulse 1.6s ease-in-out infinite; transition: transform 0.4s ease;
}
@keyframes pulse {
  0%, 100% { transform: scale(1); box-shadow: 0 0 15px rgba(0, 228, 255, 0.7), inset 0 0 8px rgba(0, 228, 255, 0.8); }
  50% { transform: scale(1.3); box-shadow: 0 0 25px rgba(0, 228, 255, 1), inset 0 0 12px rgba(0, 228, 255, 1); }
}
@keyframes rotateLoader { 0% { transform: rotate(45deg); } 50% { transform: rotate(225deg); } 100% { transform: rotate(405deg); } }
.cube:nth-child(1) { animation-delay: 0s; } .cube:nth-child(2) { animation-delay: 0.2s; }
.cube:nth-child(3) { animation-delay: 0.4s; } .cube:nth-child(4) { animation-delay: 0.6s; }
```
```html
<div class="loading-container">
  <div class="loader"><div class="cube"></div><div class="cube"></div><div class="cube"></div><div class="cube"></div></div>
</div>
```

## 7. "Loader by Avishek255874" — radial burst/blast dots

```css
.loader { width: 48px; height: 48px; position: relative; }
.loader::before, .loader::after {
  content: ''; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
  width: 48em; height: 48em;
  background-image: radial-gradient(circle 10px, #FFF 100%, transparent 0),
    radial-gradient(circle 10px, #FFF 100%, transparent 0), radial-gradient(circle 10px, #FFF 100%, transparent 0),
    radial-gradient(circle 10px, #FFF 100%, transparent 0), radial-gradient(circle 10px, #FFF 100%, transparent 0),
    radial-gradient(circle 10px, #FFF 100%, transparent 0), radial-gradient(circle 10px, #FFF 100%, transparent 0),
    radial-gradient(circle 10px, #FFF 100%, transparent 0);
  background-position: 0em -18em, 0em 18em, 18em 0em, -18em 0em, 13em -13em, -13em -13em, 13em 13em, -13em 13em;
  background-repeat: no-repeat; font-size: 0.5px; border-radius: 50%; animation: blast 1s ease-in infinite;
}
.loader::after { font-size: 1px; background: #fff; animation: bounce 1s ease-in infinite; }
@keyframes bounce { 0%, 100% { font-size: 0.75px } 50% { font-size: 1.5px } }
@keyframes blast { 0%, 40% { font-size: 0.5px; } 70% { opacity: 1; font-size: 4px; } 100% { font-size: 6px; opacity: 0; } }
```
```html
<div class="loader"></div>
```

## 8. "Loader by Pradeepsaranbishnoi" — 3D-perspective expanding rings

```css
.loader {
  position: absolute; top: 50%; left: 50%; width: 200px; height: 200px;
  margin-top: -100px; margin-left: -100px; perspective: 600px; transform-style: preserve-3d;
}
.dot {
  position: absolute; top: 50%; left: 50%; width: 100px; height: 100px;
  margin-top: -50px; margin-left: -50px; border-radius: 100px; border: 20px solid #1e3f57;
  transform-style: preserve-3d; transform: scale(0) rotateX(60deg);
  animation: dot 3s cubic-bezier(.67,.08,.46,1.5) infinite;
}
.dot:nth-child(2) { animation-delay: 200ms; } .dot:nth-child(3) { animation-delay: 400ms; }
.dot:nth-child(4) { animation-delay: 600ms; } .dot:nth-child(5) { animation-delay: 800ms; }
.dot:nth-child(6) { animation-delay: 1000ms; } .dot:nth-child(7) { animation-delay: 1200ms; }
.dot:nth-child(8) { animation-delay: 1400ms; }
@keyframes dot {
  0% { opacity: 0; border-color: #6bb2cd; transform: rotateX(60deg) rotateY(45deg) translateZ(-100px) scale(0.1); }
  40% { opacity: 1; transform: rotateX(0deg) rotateY(20deg) translateZ(0) scale(1); }
  100% { opacity: 0; transform: rotateX(60deg) rotateY(-45deg) translateZ(-100px) scale(0.1); }
}
```
```html
<div class="loader">
  <div class="dot"></div><div class="dot"></div><div class="dot"></div><div class="dot"></div>
  <div class="dot"></div><div class="dot"></div><div class="dot"></div><div class="dot"></div>
</div>
```

## 9. "Loader by JkHuger" — single pulsing dot

```css
.loader-pulse { width: 64px; height: 64px; border-radius: 50%; background: #8f44fd; animation: load-pulse 0.85s infinite linear; }
@keyframes load-pulse {
  0% { transform: scale(0.15); opacity: 0; } 50% { opacity: 1; } 100% { transform: scale(1); opacity: 0; }
}
```
```html
<div class="item"><div class="loader-pulse"></div></div>
```

## 10. "Loader by SouravBandyopadhyay" — animated heart/square/shadow (CSS Loader classic)

Full vendor-prefixed keyframe set for a rotating heart shape splitting into two
lobes over a square, with a pulsing ground shadow. See chat history 2026-09-08
for the complete CSS (`.cssload-main`, `.cssload-heart`, `.cssload-heartL/R`,
`.cssload-square`, `.cssload-shadow` + `@keyframes cssload-*` with -o-/-ms-/
-webkit-/-moz- prefixes) — omitted here for length; retrieve from the session
transcript if this one gets shortlisted.

## 11. "Loader by Satwinder04" — 3x3 diagonal scaling dot grid

```css
.loader-container { display: flex; justify-content: center; align-items: center; }
.loader { display: flex; justify-content: center; align-items: center; position: relative; transform: rotate(45deg); }
.loader-inner {
  position: absolute; width: 0.5rem; height: 0.5rem; border-radius: 50%;
  background-color: #db3434; animation: loader_05101 1.2s linear infinite;
}
.loader-inner:nth-child(1) { top: 0; left: 0; animation-delay: 0s; }
.loader-inner:nth-child(2) { top: 0; left: 1.5rem; animation-delay: 0.1s; }
.loader-inner:nth-child(3) { top: 0; left: 3rem; animation-delay: 0.2s; }
.loader-inner:nth-child(4) { top: 1.5rem; left: 0; animation-delay: 0.3s; }
.loader-inner:nth-child(5) { top: 1.5rem; left: 1.5rem; animation-delay: 0.4s; }
.loader-inner:nth-child(6) { top: 1.5rem; left: 3rem; animation-delay: 0.5s; }
.loader-inner:nth-child(7) { top: 3rem; left: 0; animation-delay: 0.6s; }
.loader-inner:nth-child(8) { top: 3rem; left: 1.5rem; animation-delay: 0.7s; }
.loader-inner:nth-child(9) { top: 3rem; left: 3rem; animation-delay: 0.8s; }
@keyframes loader_05101 { 0% { transform: scale(0); } 100% { transform: scale(2); opacity: 0; } }
```
```html
<div class="loader-container">
  <div class="loader">
    <div class="loader-inner"></div><div class="loader-inner"></div><div class="loader-inner"></div>
    <div class="loader-inner"></div><div class="loader-inner"></div><div class="loader-inner"></div>
    <div class="loader-inner"></div><div class="loader-inner"></div><div class="loader-inner"></div>
  </div>
</div>
```

## 12. "Innovative Loader by Andrew" (andrew-manzyk) — glowing sphere + animated wave-mask SVG

Complex layered sphere (radial-gradient highlight + rotating hue-shifted
inset-shadow rings) with an SVG `<mask>`-driven "waves" pattern morphing via
`d` path keyframes. See session transcript for the full CSS/SVG — this one is
GPU-moderate (multiple blur filters + path animation) and would need a
performance check before adoption, in the same spirit as the react-bits
WebGL heroes.

## 13. "Loading loader by asgardOP" — sliding bar + typed "Loading..." dots

```css
.loader { width: 100px; height: 3px; background-color: rgb(15, 15, 15); border-radius: 20px; overflow: hidden; }
.child {
  width: 60px; height: 3px; background-color: rgb(107, 27, 255); border-radius: 20px;
  z-index: 0; margin-left: -60px; animation: go 1s 0s infinite;
}
@keyframes go { from { margin-left: -100px; width: 80px; } to { width: 30px; margin-left: 110px; } }
.text { width: 100px; height: 30px; background-color: transparent; margin-top: 20px; text-align: center; }
.text::before { content: "Loading"; color: white; animation: text 1s 0s infinite; }
@keyframes text {
  0% { content: "Loading"; } 30% { content: "Loading."; } 60% { content: "Loading.."; } 100% { content: "Loading..."; }
}
```
```html
<ul>
  <li><div class="loader"><div class="child"></div></div></li>
  <li><div class="text"></div></li>
</ul>
```

## 14. "Loader by Bethel-nz" — animated multi-color worm along an SVG path

Uses `pathLength`-based `stroke-dashoffset` animation over a decorative worm-
shaped SVG path plus a static background ring — genuinely distinctive (looks
like a snake/loading-glyph tracing a shape). See session transcript for the
full CSS/SVG (`.pl`, `.pl__ring`, `.pl__worm`, `@keyframes bump9`/`worm9`).

## 15. Premium customizable-text loaders (JeremGamingYT "Aura", SelfMadeSystem "You", Lissy2019)

Three SVG-path-morphing loader variants built for react-bits, each drawing a
custom animated glyph/logotype via `stroke-dasharray`/`stroke-dashoffset`
"dash" + "spin" keyframes with linear-gradient strokes. Explicitly flagged by
the owner as **premium and wished to be customizable beyond just color** — i.e.
the actual SVG path (currently a fixed logotype per snippet) would need to be
swappable, not just the gradient stops. The comment thread on these notes:
"you'll have to change the path of the elements — it's just a few animated
SVGs." Good long-term candidate for an admin-uploadable "your own animated
wordmark" preloader tier, but that's a real feature (SVG path validation +
storage), not a preset swap — scope separately if pursued. Full code in the
session transcript.
