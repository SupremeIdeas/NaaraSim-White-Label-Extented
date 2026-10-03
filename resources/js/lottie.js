// Lottie animations, self-hosted and CSP-safe (no lottie.host / unpkg CDN).
// The runtime AND each animation JSON are code-split into their own chunks and
// only fetched on pages that actually render a `[data-lottie]` element, so the
// main bundle stays lean. Everything is reduced-motion aware.
//
// Usage (Blade): <x-lottie name="gift-preloader" class="h-40 w-40" />

// Static map so Vite can code-split each animation into its own lazy chunk.
const REGISTRY = {
    'gift-preloader': () => import('./animations/gift-preloader.json'),
    reward: () => import('./animations/reward.json'),
    // BUILD-4 merchant/rewards/referral visuals (self-hosted dotLottie exports).
    'refer-earn': () => import('./animations/refer-earn.json'),
    'rewards-confetti': () => import('./animations/rewards-confetti.json'),
    'merchant-v1-badge': () => import('./animations/merchant-v1-badge.json'),
    'merchant-v2-badge': () => import('./animations/merchant-v2-badge.json'),
    'merchant-hero': () => import('./animations/merchant-hero.json'),
};

function mount(lottie, el) {
    // A bundled animation (REGISTRY) or an admin-uploaded one served from our own origin (`data-lottie-src`, e.g. the custom preloader).
    const src = el.dataset.lottieSrc;
    const loader = src
        ? () => fetch(src, { credentials: 'omit' }).then((r) => (r.ok ? r.json() : Promise.reject(new Error('lottie ' + r.status)))).then((json) => ({ default: json }))
        : REGISTRY[el.dataset.lottie];
    if (!loader) { el.removeAttribute('data-lottie-ready'); return; }
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    loader().then((mod) => {
        const anim = lottie.loadAnimation({
            container: el,
            renderer: 'svg',
            loop: el.dataset.lottieLoop !== 'false',
            autoplay: !reduce && el.dataset.lottieAutoplay !== 'false',
            animationData: mod.default,
        });
        // Reduced motion: show the settled last frame, no looping motion.
        if (reduce) {
            anim.addEventListener('DOMLoaded', () => anim.goToAndStop(Math.max(0, anim.totalFrames - 1), true));
        }
        el.__lottie = anim; // handle for teardown
    }).catch(() => { el.removeAttribute('data-lottie-ready'); });
}

export function initLottie() {
    const nodes = document.querySelectorAll('[data-lottie]:not([data-lottie-ready])');
    if (!nodes.length) return;
    // Claim the nodes now: the runtime import is async, and a second trigger (DOMContentLoaded + nx:lottie-init) must not hydrate them again.
    nodes.forEach((el) => el.setAttribute('data-lottie-ready', ''));
    // Pull the runtime once, lazily, then hydrate every pending node.
    import('lottie-web').then(({ default: lottie }) => nodes.forEach((el) => mount(lottie, el)));
}

// Run on first paint and after every Livewire SPA navigation.
document.addEventListener('DOMContentLoaded', initLottie);
document.addEventListener('livewire:navigated', initLottie);
// The custom preloader picks its light/dark file in an inline script, then asks for a hydrate.
document.addEventListener('nx:lottie-init', initLottie);
