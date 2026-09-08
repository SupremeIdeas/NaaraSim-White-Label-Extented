import './bootstrap';
import './lottie';
import { initImageCompression } from './image-compress';
import { registerVoiceRecorder } from './support-voice';
import { registerNiaChat } from './nia-chat';
import { registerStorytellingCarousel } from './storytelling-carousel';
import { registerUsageCharts } from './usage-chart';
import { registerLinesAnalyticsCharts } from './lines-analytics-charts';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// Browser-side image compression on every upload surface (BUILD-11 §2). Bound
// once at document level, so it survives SPA navigation without re-binding.
initImageCompression();

// Real in-browser voice-note recording for NaaraCare chat (BUILD-3 §3).
registerVoiceRecorder();

// Nia 3-phase human-conversation pacing for NaaraCare chat (BUILD-3 §5).
registerNiaChat();

// Storytelling Carousel (BLUEPRINT-batch1-sections §3) — Alpine component +
// shared section-nav store, registered on alpine:init before Livewire boots it.
registerStorytellingCarousel();

// Per-eSIM usage chart on My Line (Connectivity Analytics blueprint Part A
// §2.6) — registers window.NaaraUsageCharts.mount(); Chart.js itself is only
// dynamic-imported the first time a user actually opens a usage panel.
registerUsageCharts();

// "My Analytics" panel on My Line (Connectivity Analytics blueprint Part A
// §2.4/2.7) — registers window.NaaraLinesAnalytics.mountAll(); shares the
// same lazy Chart.js chunk as the per-eSIM usage chart above.
registerLinesAnalyticsCharts();

// Alpine is provided by Livewire 3's bundled build (do not start a second
// Alpine instance here — Livewire injects and starts it globally).

// --- GSAP premium motion (Module 27.5) ---------------------------------------
// Bundled into our own JS via npm (no CDN, CSP-safe). Everything respects
// prefers-reduced-motion and degrades to the IntersectionObserver reveals.
function initGsapCraft() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // 1. Apple-style hero media: slow parallax scale as you scroll away.
    const heroMedia = document.querySelector('[data-hero-media]');
    if (heroMedia) {
        gsap.fromTo(heroMedia, { scale: 1.06, yPercent: 0 }, {
            scale: 1, yPercent: 8, ease: 'none',
            scrollTrigger: { trigger: heroMedia, start: 'top top', end: 'bottom top', scrub: true },
        });
    }

    // 2. Dynamic content switch on scroll: the products section pins while its
    //    panels crossfade in sequence. Pinning + scrub is expensive and janky on
    //    mobile, so it is DESKTOP-ONLY (>=1024px); phones/tablets get a cheap
    //    IntersectionObserver reveal per panel instead — no scroll-jacking.
    const pin = document.querySelector('[data-products-pin]');
    if (pin) {
        const panels = pin.querySelectorAll('[data-product-panel]');
        if (panels.length > 1) {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                pin.classList.add('gsap-pin'); // overlap panels only once GSAP owns them
                gsap.set(panels, { autoAlpha: 0, y: 24 });
                gsap.set(panels[0], { autoAlpha: 1, y: 0 });

                const tl = gsap.timeline({
                    scrollTrigger: {
                        trigger: pin,
                        start: 'top top',
                        end: () => '+=' + panels.length * 90 + '%',
                        pin: true,
                        scrub: 0.4,
                    },
                });
                panels.forEach((panel, i) => {
                    if (i === 0) return;
                    tl.to(panels[i - 1], { autoAlpha: 0, y: -24, duration: 1 }, i)
                      .fromTo(panel, { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, duration: 1 }, i + 0.15);
                });
            } else {
                // Mobile: light reveal, no pin/scrub.
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((e) => {
                        if (e.isIntersecting) {
                            gsap.fromTo(e.target, { autoAlpha: 0, y: 20 }, { autoAlpha: 1, y: 0, duration: 0.5, ease: 'power2.out' });
                            io.unobserve(e.target);
                        }
                    });
                }, { threshold: 0.2 });
                panels.forEach((p) => io.observe(p));
            }
        }
    }

    // 3. Timeline draw: the progress rail fills as steps pass.
    const timeline = document.querySelector('[data-timeline]');
    if (timeline) {
        const rail = timeline.querySelector('[data-timeline-rail]');
        if (rail) {
            gsap.fromTo(rail, { scaleY: 0 }, {
                scaleY: 1, transformOrigin: 'top center', ease: 'none',
                scrollTrigger: { trigger: timeline, start: 'top 70%', end: 'bottom 55%', scrub: true },
            });
        }
    }

    // 4. Stat count-up (hero stats row).
    document.querySelectorAll('[data-countup]').forEach((el) => {
        const target = parseFloat(el.dataset.countup);
        if (Number.isNaN(target)) return;
        const suffix = el.dataset.suffix || '';
        gsap.fromTo(el, { innerText: 0 }, {
            innerText: target, duration: 1.6, ease: 'power2.out', snap: { innerText: 1 },
            onUpdate() { el.textContent = Math.round(parseFloat(el.textContent || '0')) + suffix; },
            scrollTrigger: { trigger: el, start: 'top 88%', once: true },
        });
    });
}

document.addEventListener('DOMContentLoaded', initGsapCraft);
document.addEventListener('livewire:navigated', () => {
    ScrollTrigger.getAll().forEach((t) => t.kill());
    initGsapCraft();
});

// --- Marketing scroll-craft (Module 27) -------------------------------------
// Three tiny IntersectionObservers, no external libraries (CSP-safe):
//  1. [data-reveal]        -> .is-revealed  (text/image reveal on scroll)
//  2. [data-bg="..."]      -> swaps a scene class on the .mkt-bg wrapper
//                             (background colour change on scroll)
//  3. [data-hero-sentinel] -> shows the sticky CTA once the hero scrolls away
function initScrollCraft() {
    const revealables = document.querySelectorAll('[data-reveal]:not(.is-revealed)');
    if (revealables.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-revealed');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.15 });
        revealables.forEach((el) => io.observe(el));
    }

    const bgWrapper = document.querySelector('.mkt-bg');
    const scenes = document.querySelectorAll('[data-bg]');
    if (bgWrapper && scenes.length) {
        const sceneClasses = ['bg-navy-scene', 'bg-teal-scene'];
        const bgIo = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    sceneClasses.forEach((c) => bgWrapper.classList.remove(c));
                    const scene = e.target.dataset.bg;
                    if (scene && scene !== 'light') bgWrapper.classList.add(`bg-${scene}-scene`);
                }
            });
        }, { threshold: 0.4 });
        scenes.forEach((el) => bgIo.observe(el));
    }

}

document.addEventListener('DOMContentLoaded', initScrollCraft);
document.addEventListener('livewire:navigated', initScrollCraft);

// --- Premium WebGL login scene (lazy) ---------------------------------------
// Three.js is dynamic-imported ONLY when the auth panel canvas is present, so it
// never lands in the main bundle. Skipped for reduced-motion, where the CSS orb
// fallback stays. Cleaned up on SPA navigation.
let _loginSceneDestroy = null;
function initLoginScene() {
    if (_loginSceneDestroy) { _loginSceneDestroy(); _loginSceneDestroy = null; }
    const canvas = document.querySelector('[data-webgl="login"]');
    if (!canvas) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    import('./webgl/login-scene.js')
        .then(({ mountLoginScene }) => {
            _loginSceneDestroy = mountLoginScene(canvas);
            canvas.classList.add('is-live'); // fades the canvas in over the fallback
        })
        .catch(() => { /* bundle/WebGL failure → CSS fallback stays */ });
}

document.addEventListener('DOMContentLoaded', initLoginScene);
document.addEventListener('livewire:navigated', initLoginScene);

// --- Marketing page WebGL heroes (lazy) -------------------------------------
// One scene per page: each hero canvas carries data-webgl-hero="<variant>".
// Three.js is dynamic-imported (shared with the login chunk), skipped for
// reduced-motion, and torn down on SPA navigation. Each scene self-pauses when
// it scrolls off-screen, so it costs nothing once you've scrolled past it.
let _heroDestroyers = [];
function initHeroScenes() {
    _heroDestroyers.forEach((d) => d());
    _heroDestroyers = [];
    const canvases = document.querySelectorAll('[data-webgl-hero]');
    if (!canvases.length) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    import('./webgl/hero-scene.js')
        .then(({ mountHeroScene }) => {
            canvases.forEach((canvas) => {
                const destroy = mountHeroScene(canvas, canvas.dataset.webglHero);
                _heroDestroyers.push(destroy);
                canvas.classList.add('is-live'); // fades the canvas in over the fallback
            });
        })
        .catch(() => { /* bundle/WebGL failure → CSS fallback stays */ });
}

document.addEventListener('DOMContentLoaded', initHeroScenes);
document.addEventListener('livewire:navigated', initHeroScenes);

// --- In-browser dialer (Live Voice — Part B, lazy) --------------------------
// The Twilio Voice SDK is heavy + only needed on the dialer page, so the module
// (and the SDK it imports) is dynamic-imported only when [data-dialer] is on the
// page. Bundled via Vite — never a CDN — and re-initialised on SPA navigation.
function initDialer() {
    const root = document.querySelector('[data-dialer]');
    if (!root || root.dataset.dialerMounted) return;
    root.dataset.dialerMounted = '1';

    import('./dialer.js')
        .then(({ mountDialer }) => mountDialer(root))
        .catch(() => { /* SDK/bundle failure → the money path is untouched */ });
}

document.addEventListener('DOMContentLoaded', initDialer);
document.addEventListener('livewire:navigated', initDialer);
