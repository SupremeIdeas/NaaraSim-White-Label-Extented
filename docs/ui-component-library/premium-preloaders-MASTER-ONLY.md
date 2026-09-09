# Premium preloaders — MASTER (original NaaraSim) ONLY — parked for Preloader Studio

> ⛔ **MASTER-REPO-ONLY. These must NEVER be merged into any white-label copy.**
> When these get wired as Preloader Studio presets (a later pass, after the
> license batch), gate them so a white-label fork never receives them — the
> cleanest hook is the same "am I the original platform?" signal the rest of
> the codebase already uses (`config('updater.product_identifier') ===
> 'naarasim-core'`, which every fork flips to `naarasim-whitelabel`). Do NOT
> rely on the doc itself staying out of the forks — the white-labels merge
> FROM master, so anything added to master flows to them on the next sync
> unless it's explicitly product-gated. The preset partials + registry entries
> must carry that gate; this doc is just the parked source.
>
> This also dovetails with the license batch (Batch 8): "preloader
> customization" is a tier-locked feature for white-label subscribers, so a
> basic-tier fork gets no preloader customization at all, and these specific
> premium loaders are the original platform's alone.

Owner-supplied 2026-09-08 (Supreme Ideas Agency curated set). Saved verbatim;
namespace each as `nx-pl--<slug>` and adapt colors to brand CSS vars before
wiring, per the existing Studio preset convention
(`resources/views/components/preloaders/*` + `resources/css/preloaders.css` +
`App\Support\PreloaderSettings::PRESETS`). Add a `prefers-reduced-motion`
fallback to each (the Studio's blanket `.nx-pl * { animation: none }` rule
already covers most).

---

## 1. "Generating" letters (dexter-st) — Supreme Ideas Agency best loader

Animated letters spelling a word (e.g. "Generating") with a radial rainbow
color-sweep masked behind them. Wordmark is customizable — swap the letters.

```css
.loader-wrapper {
  position: relative; display: flex; align-items: center; justify-content: center;
  height: 120px; width: auto; margin: 2rem;
  font-family: "Poppins", sans-serif; font-size: 1.6em; font-weight: 600;
  user-select: none; color: #140e08; scale: 2;
}
.loader {
  position: absolute; top: 0; left: 0; height: 100%; width: 100%; z-index: 1;
  background-color: transparent;
  mask: repeating-linear-gradient(90deg, transparent 0, transparent 6px, black 6px, black 9px);
}
.loader::after {
  content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
  background-image: radial-gradient(circle at 50% 50%, #ff0 0%, transparent 50%),
    radial-gradient(circle at 45% 45%, #f00 0%, transparent 45%),
    radial-gradient(circle at 55% 55%, #0ff 0%, transparent 45%),
    radial-gradient(circle at 45% 55%, #0f0 0%, transparent 45%),
    radial-gradient(circle at 55% 45%, #00f 0%, transparent 45%);
  mask: radial-gradient(circle at 50% 50%, transparent 0%, transparent 10%, black 25%);
  animation: transform-animation 2s infinite alternate, opacity-animation 4s infinite;
  animation-timing-function: cubic-bezier(0.6, 0.8, 0.5, 1);
}
@keyframes transform-animation { 0% { transform: translate(-55%); } 100% { transform: translate(55%); } }
@keyframes opacity-animation { 0%,100% { opacity: 0; } 15% { opacity: 1; } 65% { opacity: 0; } }
.loader-letter { display: inline-block; opacity: 0; animation: loader-letter-anim 4s infinite linear; z-index: 2; }
.loader-letter:nth-child(1){animation-delay:.1s}.loader-letter:nth-child(2){animation-delay:.205s}
.loader-letter:nth-child(3){animation-delay:.31s}.loader-letter:nth-child(4){animation-delay:.415s}
.loader-letter:nth-child(5){animation-delay:.521s}.loader-letter:nth-child(6){animation-delay:.626s}
.loader-letter:nth-child(7){animation-delay:.731s}.loader-letter:nth-child(8){animation-delay:.837s}
.loader-letter:nth-child(9){animation-delay:.942s}.loader-letter:nth-child(10){animation-delay:1.047s}
@keyframes loader-letter-anim {
  0% { filter: blur(0px); opacity: 0; }
  5% { opacity: 1; text-shadow: 0 0 4px #8d8379; filter: blur(0px); transform: scale(1.1) translateY(-2px); }
  20% { opacity: 0.2; filter: blur(0px); }
  100% { filter: blur(5px); opacity: 0; }
}
```
```html
<div class="loader-wrapper">
  <span class="loader-letter">G</span><span class="loader-letter">e</span><span class="loader-letter">n</span>
  <span class="loader-letter">e</span><span class="loader-letter">r</span><span class="loader-letter">a</span>
  <span class="loader-letter">t</span><span class="loader-letter">i</span><span class="loader-letter">n</span>
  <span class="loader-letter">g</span>
  <div class="loader"></div>
</div>
```

## 2. Hue-rotating spoke spinner (andrew-demchenk0)

12 rounded spokes fading + hue-rotating in sequence. Set the spoke color via the
`background` in `.loader_item:after`.

```css
.loader { display: inline-block; position: relative; width: 80px; height: 80px; }
.loader .loader_item { transform-origin: 40px 40px; animation: spinner 1.2s linear infinite; }
.loader .loader_item:after {
  content: " "; display: block; position: absolute; top: 3px; left: 37px;
  width: 6px; height: 18px; border-radius: 20%; background: green; /* swap to brand */
}
.loader .loader_item:nth-child(1){transform:rotate(0deg);animation-delay:-1.1s}
.loader .loader_item:nth-child(2){transform:rotate(30deg);animation-delay:-1s}
.loader .loader_item:nth-child(3){transform:rotate(60deg);animation-delay:-.9s}
.loader .loader_item:nth-child(4){transform:rotate(90deg);animation-delay:-.8s}
.loader .loader_item:nth-child(5){transform:rotate(120deg);animation-delay:-.7s}
.loader .loader_item:nth-child(6){transform:rotate(150deg);animation-delay:-.6s}
.loader .loader_item:nth-child(7){transform:rotate(180deg);animation-delay:-.5s}
.loader .loader_item:nth-child(8){transform:rotate(210deg);animation-delay:-.4s}
.loader .loader_item:nth-child(9){transform:rotate(240deg);animation-delay:-.3s}
.loader .loader_item:nth-child(10){transform:rotate(270deg);animation-delay:-.2s}
.loader .loader_item:nth-child(11){transform:rotate(300deg);animation-delay:-.1s}
.loader .loader_item:nth-child(12){transform:rotate(330deg);animation-delay:0s}
@keyframes spinner { 0% { opacity: 1; filter: hue-rotate(0deg); } 100% { opacity: 0; filter: hue-rotate(360deg); } }
```
```html
<div class="loader">
  <div class="loader_item"></div><!-- ×12 --></div>
```

## 3. Gooey blob dots (Sourcesketch)

Three colored dots merging/splitting through an SVG `feGaussianBlur`+`feColorMatrix`
goo filter, rotating. Needs the inline `<svg>` filter def.

```css
.container {
  width: 200px; height: 200px; position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%); margin: auto; filter: url("#goo");
  animation: rotate-move 2s ease-in-out infinite;
}
.dot { width: 70px; height: 70px; border-radius: 50%; background-color: #000; position: absolute; inset: 0; margin: auto; }
.dot-3 { background-color: #ff1717; animation: dot-3-move 2s ease infinite, index 6s ease infinite; }
.dot-2 { background-color: #0051ff; animation: dot-2-move 2s ease infinite, index 6s -4s ease infinite; }
.dot-1 { background-color: #ffc400; animation: dot-1-move 2s ease infinite, index 6s -2s ease infinite; }
@keyframes dot-3-move { 20%{transform:scale(1)} 45%{transform:translateY(-18px) scale(.45)} 60%{transform:translateY(-90px) scale(.45)} 80%{transform:translateY(-90px) scale(.45)} 100%{transform:translateY(0) scale(1)} }
@keyframes dot-2-move { 20%{transform:scale(1)} 45%{transform:translate(-16px,12px) scale(.45)} 60%{transform:translate(-80px,60px) scale(.45)} 80%{transform:translate(-80px,60px) scale(.45)} 100%{transform:translateY(0) scale(1)} }
@keyframes dot-1-move { 20%{transform:scale(1)} 45%{transform:translate(16px,12px) scale(.45)} 60%{transform:translate(80px,60px) scale(.45)} 80%{transform:translate(80px,60px) scale(.45)} 100%{transform:translateY(0) scale(1)} }
@keyframes rotate-move { 55%{transform:translate(-50%,-50%) rotate(0)} 80%{transform:translate(-50%,-50%) rotate(360deg)} 100%{transform:translate(-50%,-50%) rotate(360deg)} }
@keyframes index { 0%,100%{z-index:3} 33.3%{z-index:2} 66.6%{z-index:1} }
```
```html
<div class="container"><div class="dot dot-1"></div><div class="dot dot-2"></div><div class="dot dot-3"></div></div>
<svg version="1.1" xmlns="http://www.w3.org/2000/svg"><defs><filter id="goo">
  <feGaussianBlur result="blur" stdDeviation="10" in="SourceGraphic"></feGaussianBlur>
  <feColorMatrix values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 21 -7" mode="matrix" in="blur"></feColorMatrix>
</filter></defs></svg>
```

## 4. Glowing neon half-rings with reflection (omriluz)

Two neon half-rings + two glowing dot-orbs, rotating + hue-cycling, with a
CSS `-webkit-box-reflect` mirror below. Heavy glow — flag as a "Heavy" preset.

```css
.container {
  position: relative; width: 100%; height: 200px; display: flex; align-items: center; justify-content: center;
  -webkit-box-reflect: below 0 linear-gradient(transparent, transparent, #0005);
}
.container .loader { position: absolute; width: 200px; height: 200px; border-radius: 50%; animation: animate 2s linear infinite; }
.container .loader:nth-child(2), .container .loader:nth-child(4) { animation-delay: -1s; }
@keyframes animate { 0%{transform:rotate(0);filter:hue-rotate(360deg)} 100%{transform:rotate(360deg);filter:hue-rotate(0deg)} }
.container .loader:nth-child(1)::before, .container .loader:nth-child(2)::before {
  content:''; position:absolute; top:0; left:0; width:50%; height:100%;
  background: linear-gradient(to top, transparent, rgba(0,255,249,0.4));
  background-size: 100px 180px; background-repeat: no-repeat;
  border-top-left-radius: 100px; border-bottom-left-radius: 100px;
}
.container .loader i {
  position:absolute; top:0; left:50%; transform:translateX(-50%);
  width:20px; height:20px; background:#00fff9; border-radius:50%; z-index:100;
  box-shadow: 0 0 10px #00fff9,0 0 30px #00fff9,0 0 40px #00fff9,0 0 50px #00fff9,0 0 60px #00fff9,0 0 70px #00fff9,0 0 80px #00fff9,0 0 90px #00fff9,0 0 100px #00fff9;
}
.container .loader span { position:absolute; inset:20px; background:#e8e8e8; border-radius:50%; z-index:1; }
```
```html
<div class="container">
  <div class="loader"><span></span></div><div class="loader"><span></span></div>
  <div class="loader"><i></i></div><div class="loader"><i></i></div>
</div>
```

## 5. Fintech breathing SVG (csemszepp)

An SVG circle "breathing" while two small dots scale up huge and back — reads as
a clean fintech mark. Uses a `--higru` custom prop for the dot color.

```css
.loader { width: 200px; max-height: 900px; transform-origin: 50% 50%; overflow: visible; }
.ci1 { fill: var(--higru); animation: toBig 3s infinite -1.5s; transform-box: fill-box; transform-origin: 50% 50%; }
.ciw { transform-box: fill-box; transform-origin: 50% 50%; animation: breath 3s infinite; }
.ci2 { fill: var(--higru); animation: toBig2 3s infinite; transform-box: fill-box; transform-origin: 50% 50%; }
.points { animation: rot 3s infinite; transform-box: fill-box; transform-origin: 50% 50%; }
@keyframes rot { 0%{transform:rotate(0)} 30%{transform:rotate(360deg)} 50%{transform:rotate(360deg)} 80%{transform:rotate(0)} 100%{transform:rotate(0)} }
@keyframes toBig { 0%{transform:scale(1) translateX(0)} 30%{transform:scale(1) translateX(0)} 50%{transform:scale(10) translateX(-4.5px)} 80%{transform:scale(10) translateX(-4.5px)} 100%{transform:scale(1) translateX(0)} }
@keyframes toBig2 { 0%{transform:scale(1) translateX(0)} 30%{transform:scale(1) translateX(0)} 50%{transform:scale(10) translateX(4.5px)} 80%{transform:scale(10) translateX(4.5px)} 100%{transform:scale(1) translateX(0)} }
@keyframes breath { 15%{transform:scale(1)} 40%{transform:scale(1.1)} 65%{transform:scale(1)} 90%{transform:scale(1.1)} }
```
```html
<svg viewBox="0 0 100 100" class="loader">
  <g class="points">
    <circle fill="#fff" r="50" cy="50" cx="50" class="ciw"></circle>
    <circle r="4" cy="50" cx="5" class="ci2"></circle>
    <circle r="4" cy="50" cx="95" class="ci1"></circle>
  </g>
</svg>
```

## 6. 3D MacBook (Ashon-G) — "laptop related" contexts

Full CSS 3D MacBook that opens, rotates, and shows a screen shade sweep — a
showpiece loader for device/eSIM-install contexts. Large markup (an 80-key
keyboard) + heavy transform animation; definitely a "Heavy" preset, gate it off
for reduced-motion. Full CSS + HTML retrievable from the 2026-09-08 session
transcript (too long to re-vendor inline here — ~350 lines); key classes:
`.macbook`/`.inner`/`.screen`/`.macbody`/`.keyboard .key` with `@keyframes`
`rotate`/`lid-screen`/`lid-macbody`/`screen-shade`/`keys`/`shadow`.

## 7. Flying files page loader (nawinasokan)

Purple "file" cards flying left-to-right in a staggered stream. Good for a
document/export/processing context.

```css
.loader-con { position: relative; width: 50%; height: 100px; overflow: hidden; }
.pfile {
  position: absolute; bottom: 25px; width: 40px; height: 50px;
  background: linear-gradient(90deg, #b324db, #ac8dcb); border-radius: 4px;
  transform-origin: center; animation: flyRight 3s ease-in-out infinite; opacity: 0;
  animation-delay: calc(var(--i) * 0.6s);
}
.pfile::before { content:""; position:absolute; top:6px; left:6px; width:28px; height:4px; background:#fff; border-radius:2px; }
.pfile::after  { content:""; position:absolute; top:13px; left:6px; width:18px; height:4px; background:#fff; border-radius:2px; }
@keyframes flyRight { 0%{left:-10%;transform:scale(0);opacity:0} 50%{left:45%;transform:scale(1.2);opacity:1} 100%{left:100%;transform:scale(0);opacity:0} }
```
```html
<div class="loader-con">
  <div style="--i:0" class="pfile"></div><div style="--i:1" class="pfile"></div><div style="--i:2" class="pfile"></div>
  <div style="--i:3" class="pfile"></div><div style="--i:4" class="pfile"></div><div style="--i:5" class="pfile"></div>
</div>
```

## 8. Gradient-fill hover button (zjssun) — NOT a preloader

Included in the same paste but this is a **button hover treatment**, not a
loader — a stroked-text button that fills with an animated rainbow gradient on
hover. Park it as a candidate premium CTA style for the marketing pages / theme
kit, not a Preloader Studio preset.

```css
.button {
  position: relative; border: none; background: transparent;
  --stroke-color: #ffffff7c; --ani-color: rgba(95, 3, 244, 0);
  --color-gar: linear-gradient(90deg,#03a9f4,#f441a5,#ffeb3b,#03a9f4);
  letter-spacing: 3px; font-size: 2em; font-family: "Arial"; text-transform: uppercase;
  color: transparent; -webkit-text-stroke: 1px var(--stroke-color); cursor: pointer;
}
.front-text {
  position:absolute; top:0; left:0; width:0%; background: var(--color-gar);
  -webkit-background-clip: text; background-clip: text; background-size: 200%;
  overflow: hidden; transition: all 1s; animation: 8s ani infinite; border-bottom: 2px solid transparent;
}
.button:hover .front-text { width: 100%; border-bottom: 2px solid #03a9f4; -webkit-text-stroke: 1px var(--ani-color); }
@keyframes ani { 0%{background-position:0%} 50%{background-position:400%} 100%{background-position:0%} }
```
```html
<button class="button" data-text="Awesome">
  <span class="actual-text">&nbsp;uiverse&nbsp;</span>
  <span aria-hidden="true" class="front-text">&nbsp;uiverse&nbsp;</span>
</button>
```
