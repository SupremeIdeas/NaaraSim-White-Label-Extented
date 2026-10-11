// Floating-layer manager (Engineering v3 Phase 1E). Publishes the real heights of the sticky header and the bottom dock as CSS variables
// (--nx-header-h, --nx-dock-h) so page padding follows the layout instead of a guessed number, and flags `html.nx-kbd` while a text field
// is focused on a phone so floating buttons get out of the way. `window.nxLayers.audit()` returns every visible text element that a fixed
// layer is covering: the Phase 0.6 / Gate 1 overlap assertion uses it.
const DOCK = '.nx-bottomnav, .nx-floatnav';
const HEADER = 'header[data-nx-header], header.nx-header-fade, .nx-hdr, body > header';
let raf = 0;

function measure() {
    raf = 0;
    const root = document.documentElement;
    const vh = window.innerHeight;
    let dock = 0;
    document.querySelectorAll(DOCK).forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.width > 0 && r.height > 0 && getComputedStyle(el).display !== 'none') dock = Math.max(dock, vh - r.top);
    });
    let head = 0;
    const h = document.querySelector(HEADER);
    if (h) { const r = h.getBoundingClientRect(); if (r.height > 0) head = Math.round(r.bottom > 0 ? r.bottom : 0); }
    root.style.setProperty('--nx-dock-h', Math.max(0, Math.round(dock)) + 'px');
    root.style.setProperty('--nx-header-h', head + 'px');
}

function schedule() { if (!raf) raf = requestAnimationFrame(measure); }

const isText = (el) => el instanceof HTMLElement && (el.matches('textarea, input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=file]):not([type=range])') || el.isContentEditable);

function audit() {
    const fixed = [...document.querySelectorAll('*')].filter((e) => {
        const cs = getComputedStyle(e);
        return cs.position === 'fixed' && cs.visibility !== 'hidden' && cs.display !== 'none' && Number(cs.opacity) > 0.05 && cs.pointerEvents !== 'none' && e.getBoundingClientRect().width > 0 && !e.matches('[data-nx-upload-host], [data-nx-upload-host] *');
    });
    const out = [];
    document.querySelectorAll('main p, main h1, main h2, main h3, main label, main button, main a, main input, main textarea, main td').forEach((t) => {
        if (fixed.some((f) => f.contains(t))) return;
        const r = t.getBoundingClientRect();
        if (r.width < 4 || r.height < 4 || r.bottom < 0 || r.top > window.innerHeight) return;
        for (const f of fixed) {
            const fr = f.getBoundingClientRect();
            const ix = Math.min(r.right, fr.right) - Math.max(r.left, fr.left), iy = Math.min(r.bottom, fr.bottom) - Math.max(r.top, fr.top);
            if (ix > 6 && iy > 6 && ix * iy > 0.3 * r.width * r.height) { out.push({ text: (t.textContent || '').trim().slice(0, 40), by: (f.className || f.tagName).toString().slice(0, 50) }); break; }
        }
    });
    return out;
}

export function registerNxLayers() {
    if (window.nxLayers) return;
    window.nxLayers = { measure, audit };
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('orientationchange', schedule);
    document.addEventListener('livewire:navigated', schedule);
    document.addEventListener('DOMContentLoaded', schedule);
    if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(schedule);
        const watch = () => document.querySelectorAll(DOCK + ', ' + HEADER).forEach((el) => ro.observe(el));
        watch(); document.addEventListener('livewire:navigated', watch);
    }
    new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('focusin', (e) => { if (isText(e.target) && window.innerWidth < 1024) document.documentElement.classList.add('nx-kbd'); });
    document.addEventListener('focusout', () => setTimeout(() => { if (!isText(document.activeElement)) document.documentElement.classList.remove('nx-kbd'); }, 60));
    schedule();
}
