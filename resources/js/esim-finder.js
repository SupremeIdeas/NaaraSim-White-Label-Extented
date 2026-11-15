// eSIM Plan Finder: the controls, the live "N plans fit" counter and the data estimator run entirely in the browser over a compact list of the
// plans in the current context ([data MB or null, days or null, USD price], no ids, no cost). Only "Sort best match" talks to the server
// ($wire.applyFinder), which does the real ranking with the same rules (App\Support\EsimPlanFinder), so the two can never disagree on a result.
const DAYS = [1, 3, 5, 7, 10, 15, 30, '30+'];
const NEEDS = [1, 2, 3, 5, 10, 20, 50, 100];

const round1 = (n) => Math.round(n * 10) / 10;

export function registerEsimFinder() {
    document.addEventListener('alpine:init', () => {
        const Alpine = window.Alpine;
        if (!Alpine) return;

        // The x-data attribute is a constant ("esimFinder") ON PURPOSE: the config rides in a JSON element read once in init(). If the
        // attribute carried the server's criteria it would change on every Livewire re-render, and the DOM morph would then rebuild the
        // component out from under the page (chips and counter out of sync). With a constant attribute the browser keeps its own state.
        // x-rail: a horizontally slidable row of pills. Touch and trackpads scroll natively; a mouse can drag it. The ends fade (CSS reads
        // data-start / data-end) so it is obvious there is more to slide to, and a drag never counts as a tap on a pill.
        Alpine.directive('rail', (el, _d, { cleanup }) => {
            let down = false, moved = false, x0 = 0, s0 = 0;
            const edge = () => {
                el.dataset.start = el.scrollLeft <= 2 ? '1' : '0';
                el.dataset.end = el.scrollLeft + el.clientWidth >= el.scrollWidth - 2 ? '1' : '0';
            };
            const onDown = (e) => { if (e.pointerType !== 'mouse') return; down = true; moved = false; x0 = e.clientX; s0 = el.scrollLeft; };
            const onMove = (e) => {
                if (!down) return;
                const dx = e.clientX - x0;
                if (Math.abs(dx) > 5) { moved = true; el.classList.add('is-drag'); }
                if (moved) el.scrollLeft = s0 - dx;
            };
            const onUp = () => { down = false; el.classList.remove('is-drag'); };
            const onClick = (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } };
            el.addEventListener('pointerdown', onDown);
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
            el.addEventListener('click', onClick, true);
            el.addEventListener('scroll', edge, { passive: true });
            window.addEventListener('resize', edge);
            requestAnimationFrame(edge);
            // a rail inside a collapsed panel measures 0 until it opens, so re-measure whenever its size changes
            const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(edge) : null;
            if (ro) ro.observe(el);
            // keep a picked pill in view (e.g. after "Use this estimate" selects 15 days)
            const reveal = () => { const on = el.querySelector('.is-on'); if (on) el.scrollTo({ left: Math.max(0, on.offsetLeft - 24), behavior: 'smooth' }); };
            el._railReveal = reveal;
            cleanup(() => { if (ro) ro.disconnect(); el.removeEventListener('pointerdown', onDown); window.removeEventListener('pointermove', onMove); window.removeEventListener('pointerup', onUp); el.removeEventListener('click', onClick, true); el.removeEventListener('scroll', edge); window.removeEventListener('resize', edge); });
        });

        Alpine.data('esimFinder', () => ({
            rows: [],
            c: { days: [], need: null, unlimited: false, min: 1, max: 2000, usage: 'medium', trip: 30, estimate: false, sort: 'best' },
            profiles: {},
            cover: 0.95,
            over: 6,
            lo: 1,
            hi: 2000,
            open: true,
            est: false,
            stuck: false,
            dayChoices: DAYS,
            needChoices: NEEDS,
            vMin: 0,
            vMax: 100,

            init() {
                let cfg = {};
                try { cfg = JSON.parse(this.$refs.cfg?.textContent || '{}'); } catch (e) { cfg = {}; }
                this.rows = cfg.rows || [];
                this.c = { ...this.c, ...(cfg.criteria || {}) };
                this.profiles = cfg.profiles || {};
                this.cover = cfg.cover ?? this.cover;
                this.over = cfg.over ?? this.over;
                this.lo = cfg.lo ?? this.lo;
                this.hi = cfg.hi ?? this.hi;
                this.vMin = this.toV(this.c.min);
                this.vMax = this.toV(this.c.max);
                if (this.c.estimate) this.est = true;
                // The compact "N filters · M fit" bar shows once the finder card has scrolled up past the header.
                this._scroll = () => { const r = this.$refs.card?.getBoundingClientRect(); this.stuck = !!r && r.bottom < 150; };
                window.addEventListener('scroll', this._scroll, { passive: true });
                this._scroll();
            },
            destroy() { if (this._scroll) window.removeEventListener('scroll', this._scroll); },

            // ---- the same hard filters the server applies -------------------------------------------------------------------------------
            ok(r) {
                const [mb, days, price] = r;
                const c = this.c;
                if (price < c.min - 0.0001 || price > c.max + 0.0001) return false;
                if (c.unlimited && mb !== null) return false;
                if (c.days.length && !c.days.some((d) => (d === '30+' ? (days || 0) > 30 : days === d))) return false;
                if (c.need !== null && !c.unlimited && mb !== null) {
                    const gb = mb / 1024;
                    if (gb < c.need * this.cover || gb > c.need * this.over) return false;
                }
                return true;
            },
            get fit() { return this.rows.filter((r) => this.ok(r)).length; },
            get total() { return this.rows.length; },
            get active() {
                return (this.c.days.length ? 1 : 0) + ((this.c.need !== null || this.c.unlimited) ? 1 : 0) + ((this.c.min > this.lo + 0.001 || this.c.max < this.hi - 0.001) ? 1 : 0);
            },

            // ---- choices ----------------------------------------------------------------------------------------------------------------
            manual() { this.c.estimate = false; },
            toggleDay(d) {
                const i = this.c.days.indexOf(d);
                if (i >= 0) this.c.days.splice(i, 1); else this.c.days.push(d);
                this.manual();
            },
            setNeed(g) {
                this.c.unlimited = false;
                this.c.need = this.c.need === g ? null : g;
                this.manual();
            },
            toggleUnlimited() {
                this.c.unlimited = !this.c.unlimited;
                if (this.c.unlimited) this.c.need = null;
                this.manual();
            },
            quickPrice(a, b) { this.c.min = a; this.c.max = b; this.vMin = this.toV(a); this.vMax = this.toV(b); },

            // price slider: log scale between lo and hi
            toPrice(v) {
                const p = this.lo * Math.pow(this.hi / this.lo, v / 100);
                return p < 10 ? round1(p) : Math.round(p);
            },
            toV(p) { return Math.max(0, Math.min(100, (Math.log(Math.max(this.lo, p) / this.lo) / Math.log(this.hi / this.lo)) * 100)); },
            slide(which) {
                if (this.vMin > this.vMax - 1) { if (which === 'min') this.vMin = this.vMax - 1; else this.vMax = this.vMin + 1; }
                this.vMin = Math.max(0, this.vMin); this.vMax = Math.min(100, this.vMax);
                this.c.min = this.toPrice(this.vMin);
                this.c.max = this.toPrice(this.vMax);
            },
            typePrice(which, raw) {
                let v = parseFloat(raw);
                if (Number.isNaN(v)) v = which === 'min' ? this.lo : this.hi;
                v = Math.max(this.lo, Math.min(this.hi, v));
                this.c[which] = v;
                if (this.c.min > this.c.max) { const t = this.c.min; this.c.min = this.c.max; this.c.max = t; }
                this.vMin = this.toV(this.c.min);
                this.vMax = this.toV(this.c.max);
            },
            get fillStyle() { return `left:${this.vMin}%;width:${Math.max(0, this.vMax - this.vMin)}%`; },
            money(n) { return '$' + (n >= 100 ? Math.round(n).toLocaleString() : (Math.round(n * 100) / 100).toString()); },

            // ---- estimator --------------------------------------------------------------------------------------------------------------
            tripChips: [3, 5, 7, 10, 14, 21, 30, 45, 60],
            get perDayGb() { return Math.round(((this.profiles[this.c.usage]?.mb || 800) / 1024) * 100) / 100; },
            perDay(key) { return Math.round(((this.profiles[key]?.mb || 0) / 1024) * 100) / 100; },
            get estGb() { return round1(((this.profiles[this.c.usage]?.mb || 800) * this.c.trip) / 1024); },
            // the smallest standard plan size that covers the estimate (null when it is bigger than any chip: unlimited territory)
            get sizeNeeded() { return NEEDS.find((g) => g >= this.estGb) ?? null; },
            pickUsage(key) { this.c.usage = key; this.manual(); },
            pickTrip(d) { this.c.trip = d; this.manual(); },
            useEstimate() {
                const need = Math.max(1, this.estGb);
                const day = DAYS.find((d) => d !== '30+' && d >= this.c.trip);
                this.c.need = round1(need);
                this.c.unlimited = false;
                this.c.days = [day !== undefined ? day : '30+'];
                this.c.estimate = true;
                this.go();
            },

            // ---- server round trips -----------------------------------------------------------------------------------------------------
            payload() { return JSON.parse(JSON.stringify(this.c)); },
            go() { this.$wire.applyFinder(this.payload()); },
            reset() {
                this.c = { days: [], need: null, unlimited: false, min: this.lo, max: this.hi, usage: 'medium', trip: 30, estimate: false, sort: 'best' };
                this.vMin = 0; this.vMax = 100; this.est = false;
                this.$wire.resetFinder();
            },
            toTop() { this.$refs.card?.scrollIntoView({ behavior: 'smooth', block: 'start' }); },
        }));
    });
}
