// --- Nia 3-phase "human conversation" pacing (BUILD-3 §5) --------------------
// Lives ONLY on the NaaraCare SupportChat view. The server already persisted the
// AI reply (so history + tests are unaffected); this layer reveals the NEWEST
// reply with human-like pacing, and never drops a message when several arrive.
//
//   Phase 1 — Reading delay: 4000ms + random(0,500ms), total silence.
//   Phase 2 — Typing indicator: ~1200ms + random(0,300ms), the glowing dots.
//   Phase 3 — Human typing: stream char-by-char at ~200 WPM (clamped 2–5s),
//             ±5–8ms jitter, and an 8% chance of a 150–400ms pause after . , ? !
//
// A reply arriving mid-flow is queued (never cancels the current one); after the
// current finishes we wait, then run the next. Total perceived wait per message
// is a deliberate ~7–10s — this is pacing, not latency to optimise away.
//
// Registered as an Alpine store + data component so it is bundled (CSP-safe) and
// the Blade stays declarative.

export function registerNiaChat() {
    document.addEventListener('alpine:init', () => {
        const A = window.Alpine;

        A.store('nia', {
            phase: 'idle', // idle | reading | typing | stream
            _animated: new Set(),
            _queue: [],
            _running: false,
            _reduce: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

            // A streamed bubble registers its full text + a setter for its shown text.
            enqueue(job) {
                if (this._animated.has(job.id)) { job.set(job.full); return; }
                this._animated.add(job.id);
                this._queue.push(job);
                this._pump();
            },

            async _pump() {
                if (this._running) return;
                this._running = true;
                while (this._queue.length) {
                    // eslint-disable-next-line no-await-in-loop
                    await this._runJob(this._queue.shift());
                    if (this._queue.length) {
                        // eslint-disable-next-line no-await-in-loop
                        await this._delay(500);
                    }
                }
                this._running = false;
                this.phase = 'idle';
            },

            async _runJob(job) {
                if (this._reduce) { this.phase = 'idle'; job.set(job.full); return; }

                this.phase = 'reading';
                await this._delay(4000 + Math.random() * 500);

                this.phase = 'typing';
                await this._delay(1200 + Math.random() * 300);

                this.phase = 'stream';
                job.begin();
                await this._stream(job.full, job.set);

                // Glow lingers ~2s after completion before the next message.
                await this._delay(2000);
            },

            _stream(full, set) {
                return new Promise((resolve) => {
                    const words = (full.trim().match(/\S+/g) || []).length || 1;
                    let total = Math.round((words / 200) * 60000); // ms at 200 WPM
                    total = Math.min(5000, Math.max(2000, total));
                    const base = full.length ? total / full.length : 20;
                    let i = 0;
                    const tick = () => {
                        if (i >= full.length) { resolve(); return; }
                        const ch = full[i];
                        i += 1;
                        set(full.slice(0, i));
                        let d = base + (Math.random() * 16 - 8); // ±8ms jitter
                        if ('.,?!'.includes(ch) && Math.random() < 0.08) {
                            d += 150 + Math.random() * 250; // occasional human pause
                        }
                        setTimeout(tick, Math.max(4, d));
                    };
                    tick();
                });
            },

            _delay(ms) {
                return new Promise((r) => setTimeout(r, ms));
            },
        });

        // Frontend-UX-fix blueprint Phase B — the support-chat page used to size
        // itself with a hardcoded `h-[calc(100vh-9rem)]`, guessing at how much
        // chrome sits above it. That guess didn't account for the optional
        // "confirm your email" banner (only shown to unverified users), so on
        // any page load where it appeared the chat's own height overflowed the
        // viewport and the input row — sitting at the bottom of that flex
        // column — rendered partly BEHIND the floating mobile bottom nav
        // instead of above it (root-caused with Playwright: the input's own
        // bounding box overlapped the nav's). Measuring the real distance from
        // this element's top to the viewport bottom is robust to ANY chrome
        // above it, banner or not, present or future.
        A.data('niaChatLayout', () => ({
            height: null,
            calc() {
                const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
                // Matches the bottom-nav clearance <main> already reserves
                // site-wide (`pb-28` mobile / `pb-10` desktop, where the
                // floating nav is hidden entirely) — same convention, not a
                // new guess.
                const navClearance = isDesktop ? 40 : 112;
                const top = this.$el.getBoundingClientRect().top;
                this.height = Math.max(320, Math.round(window.innerHeight - top - navClearance - 8));
            },
            init() {
                // Measuring immediately on init() can run before the page's
                // full chrome (the optional "confirm your email" banner in
                // particular) has finished laying out, under-measuring `top`
                // and over-allocating height — the exact overflow this fix
                // exists to prevent. Two animation frames guarantee the
                // browser has completed a real layout/paint pass first; the
                // delayed re-check catches anything that settles later still
                // (e.g. Livewire's own async mount).
                requestAnimationFrame(() => requestAnimationFrame(() => this.calc()));
                setTimeout(() => this.calc(), 300);
                this._onChange = () => this.calc();
                window.addEventListener('resize', this._onChange);
                window.addEventListener('nx-layout-changed', this._onChange);
            },
            destroy() {
                window.removeEventListener('resize', this._onChange);
                window.removeEventListener('nx-layout-changed', this._onChange);
            },
        }));

        // One assistant bubble. Reads its text + role from data-* attributes.
        // When it's the freshly-arrived reply (data-stream="1"), it registers
        // with the store and reveals as `shown`; otherwise it shows full text.
        A.data('niaBubble', () => ({
            shown: '',
            streaming: false,
            init() {
                const el = this.$el;
                const full = el.dataset.full || '';
                const isStream = el.dataset.stream === '1';
                const id = Number(el.dataset.id || 0);
                if (!isStream) { this.shown = full; return; }
                A.store('nia').enqueue({
                    id,
                    full,
                    set: (partial) => { this.shown = partial; },
                    begin: () => { this.streaming = true; },
                });
            },
        }));
    });
}
