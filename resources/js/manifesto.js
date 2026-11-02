// Homepage manifesto scroll-word-reveal (owner request, 2026-09-21 — "that
// reveal text on scroll... show case in hero after the flags carousel").
// Ported verbatim from the owner-supplied reference's §4 Manifesto mechanic:
// words light up one by one as the paragraph scrolls through the viewport,
// driven by real scroll position rather than a fixed timer.
export function mountManifesto(root) {
    const words = root.querySelectorAll('.nx-manifesto-word');
    if (!words.length) return () => {};

    let started = false;
    const update = () => {
        const rect = root.getBoundingClientRect();
        const wh = window.innerHeight;
        let progress = (wh - rect.top) / (rect.height + wh);
        progress = Math.max(0, Math.min(1, progress * 1.5));
        const lit = Math.floor(progress * words.length);
        words.forEach((w, i) => w.classList.toggle('is-lit', i < lit));
    };

    const io = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !started) {
            started = true;
            window.addEventListener('scroll', update, { passive: true });
            update();
        }
    }, { threshold: 0.05 });
    io.observe(root);

    return () => {
        io.disconnect();
        window.removeEventListener('scroll', update);
    };
}
