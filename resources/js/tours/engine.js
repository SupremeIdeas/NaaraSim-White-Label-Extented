// NaaraTour: the guided-tour engine (custom, no dependency: full brand control and nothing to licence or keep patched).
// One public API so it can be swapped: window.NaaraTour.start(key, { mode: 'full'|'quick', resume: bool, auto: bool }).
//
// Polite by construction: it never auto-starts during a call, a modal, an upload, with the keyboard up, or more than once per session;
// a missing anchor is skipped and reported (never breaks the page); every tour is dismissible; progress is saved on the server so a hard
// reload or a navigation resumes. Z-order sits below toasts and the call screen, above page content and the dock.

const Z = 70;
const reduce = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
const isMobile = () => window.innerWidth < 640;

async function api(url, opts = {}) {
    const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, ...opts });
    if (!r.ok) throw new Error('tour ' + r.status);
    return r.json();
}
const post = (key, body) => api(`/tours/${encodeURIComponent(key)}/progress`, { method: 'POST', body: JSON.stringify(body), keepalive: true }).catch(() => {});

function visible(el) {
    if (!el || !el.getClientRects().length) return false;
    const c = getComputedStyle(el);
    return c.visibility !== 'hidden' && c.display !== 'none' && parseFloat(c.opacity) !== 0;
}
const find = (anchor) => [...document.querySelectorAll(`[data-tour="${CSS.escape(anchor)}"]`)].find(visible) || null;

// The page-load overlay must be gone first: a tour drawn under it is invisible and steals nothing but trust.
const ready = async () => { for (let i = 0; i < 60 && document.getElementById('nx-preloader'); i++) await new Promise((r) => setTimeout(r, 150)); };

function blockers() {
    // Another modal / call / upload / open keyboard means: not now.
    const modal = [...document.querySelectorAll('[aria-modal="true"], .ns-modal, [data-keep-reading-root], [data-blog-share-root]')].some((e) => !e.closest('#naara-tour') && visible(e));
    const upload = !!document.querySelector('[data-uploading="true"], .nx-upload.is-busy');
    const kb = window.visualViewport && window.visualViewport.height < window.innerHeight * 0.75;
    return modal || upload || !!kb;
}

class Tour {
    constructor(data, opts) {
        this.d = data; this.opts = opts; this.i = 0; this.root = null; this.leaving = false; this.raf = 0; this.lastFocus = document.activeElement;
        this.steps = [];
    }

    async run() {
        this.mount();
        this.i = Math.min(this.opts.resume ? this.d.resume_at || 0 : 0, Math.max(0, this.d.steps.length - 1));
        // Nothing on this screen for this person (e.g. no workspace yet): say so and leave, rather than "finishing" an empty tour.
        const first = await this.resolve(this.i, 1);
        if (!first) {
            this.close();
            window.dispatchEvent(new CustomEvent('nx-toast', { detail: { type: 'info', message: 'This tour has nothing to show on your account yet.' } }));
            return;
        }
        await post(this.d.key, { event: 'started', mode: this.d.mode });
        await this.show(first.i, 1);
    }

    mount() {
        this.root = document.createElement('div');
        this.root.id = 'naara-tour';
        this.root.setAttribute('data-tour-root', '');
        this.root.style.cssText = `position:fixed;inset:0;z-index:${Z};pointer-events:none`;
        this.root.innerHTML = `
          <div class="nt-block" style="position:fixed;inset:0;pointer-events:auto;background:transparent"></div>
          <div class="nt-spot" aria-hidden="true"></div>
          <div class="nt-card" role="dialog" aria-modal="true" aria-labelledby="nt-title" tabindex="-1">
            <div class="nt-top"><span class="nt-step" id="nt-step"></span><button type="button" class="nt-x" aria-label="Close tour">&times;</button></div>
            <h2 class="nt-title" id="nt-title"></h2><p class="nt-body" id="nt-body"></p>
            <div class="nt-dots" aria-hidden="true"></div>
            <div class="nt-foot"><button type="button" class="nt-skip">Skip tour</button><span class="nt-sp"></span><button type="button" class="nt-back">Back</button><button type="button" class="nt-next">Next</button></div>
            <label class="nt-never" hidden><input type="checkbox"> Don't show tips again</label>
            <div class="nt-live" aria-live="polite" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)"></div>
          </div>`;
        document.body.appendChild(this.root);
        this.card = this.root.querySelector('.nt-card');
        this.spot = this.root.querySelector('.nt-spot');
        this.card.style.pointerEvents = 'auto';
        this.q = (s) => this.root.querySelector(s);
        this.q('.nt-next').onclick = () => this.next();
        this.q('.nt-back').onclick = () => this.back();
        this.q('.nt-skip').onclick = () => this.exit('skipped');
        this.q('.nt-x').onclick = () => this.confirmExit();
        if (this.opts.auto) this.q('.nt-never').hidden = false;
        this.onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); this.confirmExit(); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); this.next(); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); this.back(); }
            else if (e.key === 'Tab') this.trap(e);
        };
        document.addEventListener('keydown', this.onKey, true);
        this.onMove = () => { cancelAnimationFrame(this.raf); this.raf = requestAnimationFrame(() => this.place()); };
        addEventListener('resize', this.onMove); addEventListener('scroll', this.onMove, true);
    }

    trap(e) {
        const f = [...this.card.querySelectorAll('button, input')].filter((x) => !x.hidden && !x.closest('[hidden]'));
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        else if (!this.card.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
    }

    // Find the next step (from index i, moving by dir) whose element can be shown on this screen; skip and report the rest.
    async resolve(i, dir) {
        const n = this.d.steps.length;
        for (; i >= 0 && i < n; i += dir) {
            const s = this.d.steps[i];
            if ((s.viewport === 'mobile' && !isMobile()) || (s.viewport === 'desktop' && isMobile())) continue;
            if (s.before_click) { const b = find(s.before_click); if (b) { b.click(); await new Promise((r) => setTimeout(r, 380)); } }
            let el = find(s.anchor);
            if (!el && !s.optional) { await new Promise((r) => setTimeout(r, 250)); el = find(s.anchor); }   // an optional step that is not there is simply not for this person
            if (el) return { i, el };
            if (!s.optional) post(this.d.key, { event: 'missing', anchor: s.anchor });
            else post(this.d.key, { event: 'skipped_step', step: i, anchor: s.anchor });
        }
        return null;
    }

    async show(i, dir) {
        const r = await this.resolve(i, dir);
        if (!r) { return dir > 0 ? this.finish() : this.show(0, 1); }
        this.i = r.i; this.el = r.el;
        const s = this.d.steps[this.i];
        const total = this.d.steps.length;
        this.q('#nt-title').textContent = s.title; this.q('#nt-body').textContent = s.body;
        this.q('#nt-step').textContent = `Step ${this.i + 1} of ${total}`;
        this.q('.nt-dots').innerHTML = this.d.steps.map((_, k) => `<i class="${k === this.i ? 'on' : ''}"></i>`).join('');
        this.q('.nt-back').style.visibility = this.i === 0 ? 'hidden' : 'visible';
        this.q('.nt-next').textContent = this.i === total - 1 ? 'Finish' : 'Next';
        this.q('.nt-live').textContent = `Step ${this.i + 1} of ${total}: ${s.title}`;
        this.keepInView();
        this.place();
        this.card.focus({ preventScroll: true });
        post(this.d.key, { event: 'step', step: this.i, anchor: s.anchor });
    }

    // Bring the target into the part of the screen the card does not cover (phones: card is at the top or bottom, opposite the target).
    keepInView() {
        const r = this.el.getBoundingClientRect(); const vh = window.innerHeight;
        const fixed = getComputedStyle(this.el).position === 'fixed' || !!this.el.closest('nav.fixed, header.fixed, [class*="fixed"]');
        if (fixed) return;
        const margin = isMobile() ? 220 : 120;
        if (r.top < margin || r.bottom > vh - margin) this.el.scrollIntoView({ block: 'center', behavior: reduce() ? 'auto' : 'smooth' });
        setTimeout(() => this.place(), reduce() ? 0 : 380);
    }

    place() {
        if (!this.el || !this.root || this.finished) return;
        const r = this.el.getBoundingClientRect(); const pad = 8; const vw = innerWidth, vh = innerHeight;
        // A target that fills most of the screen (a whole conversation list) gets no cut-out: the page is just dimmed and the card sits clear of it.
        const big = r.width > vw * 0.6 && r.height > vh * 0.6;
        this.spot.style.display = big ? 'none' : 'block';
        this.q('.nt-block').style.background = big ? 'rgb(0 0 0 / .45)' : 'transparent';
        Object.assign(this.spot.style, { left: r.left - pad + 'px', top: r.top - pad + 'px', width: r.width + pad * 2 + 'px', height: r.height + pad * 2 + 'px' });
        const c = this.card; c.classList.toggle('nt-mobile', isMobile());
        if (isMobile()) {
            const topHalf = big || r.top + r.height / 2 < vh / 2;   // target high on screen -> card at the bottom; target low (e.g. the bottom nav) -> card on top
            c.style.left = '12px'; c.style.right = '12px'; c.style.width = 'auto';
            c.style.top = topHalf ? 'auto' : 'calc(env(safe-area-inset-top, 0px) + 12px)';
            c.style.bottom = topHalf ? 'calc(env(safe-area-inset-bottom, 0px) + 12px)' : 'auto';
            return;
        }
        c.style.right = 'auto'; c.style.bottom = 'auto'; c.style.width = '340px';
        const ch = c.offsetHeight, cw = 340, gap = 16;
        const s = this.d.steps[this.i]; let pl = s.placement;
        if (big) pl = 'center';
        if (pl === 'auto' || !pl) pl = (vh - r.bottom > ch + gap + 8) ? 'bottom' : (r.top > ch + gap + 8) ? 'top' : (vw - r.right > cw + gap + 8) ? 'right' : 'left';
        let x, y;
        if (pl === 'bottom') { x = r.left + r.width / 2 - cw / 2; y = r.bottom + gap; }
        else if (pl === 'top') { x = r.left + r.width / 2 - cw / 2; y = r.top - ch - gap; }
        else if (pl === 'right') { x = r.right + gap; y = r.top + r.height / 2 - ch / 2; }
        else if (pl === 'left') { x = r.left - cw - gap; y = r.top + r.height / 2 - ch / 2; }
        else { x = (vw - cw) / 2; y = (vh - ch) / 2; }
        c.style.left = Math.max(12, Math.min(vw - cw - 12, x)) + 'px'; c.style.top = Math.max(12, Math.min(vh - ch - 12, y)) + 'px';
    }

    // One move at a time: while a step is being found (or a tab opened for it) further Next/Back presses are ignored, never queued.
    async go(fn) { if (this.busy || this.finished) return; this.busy = true; try { await fn(); } finally { this.busy = false; } }
    next() { return this.go(() => (this.i >= this.d.steps.length - 1 ? this.finish() : this.show(this.i + 1, 1))); }
    back() { return this.go(() => (this.i > 0 ? this.show(this.i - 1, -1) : null)); }

    confirmExit() {
        if (this.i === 0 && !this.leaving) return this.exit('dismissed');
        if (this.confirming) return; this.confirming = true;
        const f = this.q('.nt-foot'); const prev = f.innerHTML;
        f.innerHTML = `<span class="nt-ask">Leave the tour?</span><span class="nt-sp"></span><button type="button" class="nt-back nt-stay">Keep going</button><button type="button" class="nt-next nt-leave">Leave</button>`;
        f.querySelector('.nt-stay').onclick = () => { f.innerHTML = prev; this.confirming = false; this.rebind(); };
        f.querySelector('.nt-leave').onclick = () => this.exit('dismissed');
        f.querySelector('.nt-stay').focus();
    }
    rebind() {
        this.q('.nt-next').onclick = () => this.next(); this.q('.nt-back').onclick = () => this.back(); this.q('.nt-skip').onclick = () => this.exit('skipped');
        this.q('.nt-back').style.visibility = this.i === 0 ? 'hidden' : 'visible'; this.q('.nt-next').textContent = this.i === this.d.steps.length - 1 ? 'Finish' : 'Next';
    }

    finish() {
        const hub = this.d.hub;
        post(this.d.key, { event: 'completed' });
        this.spot.style.display = 'none';
        this.q('.nt-top').innerHTML = '<span class="nt-step" id="nt-step">All done</span><button type="button" class="nt-x" aria-label="Close">&times;</button>';
        this.q('#nt-title').textContent = "You're set";
        this.q('#nt-body').textContent = 'Replay this tour any time in Settings, Guided tours.';
        this.q('.nt-dots').innerHTML = '';
        this.q('.nt-foot').innerHTML = `${hub ? `<a class="nt-link" href="${hub}">Guided tours</a>` : ''}<span class="nt-sp"></span><button type="button" class="nt-next nt-done">Done</button>`;
        this.q('.nt-never').hidden = true;
        this.q('.nt-x').onclick = () => this.close();
        this.q('.nt-done').onclick = () => this.close();
        this.card.style.top = 'auto'; this.card.style.bottom = isMobile() ? '12px' : '24px'; this.card.style.left = isMobile() ? '12px' : 'auto'; this.card.style.right = '12px';
        this.finished = true; this.q('.nt-done').focus();
    }

    exit(status) {
        const never = this.q('.nt-never input')?.checked;
        post(this.d.key, { event: status, never: !!never });
        this.close();
    }

    close() {
        document.removeEventListener('keydown', this.onKey, true);
        removeEventListener('resize', this.onMove); removeEventListener('scroll', this.onMove, true);
        this.root?.remove(); this.root = null; active = null;
        try { this.lastFocus?.focus?.(); } catch (e) { /* element gone */ }
    }
}

let active = null;

async function start(key, opts = {}) {
    if (active) return false;
    await ready();
    const q = new URLSearchParams({ mode: opts.mode === 'quick' ? 'quick' : 'full', resume: opts.resume ? '1' : '0', auto: opts.auto ? '1' : '0' });
    const data = await api(`/tours/${encodeURIComponent(key)}?${q}`);
    if (!data.steps || !data.steps.length) return false;
    active = new Tour(data, opts);
    await active.run();
    return true;
}

// Gentle, non-modal "Take a 1-minute tour?" prompt for a section's first visit. Not a forced overlay.
function prompt(data) {
    return new Promise((resolve) => {
        const el = document.createElement('div');
        el.className = 'nt-prompt'; el.setAttribute('role', 'region'); el.setAttribute('aria-label', 'Tour offer'); el.style.zIndex = Z;
        el.innerHTML = `<p class="nt-ptitle">Take a 1-minute tour?</p><p class="nt-pbody"></p><div class="nt-pfoot"><button type="button" class="nt-next nt-yes">Start tour</button><button type="button" class="nt-back nt-no">Not now</button><button type="button" class="nt-skip nt-never-btn">Don't show tips</button></div>`;
        el.querySelector('.nt-pbody').textContent = data.title + '. ' + (data.prompt || '');
        document.body.appendChild(el);
        const done = (v) => { el.remove(); resolve(v); };
        el.querySelector('.nt-yes').onclick = () => done('yes');
        el.querySelector('.nt-no').onclick = () => done('no');
        el.querySelector('.nt-never-btn').onclick = () => done('never');
        setTimeout(() => { if (el.isConnected) done('timeout'); }, 20000);
    });
}

async function maybeAuto() {
    try {
        const params = new URLSearchParams(location.search);
        const asked = params.get('tour');
        if (asked) {   // sent from the hub: start exactly that tour, then clean the URL
            params.delete('tour'); const mode = params.get('mode') || 'full'; const resume = params.get('resume') === '1'; params.delete('mode'); params.delete('resume');
            history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : '') + location.hash);
            await new Promise((r) => setTimeout(r, 600));
            if (!blockers()) start(asked, { mode, resume });
            return;
        }
        const route = document.querySelector('meta[name="nx-route"]')?.content;
        if (!route || active) return;
        if (sessionStorage.getItem('nt:auto') === '1') return;                                  // one auto tour per session
        const last = parseInt(localStorage.getItem('nt:last') || '0', 10);
        if (last && Date.now() - last < 600000) return;                                         // cool-down between auto tours
        await ready();
        await new Promise((r) => setTimeout(r, 2500));                                         // never on arrival
        if (blockers() || active) return;
        const res = await api('/tours/auto?route=' + encodeURIComponent(route));
        if (!res.tour) return;
        sessionStorage.setItem('nt:auto', '1'); localStorage.setItem('nt:last', String(Date.now()));
        const choice = await prompt({ title: res.tour.title, prompt: res.prompt });
        if (choice === 'yes') { if (!blockers()) start(res.tour.key, { auto: true }); }
        else if (choice === 'never') post(res.tour.key, { event: 'dismissed', never: true });
        else if (choice === 'no') post(res.tour.key, { event: 'dismissed' });
    } catch (e) { /* tours are an extra: never break the page */ }
}

window.NaaraTour = { start, active: () => !!active };
addEventListener('DOMContentLoaded', maybeAuto);
addEventListener('livewire:navigated', maybeAuto);
