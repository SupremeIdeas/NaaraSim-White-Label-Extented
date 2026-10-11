// Chat Composer Pro — one composer instance. Everything here is per-instance (no globals, no element ids, no monkey-patching), so any
// number of composers can live on one page. Transport-agnostic: the instance builds a payload and asks the host to deliver it by firing a
// cancelable `cc:transmit` event (Livewire surfaces answer with $wire.upload + their existing action); if the host does not answer and an
// `endpoint` is configured, the built-in HTTP path (multipart + Idempotency-Key + IndexedDB outbox + backoff) is used.
import { ICONS } from './icons.js';
import { ls, kv } from './store.js';
import { LABELS, fmt } from './labels.js';
import { encodeGif, grabFrame, loadImage } from './gif.js';
import { loadEmoji, recents, pushRecent } from './emoji.js';
import { VoiceCapture, voiceSupported } from './voice.js';

const DEFAULTS = {
    mode: 'chat',                       // chat: send button + undo + outbox | field: collects text/files/voice only, emits cc:change
    features: ['emoji', 'attach', 'voice'], // emoji attach voice gif schedule hints snippets captions
    attachKinds: ['media', 'camera', 'document'], // media camera video document audio location contact poll
    accept: { media: 'image/*,video/*', camera: 'image/*', video: 'video/*', document: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip', audio: 'audio/*' },
    placeholder: '', rows: 1, maxRows: 7, maxChars: 0,
    maxFiles: 10, maxFileMb: 25, maxRawMb: 25, compressOver: 1048576,
    undoMs: 3000, sendOnEnter: true,
    convoId: 'default', scope: '', endpoint: '', headers: {}, credentials: 'same-origin',
    gifEndpoint: '', gifSearchEndpoint: '', recentEndpoint: '', historyEndpoint: '',
    micPrimer: true, lang: '', labels: {}, draft: true, autoFocus: false, bare: false,
};

const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
const html = (tag, cls, markup) => { const e = el(tag, cls); e.innerHTML = markup; return e; }; // static markup only (icons/templates), never user data
const uid = () => (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`);
const fmtTime = (sec) => { sec = Math.max(0, Math.floor(sec)); return `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`; };
const dataUrlToBlob = (u) => { const [h, b] = u.split(','); const mime = /data:([^;]+)/.exec(h)?.[1] || 'application/octet-stream'; const bin = atob(b); const a = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) a[i] = bin.charCodeAt(i); return new Blob([a], { type: mime }); };
const blobToDataUrl = (b) => new Promise((r) => { const f = new FileReader(); f.onload = () => r(f.result); f.readAsDataURL(b); });

const SNIPPETS = {
    thanks: () => 'Thank you so much!',
    omw: () => 'On my way.',
    eta: () => `ETA about ${new Date(Date.now() + 9e5).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`,
};
const HINT_RULES = [[/\byoure\b/i, 'you’re'], [/\bdont\b/i, 'don’t'], [/\bcant\b/i, 'can’t'], [/\bwont\b/i, 'won’t'], [/\bteh\b/i, 'the'], [/\bthier\b/i, 'their']];
const SHORTENERS = /\/\/(bit\.ly|tinyurl\.com|t\.co|goo\.gl|is\.gd|cutt\.ly|rb\.gy)|xn--|\/\/\d+\.\d+\.\d+\.\d+/i;

export class Composer {
    constructor(host, options = {}) {
        this.host = host;
        const o = { ...DEFAULTS, ...options };
        o.accept = { ...DEFAULTS.accept, ...(options.accept || {}) };
        this.o = o;
        this.L = { ...LABELS, ...(o.labels || {}) };
        this.lang = o.lang || document.documentElement.lang || 'en';
        this.has = (f) => o.features.includes(f);
        this.field = o.mode === 'field';

        this.atts = [];            // pending attachments: {id, kind:'file'|'location'|'contact'|'poll', ...}
        this.voiceNote = null;     // field mode: a finished recording waiting to be submitted by the host
        this.queue = [];           // outbox (chat mode)
        this.pending = null;       // undo window
        this.sched = 0;
        this.albumAll = true;
        this.presets = [];
        this.history = [];
        this.cleanups = [];
        this.openPanel = null;
        this.draftKey = `cc_draft:${o.convoId}`;
        this.typingAt = 0;

        this.build();
        this.bind();
        this.restore();
    }

    t(k, v) { return fmt(this.L[k] ?? k, v); }
    on(target, ev, fn, opts) { target.addEventListener(ev, fn, opts); this.cleanups.push(() => target.removeEventListener(ev, fn, opts)); }
    emit(name, detail = {}, cancelable = false) {
        const e = new CustomEvent(`cc:${name}`, { bubbles: true, cancelable, detail });
        this.host.dispatchEvent(e);
        return e;
    }
    $(name) { return this.refs[name]; }

    /* ------------------------------------------------------------------ DOM */
    build() {
        const o = this.o; const L = this.L;
        const root = el('div', 'cc');
        root.dataset.mode = o.mode;
        if (o.bare) root.classList.add('cc-bare');
        this.refs = {};
        const ref = (name, node) => { node.dataset.r = name; this.refs[name] = node; return node; };

        const status = ref('status', el('div', 'cc-status')); status.hidden = true; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
        const box = el('div', 'cc-box');
        const atts = ref('atts', el('div', 'cc-atts')); atts.hidden = true;
        const text = ref('text', el('textarea', 'cc-text')); text.rows = o.rows; text.placeholder = o.placeholder || L.placeholder;
        text.setAttribute('aria-label', o.placeholder || L.placeholder); text.autocomplete = 'off'; text.enterKeyHint = this.field ? 'enter' : 'send';
        if (o.maxChars) text.maxLength = o.maxChars;
        const hint = ref('hint', el('div', 'cc-hint')); hint.hidden = true;

        box.append(atts, text, hint);
        // panels
        box.append(this.buildEmojiPanel(ref), this.buildGifPanels(ref), this.buildAttachPanel(ref), this.buildMiniPanels(ref));

        // controls
        const controls = ref('controls', el('div', 'cc-controls'));
        const btn = (name, icon, label, cls = 'cc-btn') => { const b = el('button', cls); b.type = 'button'; b.innerHTML = icon; b.setAttribute('aria-label', label); b.title = label; return ref(name, b); };
        if (this.has('attach')) controls.append(btn('attachBtn', ICONS.attach, L.attach));
        if (this.has('emoji')) controls.append(btn('emojiBtn', ICONS.emoji, L.emoji));
        if (this.has('gif')) { const g = el('button', 'cc-gifbtn', L.gif); g.type = 'button'; g.setAttribute('aria-label', L.gif); controls.append(ref('gifBtn', g)); }
        if (this.has('schedule')) controls.append(btn('schedBtn', ICONS.clock, L.schedule));
        const spacer = el('div', 'cc-spacer');
        if (this.has('voice')) spacer.append(btn('micBtn', ICONS.mic, L.record));
        if (!this.field) { const s = btn('sendBtn', ICONS.send, L.send, 'cc-send'); s.disabled = true; spacer.append(s); }
        controls.append(spacer);
        box.append(controls, this.buildVoiceBar(ref));
        const file = ref('file', el('input')); file.type = 'file'; file.hidden = true; file.multiple = o.maxFiles !== 1;
        file.tabIndex = -1; box.append(file);
        root.append(status, box);
        this.root = root;
        this.host.replaceChildren(root);
        this.autoGrow();
    }

    panel(name, ref, label) { const p = el('div', 'cc-panel'); p.hidden = true; p.setAttribute('role', 'group'); if (label) p.setAttribute('aria-label', label); return ref(name, p); }

    buildEmojiPanel(ref) {
        const p = this.panel('pEmoji', ref, this.L.emoji);
        const search = el('div', 'cc-search'); search.innerHTML = ICONS.search;
        const inp = ref('emojiSearch', el('input')); inp.type = 'search'; inp.placeholder = this.L.searchEmoji; inp.setAttribute('aria-label', this.L.searchEmoji);
        search.append(inp);
        p.append(search, ref('emojiTabs', el('div', 'cc-etabs')), ref('emojiGrid', el('div', 'cc-egrid')));
        return p;
    }

    buildGifPanels(ref) {
        const frag = document.createDocumentFragment();
        const p = this.panel('pGif', ref, this.L.gif);
        const search = el('div', 'cc-search'); search.innerHTML = ICONS.search;
        const inp = ref('gifSearch', el('input')); inp.type = 'search'; inp.placeholder = this.L.searchGifs; search.append(inp);
        const tools = el('div', 'cc-gtools');
        const mkBtn = (n, label) => { const b = el('button', 'cc-chip', label); b.type = 'button'; return ref(n, b); };
        tools.append(mkBtn('gImg', this.L.imagesToGif), mkBtn('gVid', this.L.videoToGif));
        p.append(search, tools, ref('gifGrid', el('div', 'cc-ggrid')));
        const mk = this.panel('pGifMaker', ref, this.L.gif); mk.classList.add('cc-gmaker');
        frag.append(p, mk);
        return frag;
    }

    buildAttachPanel(ref) {
        const p = this.panel('pAttach', ref, this.L.attach);
        const rl = el('div', 'cc-label', this.L.recentPhotos); ref('recentLabel', rl); rl.hidden = true;
        const strip = ref('recentStrip', el('div', 'cc-recent')); strip.hidden = true;
        const grid = el('div', 'cc-sheet');
        const kinds = { media: ['image', this.L.gallery], camera: ['camera', this.L.camera], video: ['video', this.L.video], document: ['file', this.L.document], audio: ['music', this.L.audio], location: ['pin', this.L.location], contact: ['user', this.L.contact], poll: ['chart', this.L.poll] };
        this.o.attachKinds.forEach((k) => {
            if (!kinds[k]) return;
            const b = el('button', 'cc-sheet-item'); b.type = 'button'; b.dataset.kind = k;
            const ic = el('span', `cc-sheet-ic cc-k-${k}`); ic.innerHTML = ICONS[kinds[k][0]];
            b.append(ic, el('span', '', kinds[k][1]));
            grid.append(b);
        });
        p.append(rl, strip, grid);
        return p;
    }

    buildMiniPanels(ref) {
        const frag = document.createDocumentFragment();
        const L = this.L;
        const input = (name, ph, type = 'text') => { const i = ref(name, el('input')); i.type = type; i.placeholder = ph; i.setAttribute('aria-label', ph); return i; };
        const actions = (...b) => { const a = el('div', 'cc-actions'); a.append(...b); return a; };
        const button = (name, label, cls = 'cc-chip') => { const b = el('button', cls, label); b.type = 'button'; return ref(name, b); };

        const contact = this.panel('pContact', ref, L.contact);
        const cf = el('div', 'cc-form'); cf.append(input('contactName', L.contactName), input('contactPhone', L.contactPhone, 'tel'), actions(button('contactCancel', L.cancel), button('contactAdd', L.addContact, 'cc-chip cc-primary')));
        contact.append(cf);

        const poll = this.panel('pPoll', ref, L.poll);
        const pf = el('div', 'cc-form'); const opts = ref('pollOptions', el('div', 'cc-poll-opts'));
        pf.append(input('pollQuestion', L.pollQuestion), opts, actions(button('pollAdd', L.addOption), button('pollCancel', L.cancel), button('pollCreate', L.createPoll, 'cc-chip cc-primary')));
        poll.append(pf);

        const sched = this.panel('pSched', ref, L.schedule);
        const sf = el('div', 'cc-form'); sf.append(el('label', 'cc-label', L.scheduleAt), input('schedInput', L.scheduleAt, 'datetime-local'), actions(button('schedClear', L.clearSchedule), button('schedSet', L.setSchedule, 'cc-chip cc-primary')));
        sched.append(sf);

        const dlg = this.panel('pDialog', ref, '');
        dlg.append(ref('dlgTitle', el('div', 'cc-dtitle')), ref('dlgInput', el('input')), ref('dlgActions', el('div', 'cc-actions')));

        const primer = this.panel('pPrimer', ref, L.record);
        const pbox = el('div', 'cc-primer'); const pic = el('span', 'cc-primer-ic'); pic.innerHTML = ICONS.mic;
        const ptxt = el('div', 'cc-primer-txt'); ptxt.append(el('b', '', L.micPrimer), el('span', '', L.micPrimerSub), actions(button('primerNo', L.micNotNow), button('primerYes', L.micAllow, 'cc-chip cc-primary')));
        pbox.append(pic, ptxt); primer.append(pbox);

        frag.append(contact, poll, sched, dlg, primer);
        return frag;
    }

    buildVoiceBar(ref) {
        const L = this.L;
        const bar = ref('voice', el('div', 'cc-voice')); bar.hidden = true;
        const handle = el('div', 'cc-vhandle');
        const row1 = el('div', 'cc-vrow');
        const play = ref('vPlay', el('button', 'cc-btn cc-vplay')); play.type = 'button'; play.innerHTML = ICONS.play; play.hidden = true; play.setAttribute('aria-label', L.playPreview);
        const timer = ref('vTimer', el('span', 'cc-vtimer', '0:00'));
        const track = el('div', 'cc-wave'); const bg = ref('vBg', el('div', 'cc-wave-bars')); const progWrap = ref('vProg', el('div', 'cc-wave-prog')); const fg = ref('vFg', el('div', 'cc-wave-bars')); progWrap.append(fg); track.append(bg, progWrap);
        const speed = ref('vSpeed', el('button', 'cc-speed', '1x')); speed.type = 'button'; speed.setAttribute('aria-label', L.speed);
        row1.append(play, timer, track, speed);
        const cap = ref('vCap', el('div', 'cc-vcap')); cap.setAttribute('aria-live', 'polite');
        const row2 = el('div', 'cc-vrow cc-vrow2');
        const trash = ref('vTrash', el('button', 'cc-btn cc-danger')); trash.type = 'button'; trash.innerHTML = ICONS.trash; trash.setAttribute('aria-label', L.discardVoice);
        const pr = ref('vPause', el('button', 'cc-pill')); pr.type = 'button';
        const send = ref('vSend', el('button', 'cc-send cc-ready')); send.type = 'button'; send.innerHTML = ICONS.send; send.setAttribute('aria-label', this.field ? L.voiceReady : L.sendVoice);
        row2.append(trash, pr, send);
        bar.append(handle, row1, cap, row2);
        return bar;
    }

    /* ------------------------------------------------------------- wiring */
    bind() {
        const r = this.refs; const text = r.text;
        this.on(text, 'input', () => { this.autoGrow(); this.updateSend(); this.checkHints(); this.saveDraft(); this.typing(); this.changed(); if (this.openPanel === 'pAttach') this.renderRecent(); });
        this.on(text, 'keydown', (e) => this.onKey(e));
        this.on(text, 'paste', (e) => this.onPaste(e));
        this.on(this.root, 'keydown', (e) => { if (e.key === 'Escape' && this.openPanel) { this.closePanels(); text.focus(); } });
        if (r.sendBtn) this.on(r.sendBtn, 'click', () => (this.pending ? this.undoSend() : this.handleSend()));
        if (r.attachBtn) this.on(r.attachBtn, 'click', () => { this.toggle('pAttach', r.attachBtn); if (this.openPanel === 'pAttach') this.renderRecent(); });
        if (r.emojiBtn) { this.on(r.emojiBtn, 'click', () => this.toggleEmoji()); this.on(r.emojiSearch, 'input', () => this.renderEmoji()); }
        if (r.gifBtn) { this.on(r.gifBtn, 'click', () => { this.toggle('pGif', r.gifBtn); if (this.openPanel === 'pGif') { r.gifSearch.value = ''; this.renderGifs(''); r.gifSearch.focus(); } }); this.bindGif(); }
        if (r.schedBtn) { this.on(r.schedBtn, 'click', () => this.toggle('pSched', r.schedBtn)); this.on(r.schedSet, 'click', () => this.setSchedule()); this.on(r.schedClear, 'click', () => this.clearSchedule(true)); }
        r.pAttach.querySelectorAll('.cc-sheet-item').forEach((b) => this.on(b, 'click', () => this.pickKind(b.dataset.kind)));
        this.on(r.file, 'change', () => this.onFiles());
        this.on(r.contactCancel, 'click', () => this.closePanels());
        this.on(r.contactAdd, 'click', () => this.addContact());
        this.on(r.pollAdd, 'click', () => this.addPollOption());
        this.on(r.pollCancel, 'click', () => this.closePanels());
        this.on(r.pollCreate, 'click', () => this.createPoll());
        this.resetPoll();
        if (this.has('voice')) this.bindVoice();
        this.on(window, 'online', () => { if (this.queue.length) { this.say(this.t('backOnline')); this.tick(); } });
        this.on(window, 'storage', (e) => this.onStorage(e));
        if (!this.field) { this.tickTimer = setInterval(() => this.tick(), 1000); this.cleanups.push(() => clearInterval(this.tickTimer)); }
        this.loadHistory();
        if (this.has('gif')) this.loadPresets();
        if (this.o.autoFocus) text.focus();
    }

    destroy() {
        this.cleanups.forEach((f) => f());
        this.cleanups = [];
        clearTimeout(this.barT);
        if (this.pending) clearTimeout(this.pending.t);
        this.voice?.discard();
        this.atts.forEach((a) => a.url && URL.revokeObjectURL(a.url));
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
    }

    focus() { this.refs.text.focus(); }

    /* ------------------------------------------------------------- helpers */
    say(msg, actLabel, fn, ms = 4500) {
        const bar = this.refs.status; clearTimeout(this.barT);
        bar.replaceChildren(el('span', '', msg)); bar.hidden = false;
        if (actLabel) { const b = el('button', 'cc-chip', actLabel); b.type = 'button'; b.onclick = () => { fn(); bar.hidden = true; }; bar.append(b); }
        if (ms) this.barT = setTimeout(() => { bar.hidden = true; }, ms);
    }

    autoGrow() {
        const t = this.refs.text; const lh = 24; const max = Math.round(lh * this.o.maxRows) + 24;
        t.style.height = 'auto'; t.style.height = `${Math.min(t.scrollHeight, max)}px`;
        t.style.overflowY = t.scrollHeight > max ? 'auto' : 'hidden';
    }

    hasContent() { return this.refs.text.value.trim().length > 0 || this.atts.length > 0; }

    updateSend() {
        const s = this.refs.sendBtn; if (!s) return;
        const has = this.hasContent();
        s.classList.toggle('cc-ready', has || !!this.pending);
        s.disabled = !(has || this.pending);
    }

    typing() {
        const now = Date.now();
        if (now - this.typingAt > 2500) { this.typingAt = now; this.emit('typing', { convoId: this.o.convoId }); }
    }

    changed() {
        if (!this.field) return;
        this.emit('change', { text: this.refs.text.value, files: this.atts.filter((a) => a.kind === 'file').map((a) => a.file), items: this.atts.filter((a) => a.kind !== 'file').map((a) => this.itemOf(a)), voice: this.voiceNote });
    }

    itemOf(a) {
        if (a.kind === 'location') return { type: 'location', label: a.label, expiresAt: a.expiresAt || null };
        if (a.kind === 'contact') return { type: 'contact', label: a.label, phone: a.phone || null };
        return { type: 'poll', label: a.label, options: a.options };
    }

    closePanels() {
        ['pEmoji', 'pGif', 'pGifMaker', 'pAttach', 'pContact', 'pPoll', 'pSched', 'pDialog', 'pPrimer'].forEach((n) => { if (this.refs[n]) this.refs[n].hidden = true; });
        ['emojiBtn', 'gifBtn', 'attachBtn', 'schedBtn'].forEach((n) => { const b = this.refs[n]; if (b) { b.classList.remove('cc-active'); b.setAttribute('aria-expanded', 'false'); } });
        this.openPanel = null;
    }

    toggle(name, btn) {
        const was = this.openPanel === name;
        this.closePanels();
        if (!was) { this.refs[name].hidden = false; this.openPanel = name; if (btn) { btn.classList.add('cc-active'); btn.setAttribute('aria-expanded', 'true'); } }
    }

    show(name) { this.closePanels(); this.refs[name].hidden = false; this.openPanel = name; }

    /** Inline replacement for prompt()/confirm(): resolves with the typed value (or true), or null when cancelled. */
    ask({ title, input = null, actions = [{ label: this.L.ok, value: true, primary: true }], cancel = true }) {
        return new Promise((resolve) => {
            const r = this.refs;
            this.show('pDialog');
            r.dlgTitle.textContent = title;
            r.dlgInput.hidden = input === null; r.dlgInput.className = 'cc-dinput'; r.dlgInput.value = input?.value || ''; r.dlgInput.placeholder = input?.placeholder || '';
            r.dlgInput.setAttribute('aria-label', title);
            r.dlgActions.className = 'cc-actions'; r.dlgActions.replaceChildren();
            const done = (v) => { this.closePanels(); resolve(v); };
            if (cancel) { const c = el('button', 'cc-chip', this.L.cancel); c.type = 'button'; c.onclick = () => done(null); r.dlgActions.append(c); }
            actions.forEach((a) => { const b = el('button', a.primary ? 'cc-chip cc-primary' : 'cc-chip', a.label); b.type = 'button'; b.onclick = () => done(input !== null && a.value === true ? r.dlgInput.value.trim() : a.value); r.dlgActions.append(b); });
            (input !== null ? r.dlgInput : r.dlgActions.lastChild)?.focus();
        });
    }

    /* ---------------------------------------------------------- text input */
    onKey(e) {
        const t = this.refs.text;
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !this.field && this.o.sendOnEnter) { e.preventDefault(); this.handleSend(); return; }
        if (this.has('snippets') && (e.key === ' ' || e.key === 'Tab')) {
            const v = t.value; const p = t.selectionStart; const m = /(^|\s)\/(\w+)$/.exec(v.slice(0, p));
            const key = m && m[2].toLowerCase();
            if (key && (SNIPPETS[key] || key === 'addr')) { e.preventDefault(); this.snippet(key, v, p, m[2].length); }
        }
    }

    async snippet(key, v, p, len) {
        let r = key === 'addr' ? ls.get('cc_addr', '') : SNIPPETS[key]();
        if (key === 'addr' && !r) { r = await this.ask({ title: this.L.addr, input: { placeholder: '' } }); if (r) ls.set('cc_addr', r); }
        if (!r) return;
        const t = this.refs.text; const st = p - len - 1;
        t.value = `${v.slice(0, st)}${r} ${v.slice(p)}`; t.selectionStart = t.selectionEnd = st + r.length + 1;
        t.focus(); t.dispatchEvent(new Event('input'));
    }

    onPaste(e) {
        const items = e.clipboardData?.items; if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) { const f = item.getAsFile(); if (f) this.addFiles([f]); }
        }
    }

    insertText(s) {
        const t = this.refs.text; const a = t.selectionStart ?? t.value.length; const b = t.selectionEnd ?? t.value.length;
        if (this.o.maxChars && t.value.length - (b - a) + s.length > this.o.maxChars) return;
        t.value = t.value.slice(0, a) + s + t.value.slice(b);
        t.focus(); t.selectionStart = t.selectionEnd = a + s.length;
        t.dispatchEvent(new Event('input'));
    }

    checkHints() {
        const h = this.refs.hint; h.hidden = true; h.replaceChildren();
        if (!this.has('hints')) return;
        const v = this.refs.text.value;
        if (this.lang.startsWith('en')) {
            for (const [re, fix] of HINT_RULES) {
                if (!re.test(v)) continue;
                h.append(el('span', '', this.t('hintMean', { fix })));
                const b = el('button', 'cc-chip', this.L.hintFix); b.type = 'button';
                b.onclick = () => { this.refs.text.value = v.replace(re, fix); this.refs.text.dispatchEvent(new Event('input')); };
                h.append(b); h.hidden = false; return;
            }
        }
        const u = v.match(/https?:\/\/\S+/i);
        if (u && SHORTENERS.test(u[0])) { const w = el('span', 'cc-warn'); w.innerHTML = ICONS.warn; w.append(this.t('hintLink')); h.append(w); h.hidden = false; }
    }

    /* ---------------------------------------------------------- drafts */
    saveDraft() {
        if (!this.o.draft || this.field) return;
        const v = this.refs.text.value;
        if (v) ls.set(this.draftKey, { t: Date.now(), v }); else ls.del(this.draftKey);
    }

    restore() {
        if (!this.o.draft || this.field) { this.restoreQueue(); return; }
        const d = ls.get(this.draftKey, null);
        if (d && d.v) {
            this.refs.text.value = d.v; this.autoGrow(); this.updateSend();
            const days = (Date.now() - d.t) / 864e5;
            this.refs.text.style.opacity = String(Math.max(0.4, 1 - days * 0.2));
            this.on(this.refs.text, 'input', () => { this.refs.text.style.opacity = '1'; }, { once: true });
            if (days >= 1) this.say(this.t('draftOld', { n: Math.floor(days) }), this.L.discard, () => { this.refs.text.value = ''; ls.del(this.draftKey); this.refs.text.style.opacity = '1'; this.updateSend(); this.autoGrow(); }, 7000);
        }
        this.restoreQueue();
    }

    onStorage(e) {
        if (e.key !== this.draftKey || this.refs.text.value) return;
        const d = ls.get(this.draftKey, null);
        if (d && d.v) this.say(this.t('draftOther'), this.L.pickUp, () => { this.refs.text.value = d.v; this.autoGrow(); this.updateSend(); }, 9000);
    }

    /* ----------------------------------------------------------- emoji */
    async toggleEmoji() {
        this.toggle('pEmoji', this.refs.emojiBtn);
        if (this.openPanel !== 'pEmoji') return;
        this.refs.emojiSearch.value = '';
        if (!this.emojiGroups) { this.refs.emojiGrid.textContent = '…'; this.emojiGroups = await loadEmoji(); this.buildEmojiTabs(); }
        this.emojiGroup = recents().length ? 'recent' : (this.emojiGroups[0]?.slug || '');
        this.renderEmoji(); this.refs.emojiSearch.focus();
    }

    buildEmojiTabs() {
        const tabs = this.refs.emojiTabs; tabs.replaceChildren();
        const add = (slug, label, glyph) => { const b = el('button', 'cc-etab'); if (slug === 'recent') b.innerHTML = ICONS.clock; else b.textContent = glyph; b.type = 'button'; b.title = label; b.setAttribute('aria-label', label); b.dataset.slug = slug; b.onclick = () => { this.refs.emojiSearch.value = ''; this.emojiGroup = slug; this.renderEmoji(); }; tabs.append(b); };
        add('recent', this.L.recent, '');
        this.emojiGroups.forEach((g) => add(g.slug, g.label, g.emojis[0]?.e || '•'));
    }

    renderEmoji() {
        if (!this.emojiGroups) return;
        const grid = this.refs.emojiGrid; const q = this.refs.emojiSearch.value.trim().toLowerCase(); grid.replaceChildren();
        this.refs.emojiTabs.querySelectorAll('.cc-etab').forEach((b) => b.classList.toggle('cc-active', !q && b.dataset.slug === this.emojiGroup));
        let list;
        if (q) list = this.emojiGroups.flatMap((g) => g.emojis).filter((x) => x.n.toLowerCase().includes(q)).slice(0, 160).map((x) => x.e);
        else if (this.emojiGroup === 'recent') list = recents();
        else list = (this.emojiGroups.find((g) => g.slug === this.emojiGroup)?.emojis || []).map((x) => x.e);
        if (!list.length) { grid.append(el('div', 'cc-empty', this.L.noEmoji)); return; }
        list.forEach((e) => { const b = el('button', '', e); b.type = 'button'; b.onclick = () => { this.insertText(e); pushRecent(e); if (this.emojiGroup === 'recent' && !q) this.renderEmoji(); }; grid.append(b); });
    }

    /* ------------------------------------------------------ attachments */
    pickKind(kind) {
        const f = this.refs.file; const acc = this.o.accept;
        if (['media', 'camera', 'video', 'document', 'audio'].includes(kind)) {
            f.accept = acc[kind] || ''; if (kind === 'camera') f.setAttribute('capture', 'environment'); else f.removeAttribute('capture');
            f.click();
        } else if (kind === 'location') this.addLocation();
        else if (kind === 'contact') { this.show('pContact'); this.refs.contactName.focus(); }
        else if (kind === 'poll') { this.show('pPoll'); this.refs.pollQuestion.focus(); }
    }

    async onFiles() {
        const files = Array.from(this.refs.file.files); this.refs.file.value = '';
        this.closePanels();
        await this.addFiles(files);
    }

    async addFiles(files, { skipRecord = false } = {}) {
        const o = this.o; const sent = ls.get(`cc_sent_${o.convoId}`, {});
        for (const f of files) {
            if (this.atts.filter((a) => a.kind === 'file').length >= o.maxFiles) { this.say(this.t('tooMany', { n: o.maxFiles })); break; }
            const isImg = f.type.startsWith('image/'); const isVid = f.type.startsWith('video/');
            // A photo may start large: it is compressed below and the real limit is applied to the result.
            const compressible = isImg && f.type !== 'image/gif';
            if (f.size > (compressible ? o.maxRawMb : o.maxFileMb) * 1048576) { this.say(this.t('tooBig', { name: f.name, mb: compressible ? o.maxRawMb : o.maxFileMb })); continue; }
            if (!this.typeAllowed(f)) { this.say(this.t('badType', { name: f.name })); continue; }
            const a = { id: uid(), kind: 'file', file: f, url: (isImg || isVid) ? URL.createObjectURL(f) : null, isVid };
            this.atts.push(a);
            const key = this.fkey(f); const h = this.history.find((x) => x.name === f.name && x.size === f.size);
            const drop = () => { this.removeAtt(a.id); };
            if (h && h.dir === 'them' && !sent[key]) this.say(this.t('theySent', { name: f.name, when: this.ago(h.at) }), this.L.remove, drop, 8000);
            else if (sent[key]) this.say(this.t('sentBefore', { name: f.name, when: this.ago(sent[key]) }), this.L.remove, drop, 8000);
            this.renderAtts(); this.updateSend(); this.changed();
            if (compressible && f.size > o.compressOver) await this.compress(a);
            if (!skipRecord && this.atts.includes(a) && this.has('attach')) this.recordRecent(a.file);
            if (a.file.size > o.maxFileMb * 1048576) { this.removeAtt(a.id); this.say(this.t('tooBig', { name: f.name, mb: o.maxFileMb })); }
        }
    }

    typeAllowed(f) {
        const acc = Object.values(this.o.accept).join(',').split(',').map((x) => x.trim().toLowerCase()).filter(Boolean);
        const kinds = this.o.attachKinds;
        const allowed = [];
        kinds.forEach((k) => { if (this.o.accept[k]) allowed.push(...this.o.accept[k].split(',').map((x) => x.trim().toLowerCase())); });
        const list = allowed.length ? allowed : acc;
        const ext = `.${(f.name.split('.').pop() || '').toLowerCase()}`; const mime = (f.type || '').toLowerCase();
        return list.some((a) => a === mime || a === ext || (a.endsWith('/*') && mime.startsWith(a.slice(0, -1))));
    }

    fkey(f) { return `${this.o.convoId}|${f.name}|${f.size}|${f.lastModified}`; }

    ago(t) { return Date.now() - t < 864e5 ? this.L.today : this.t('onDay', { day: new Date(t).toLocaleDateString(undefined, { weekday: 'long' }) }); }

    async compress(a) {
        try {
            const f = a.file; const im = await loadImage(f); const limit = this.o.maxFileMb * 1048576; let b = null;
            for (const [px, q] of [[1600, 0.78], [1280, 0.66], [960, 0.55]]) {
                const sc = Math.min(1, px / Math.max(im.naturalWidth, im.naturalHeight));
                const c = document.createElement('canvas'); c.width = Math.round(im.naturalWidth * sc); c.height = Math.round(im.naturalHeight * sc);
                c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
                b = await new Promise((res) => c.toBlob(res, 'image/jpeg', q));
                if (b && b.size <= limit) break;
            }
            if (b && b.size < f.size && this.atts.includes(a)) {
                a.originalUrl = a.url; a.original = f;
                a.file = new File([b], `${f.name.replace(/\.\w+$/, '')}.jpg`, { type: 'image/jpeg', lastModified: f.lastModified });
                a.url = URL.createObjectURL(b); a.note = `−${Math.round(100 - (b.size / f.size) * 100)}%`;
                this.renderAtts(); this.changed();
            }
        } catch { /* keep the original */ }
    }

    removeAtt(id) {
        const a = this.atts.find((x) => x.id === id); if (!a) return;
        if (a.url) URL.revokeObjectURL(a.url);
        if (a.originalUrl) URL.revokeObjectURL(a.originalUrl);
        if (a.recentId) this.recentSel?.delete(a.recentId);
        this.atts = this.atts.filter((x) => x.id !== id);
        this.renderAtts(); this.updateSend(); this.changed();
        if (this.openPanel === 'pAttach') this.renderRecent();
    }

    renderAtts() {
        const box = this.refs.atts; box.replaceChildren(); box.hidden = !this.atts.length && !this.voiceNote;
        this.atts.forEach((a) => {
            const c = el('div', 'cc-att');
            if (a.kind === 'file') {
                if (a.url && a.isVid) { const v = el('video'); v.src = a.url; v.muted = true; c.append(v); }
                else if (a.url) { const i = el('img'); i.src = a.url; i.alt = a.file.name; c.append(i); }
                else { const ic = el('span', 'cc-att-ic'); ic.innerHTML = ICONS.file; c.append(ic, el('div', 'cc-cap', a.file.name)); }
                if (a.note) { const b = el('button', 'cc-badge', a.note); b.type = 'button'; b.title = this.L.original; b.setAttribute('aria-label', this.L.original); b.onclick = (e) => { e.stopPropagation(); window.open(a.originalUrl, '_blank', 'noopener'); }; c.append(b); }
            } else {
                const ic = el('span', 'cc-att-ic'); ic.innerHTML = ICONS[a.kind === 'location' ? 'pin' : a.kind === 'contact' ? 'user' : 'chart']; c.append(ic, el('div', 'cc-cap', a.label));
            }
            const rm = el('button', 'cc-rm'); rm.type = 'button'; rm.innerHTML = ICONS.x; rm.setAttribute('aria-label', `${this.L.remove}`); rm.onclick = () => this.removeAtt(a.id);
            c.append(rm); box.append(c);
        });
        if (this.voiceNote) {
            const c = el('div', 'cc-att cc-att-voice'); const ic = el('span', 'cc-att-ic'); ic.innerHTML = ICONS.mic;
            c.append(ic, el('div', 'cc-cap', `${this.L.voiceNote} · ${fmtTime(this.voiceNote.duration)}`));
            const rm = el('button', 'cc-rm'); rm.type = 'button'; rm.innerHTML = ICONS.x; rm.setAttribute('aria-label', this.L.remove); rm.onclick = () => { this.voiceNote = null; this.renderAtts(); this.updateSend(); this.changed(); };
            c.append(rm); box.append(c);
        }
        if (this.atts.filter((a) => a.kind === 'file' && a.file.type.startsWith('image/')).length > 1) {
            const l = el('label', 'cc-album'); const cb = el('input'); cb.type = 'checkbox'; cb.checked = this.albumAll; cb.onchange = () => { this.albumAll = cb.checked; };
            l.append(cb, ` ${this.L.caption}`); box.append(l);
        }
    }

    /* contact / poll / location / schedule */
    addContact() {
        const r = this.refs; const name = r.contactName.value.trim(); if (!name) { r.contactName.focus(); return; }
        this.atts.push({ id: uid(), kind: 'contact', label: name, phone: r.contactPhone.value.trim() });
        r.contactName.value = ''; r.contactPhone.value = '';
        this.closePanels(); this.renderAtts(); this.updateSend(); this.changed();
    }

    resetPoll() {
        const w = this.refs.pollOptions; w.replaceChildren();
        for (let i = 1; i <= 2; i++) w.append(this.pollInput(i));
        this.refs.pollQuestion.value = '';
    }

    pollInput(n) { const i = el('input', 'cc-poll-opt'); i.placeholder = this.t('pollOption', { n }); i.setAttribute('aria-label', i.placeholder); return i; }
    addPollOption() { const w = this.refs.pollOptions; if (w.children.length < 6) w.append(this.pollInput(w.children.length + 1)); }

    createPoll() {
        const q = this.refs.pollQuestion.value.trim();
        const opts = Array.from(this.refs.pollOptions.querySelectorAll('input')).map((i) => i.value.trim()).filter(Boolean);
        if (!q || opts.length < 2) return;
        this.atts.push({ id: uid(), kind: 'poll', label: q, options: opts });
        this.resetPoll(); this.closePanels(); this.renderAtts(); this.updateSend(); this.changed();
    }

    async addLocation() {
        this.closePanels();
        if (!navigator.geolocation) { this.say(this.L.locationUnavailable); return; }
        const choice = await this.ask({ title: this.L.locationAsk, cancel: true, actions: [{ label: this.L.locationPin, value: 'pin' }, { label: this.L.locationLive, value: 'live', primary: true }] });
        if (!choice) return;
        const live = choice === 'live';
        navigator.geolocation.getCurrentPosition((p) => {
            this.atts.push({ id: uid(), kind: 'location', label: `${live ? this.L.liveTag : ''}${p.coords.latitude.toFixed(3)}, ${p.coords.longitude.toFixed(3)}`, expiresAt: live ? Date.now() + 9e5 : 0, lat: p.coords.latitude, lng: p.coords.longitude });
            this.renderAtts(); this.updateSend(); this.changed();
        }, () => this.say(this.L.locationDenied));
    }

    setSchedule() {
        const v = this.refs.schedInput.value; const t = v ? Date.parse(v) : 0;
        if (!t || t <= Date.now()) { this.say(this.L.scheduleInvalid); return; }
        this.sched = t; this.refs.schedBtn.classList.add('cc-active'); this.closePanels();
        this.refs.schedBtn.classList.add('cc-active');
        this.say(this.t('scheduleSet', { when: this.when(t) }));
    }

    clearSchedule(announce) { this.sched = 0; this.refs.schedBtn?.classList.remove('cc-active'); if (this.refs.schedInput) this.refs.schedInput.value = ''; if (announce) { this.closePanels(); this.say(this.L.scheduleCleared); } }
    when(t) { return new Date(t).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' }); }

    /* ------------------------------------------------- recent photos
       REAL images only (never placeholders). Sources, first that yields photos wins:
         1. native app bridge  window.NaaraNative.recentPhotos({limit}) or the Capacitor Media plugin — the device gallery itself
            (installed app builds only: a web page is not allowed to list a phone's photo library);
         2. `recentEndpoint` — the member's recent uploads, served by our own backend;
         3. this device's own history — photos the member attached/pasted here recently, kept on this device only, per account.
       The first tile is always "Browse" (the OS photo picker, which on phones itself opens on the newest photos). */
    scopeKey() { return `recents:${this.o.scope || 'guest'}`; }

    async nativeRecents(limit) {
        try {
            if (window.NaaraNative?.recentPhotos) return await window.NaaraNative.recentPhotos({ limit });
            const M = window.Capacitor?.isNativePlatform?.() && window.Capacitor?.Plugins?.Media;
            if (!M?.getMedias) return [];
            const res = await M.getMedias({ quantity: limit, types: 'photos', sort: [{ key: 'creationDate', ascending: false }] });
            return (res.medias || []).map((m) => ({
                id: `n-${m.identifier}`, tags: '',
                thumb: m.data ? (String(m.data).startsWith('data:') ? m.data : `data:image/jpeg;base64,${m.data}`) : '',
                file: async () => { const r = await M.getMediaByIdentifier({ identifier: m.identifier }); const blob = await (await fetch(window.Capacitor.convertFileSrc(r.path))).blob(); return new File([blob], `photo-${m.identifier}.jpg`, { type: blob.type || 'image/jpeg' }); },
            })).filter((x) => x.thumb);
        } catch { return []; }
    }

    async serverRecents() {
        const o = this.o; if (!o.recentEndpoint) return [];
        try {
            const list = await (await fetch(o.recentEndpoint, { headers: o.headers, credentials: o.credentials })).json();
            return list.map((x) => ({ id: `u-${x.id}`, tags: x.tags || '', thumb: x.thumb || x.url, file: async () => { const b = await (await fetch(x.url, { credentials: o.credentials })).blob(); return new File([b], `${x.id}.jpg`, { type: b.type || 'image/jpeg' }); } }));
        } catch { return []; }
    }

    async localRecents() {
        const list = (await kv.get(this.scopeKey())) || []; const cutoff = Date.now() - 30 * 864e5;
        return list.filter((x) => x.at > cutoff).map((x) => ({ id: x.id, tags: x.tags || '', thumb: x.thumb, local: true, file: async () => new File([x.blob], x.name, { type: x.type }) }));
    }

    /** Remember a photo the member just attached (thumbnail + the real file) so it is one tap away next time. Never keeps GIF/video/docs. */
    async recordRecent(file) {
        if (!file.type.startsWith('image/') || file.size > 3 * 1048576) return;
        try {
            const im = await loadImage(file); const sc = Math.min(1, 160 / Math.max(im.naturalWidth, im.naturalHeight));
            const c = document.createElement('canvas'); c.width = Math.round(im.naturalWidth * sc); c.height = Math.round(im.naturalHeight * sc);
            c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); URL.revokeObjectURL(im.src);
            const id = `${file.name}|${file.size}`; const key = this.scopeKey();
            const list = ((await kv.get(key)) || []).filter((x) => x.id !== id);
            list.unshift({ id, name: file.name, type: file.type, blob: file, thumb: c.toDataURL('image/jpeg', 0.7), at: Date.now(), tags: file.name.replace(/\.\w+$/, '').replace(/[^a-z0-9]+/gi, ' ').toLowerCase() });
            await kv.set(key, list.slice(0, 12));
        } catch { /* a recents failure must never block attaching */ }
    }

    async renderRecent() {
        const strip = this.refs.recentStrip; const label = this.refs.recentLabel; const o = this.o;
        if (!this.o.attachKinds.includes('media')) { strip.hidden = true; label.hidden = true; return; }
        const my = this.recentSeq = (this.recentSeq || 0) + 1;
        let list = await this.nativeRecents(12); let source = 'native';
        if (!list.length) { list = await this.serverRecents(); source = 'server'; }
        if (!list.length) { list = await this.localRecents(); source = 'local'; }
        if (my !== this.recentSeq) return;
        const t = this.refs.text.value.toLowerCase();
        list = list.map((x, i) => ({ ...x, i, hit: String(x.tags || '').split(' ').filter((w) => w.length > 2 && t.includes(w)).length })).sort((a, b) => b.hit - a.hit || a.i - b.i);
        this.recentSel ||= new Set(); strip.replaceChildren(); strip.hidden = label.hidden = false;
        label.replaceChildren(el('span', '', this.L.recentPhotos));
        if (source === 'local' && list.length) { const clr = el('button', 'cc-link', this.L.clearRecents); clr.type = 'button'; clr.onclick = async () => { await kv.set(this.scopeKey(), []); this.renderRecent(); }; label.append(clr); }
        const browse = el('button', 'cc-rthumb cc-rbrowse'); browse.type = 'button'; browse.innerHTML = ICONS.image; browse.append(el('span', '', this.L.browse));
        browse.onclick = () => this.pickKind('media'); strip.append(browse);
        list.forEach((img) => {
            const c = el('button', `cc-rthumb${this.recentSel.has(img.id) ? ' cc-sel' : ''}${img.hit ? ' cc-hit' : ''}`); c.type = 'button';
            c.style.backgroundImage = `url("${String(img.thumb).replace(/"/g, '%22')}")`; c.setAttribute('aria-pressed', this.recentSel.has(img.id) ? 'true' : 'false'); c.setAttribute('aria-label', String(img.tags || '').split(' ')[0] || this.L.recentPhotos);
            c.onclick = () => this.toggleRecent(img, c); strip.append(c);
        });
        if (!list.length) { const hint = el('div', 'cc-rempty', this.L.recentEmpty); strip.append(hint); }
    }

    async toggleRecent(img, node) {
        this.recentSel ||= new Set();
        if (this.recentSel.has(img.id)) { this.recentSel.delete(img.id); const a = this.atts.find((x) => x.recentId === img.id); if (a) this.removeAtt(a.id); node.classList.remove('cc-sel'); node.setAttribute('aria-pressed', 'false'); return; }
        if (this.atts.filter((a) => a.kind === 'file').length >= this.o.maxFiles) { this.say(this.t('tooMany', { n: this.o.maxFiles })); return; }
        try {
            const f = await img.file();
            this.recentSel.add(img.id); node.classList.add('cc-sel'); node.setAttribute('aria-pressed', 'true');
            const before = this.atts.length; await this.addFiles([f], { skipRecord: true });
            const a = this.atts[before]; if (a) a.recentId = img.id; else { this.recentSel.delete(img.id); node.classList.remove('cc-sel'); }
        } catch { this.say(this.t('failed', { err: 'photo' })); }
    }

    async loadHistory() {
        const o = this.o; if (!o.historyEndpoint) return;
        try { this.history = await (await fetch(`${o.historyEndpoint}${o.historyEndpoint.includes('?') ? '&' : '?'}chat=${encodeURIComponent(o.convoId)}`, { headers: o.headers, credentials: o.credentials })).json(); } catch { this.history = []; }
    }

    /* -------------------------------------------------------------- GIFs */
    bindGif() {
        const r = this.refs; const o = this.o;
        this.on(r.gifSearch, 'input', () => this.renderGifs(r.gifSearch.value));
        this.on(r.gImg, 'click', () => this.pickFiles('image/*', true, (fs) => this.gifFromImages(fs)));
        this.on(r.gVid, 'click', () => this.pickFiles('video/*', false, (fs) => this.gifFromVideo(fs[0])));
        this.syncGifs = o.gifEndpoint;
    }

    pickFiles(accept, multiple, cb) { const i = el('input'); i.type = 'file'; i.accept = accept; i.multiple = multiple; i.onchange = () => i.files.length && cb(Array.from(i.files)); i.click(); }

    async loadPresets() {
        const old = ls.get('cc_gif_presets', []); const v = await kv.get('gifs');
        this.presets = (v && v.length) ? v : old;
        if (!(v && v.length) && old.length) { await this.savePresets(); ls.del('cc_gif_presets'); }
        const o = this.o;
        if (o.gifEndpoint) {
            try {
                const list = await (await fetch(o.gifEndpoint, { headers: o.headers, credentials: o.credentials })).json(); let changed = false;
                list.forEach((p) => { if (!this.presets.some((x) => x.id === p.id)) { this.presets.push(p); changed = true; } });
                if (changed) await this.savePresets();
            } catch { /* offline: local presets still work */ }
        }
        if (this.openPanel === 'pGif') this.renderGifs(this.refs.gifSearch.value);
    }

    async savePresets() {
        const ok = await kv.set('gifs', this.presets); const o = this.o;
        if (o.gifEndpoint) fetch(o.gifEndpoint, { method: 'PUT', headers: { 'Content-Type': 'application/json', ...o.headers }, credentials: o.credentials, body: JSON.stringify(this.presets) }).catch(() => {});
        return ok;
    }

    renderGifs(q) {
        q = (q || '').toLowerCase().trim(); const grid = this.refs.gifGrid; grid.replaceChildren();
        const list = this.presets.filter((p) => p.name.toLowerCase().includes(q));
        if (!list.length) grid.append(el('div', 'cc-empty', this.presets.length ? this.t('noGifNamed', { q }) : this.L.noGifs));
        list.forEach((p) => {
            const c = el('div', 'cc-gcell'); c.tabIndex = 0; c.setAttribute('role', 'button'); c.setAttribute('aria-label', p.name);
            const img = el('img'); img.src = p.url; img.alt = p.name;
            const name = el('b', '', p.name);
            const ren = el('button', 'cc-gi cc-gl'); ren.type = 'button'; ren.innerHTML = ICONS.pencil; ren.setAttribute('aria-label', this.L.rename);
            const del = el('button', 'cc-gi cc-gr'); del.type = 'button'; del.innerHTML = ICONS.x; del.setAttribute('aria-label', this.L.del);
            ren.onclick = async (e) => { e.stopPropagation(); const n = await this.ask({ title: this.L.renameGif, input: { value: p.name } }); if (n) { p.name = n; this.savePresets(); this.show('pGif'); this.renderGifs(this.refs.gifSearch.value); } else this.show('pGif'); };
            del.onclick = async (e) => { e.stopPropagation(); const ok = await this.ask({ title: this.t('deleteGif', { name: p.name }), actions: [{ label: this.L.del, value: true, primary: true }] }); if (ok) { this.presets = this.presets.filter((x) => x !== p); this.savePresets(); } this.show('pGif'); this.renderGifs(this.refs.gifSearch.value); };
            c.onclick = () => this.sendPreset(p);
            c.onkeydown = (e) => { if (e.key === 'Enter') this.sendPreset(p); };
            c.append(img, name, ren, del); grid.append(c);
        });
        this.webGifs(q);
    }

    webGifs(q) {
        const o = this.o; if (!o.gifSearchEndpoint || !q) return;
        const my = this.gifSeq = (this.gifSeq || 0) + 1;
        fetch(`${o.gifSearchEndpoint}${o.gifSearchEndpoint.includes('?') ? '&' : '?'}q=${encodeURIComponent(q)}`, { headers: o.headers, credentials: o.credentials }).then((r) => r.json()).then((list) => {
            if (my !== this.gifSeq) return;
            const grid = this.refs.gifGrid; grid.append(el('div', 'cc-empty', this.L.fromWeb));
            list.slice(0, 12).forEach((g) => {
                const nm = g.name || q; const c = el('div', 'cc-gcell'); c.tabIndex = 0; c.setAttribute('role', 'button'); c.setAttribute('aria-label', nm);
                const img = el('img'); img.src = g.url; img.alt = nm;
                const star = el('button', 'cc-gi cc-gl'); star.type = 'button'; star.innerHTML = ICONS.star; star.setAttribute('aria-label', this.L.save);
                star.onclick = async (e) => { e.stopPropagation(); try { const b = await (await fetch(g.url, { credentials: o.credentials })).blob(); this.presets.unshift({ id: Date.now(), name: nm, url: await blobToDataUrl(b) }); this.savePresets(); this.renderGifs(this.refs.gifSearch.value); } catch { this.say(this.L.gifStorage); } };
                c.onclick = () => this.sendPreset({ url: g.url, name: nm });
                c.append(img, el('b', '', nm), star); grid.append(c);
            });
        }).catch(() => {});
    }

    async sendPreset(p) {
        try {
            const b = p.url.startsWith('data:') ? dataUrlToBlob(p.url) : await (await fetch(p.url, { credentials: this.o.credentials })).blob();
            this.atts.push({ id: uid(), kind: 'file', file: new File([b], `${p.name}.gif`, { type: 'image/gif' }), url: URL.createObjectURL(b) });
            this.renderAtts(); this.updateSend(); this.closePanels();
            if (this.field) this.changed(); else this.handleSend();
        } catch { this.say(this.t('failed', { err: 'gif' })); }
    }

    gifForm(title, bodyNode, build) {
        const mk = this.refs.pGifMaker; this.show('pGifMaker'); mk.replaceChildren();
        const form = el('div', 'cc-form'); const nm = el('input'); nm.placeholder = this.L.nameGif; nm.setAttribute('aria-label', this.L.nameGif); form.append(nm);
        const cancel = el('button', 'cc-chip', this.L.cancel); cancel.type = 'button'; cancel.onclick = () => { this.show('pGif'); this.renderGifs(''); };
        const ok = el('button', 'cc-chip cc-primary', this.L.saveGif); ok.type = 'button';
        ok.onclick = async () => {
            ok.disabled = true; ok.textContent = this.L.building;
            try {
                const bytes = await build((n) => { ok.textContent = this.t('frame', { n }); });
                const url = await blobToDataUrl(new Blob([bytes], { type: 'image/gif' }));
                this.presets.unshift({ id: Date.now(), name: nm.value.trim() || `GIF ${this.presets.length + 1}`, url });
                const saved = await this.savePresets();
                if (!saved) { this.presets.shift(); this.say(this.L.gifStorage); } else this.say(this.t('gifSaved', { name: this.presets[0].name }));
                this.show('pGif'); this.renderGifs('');
            } catch { this.say(this.L.gifFailed); this.closePanels(); }
        };
        const acts = el('div', 'cc-actions'); acts.append(cancel, ok);
        mk.append(el('div', 'cc-dtitle', title), bodyNode, form, acts);
    }

    async gifFromImages(fs) {
        if (fs.length < 2 || fs.length > 7) { this.say(this.t('gifNeedImages', { n: fs.length })); return; }
        const im = await Promise.all(fs.map(loadImage));
        const body = el('div'); const th = el('div', 'cc-gthumbs'); im.forEach((i) => { const t = el('img'); t.src = i.src; t.alt = ''; th.append(t); });
        const row = el('label', 'cc-grow', `${this.L.timePerImage} `); const sel = el('select'); [['30', '0.3s'], ['60', '0.6s'], ['100', '1s'], ['200', '2s']].forEach(([v, l]) => { const o = el('option', '', l); o.value = v; if (v === '60') o.selected = true; sel.append(o); }); row.append(sel);
        body.append(th, row);
        this.gifForm(`${this.L.imagesToGif} (${im.length})`, body, async () => {
            const W = 320; const H = Math.round((W * im[0].naturalHeight) / im[0].naturalWidth);
            return encodeGif(im.map((i) => grabFrame(i, i.naturalWidth, i.naturalHeight, W, H)), W, H, +sel.value);
        });
    }

    gifFromVideo(file) {
        if (!file) return;
        const u = URL.createObjectURL(file); const body = el('div'); const v = el('video'); v.src = u; v.controls = true; v.muted = true; v.playsInline = true; v.className = 'cc-gvideo';
        const mkRange = (label) => { const row = el('div', 'cc-grow'); const lab = el('span', '', label); const rg = el('input'); rg.type = 'range'; rg.min = '0'; rg.max = '1'; rg.step = '.1'; rg.setAttribute('aria-label', label); const val = el('span', '', '0.0s'); row.append(lab, rg, val); return { row, rg, val }; };
        const S = mkRange(this.L.start); const E = mkRange(this.L.end); E.rg.value = '1';
        const now = el('div', 'cc-grow'); const sn = el('button', 'cc-chip', this.L.startNow); const en = el('button', 'cc-chip', this.L.endNow); sn.type = en.type = 'button'; now.append(sn, en);
        body.append(v, S.row, E.row, now);
        const sync = (w) => {
            let a = +S.rg.value; let b = +E.rg.value;
            if (b <= a) { if (w === 's') b = a + 0.5; else a = Math.max(0, b - 0.5); }
            if (b - a > 6) { if (w === 's') b = a + 6; else a = b - 6; }
            S.rg.value = a; E.rg.value = b; S.val.textContent = `${(+S.rg.value).toFixed(1)}s`; E.val.textContent = `${(+E.rg.value).toFixed(1)}s`;
            if (w) v.currentTime = w === 's' ? a : b;
        };
        v.onloadedmetadata = () => { S.rg.max = E.rg.max = v.duration.toFixed(1); E.rg.value = Math.min(3, v.duration).toFixed(1); sync(); };
        S.rg.oninput = () => sync('s'); E.rg.oninput = () => sync('e');
        sn.onclick = () => { S.rg.value = v.currentTime; sync('s'); }; en.onclick = () => { E.rg.value = v.currentTime; sync('e'); };
        this.gifForm(this.L.videoToGif, body, async (progress) => {
            const a = +S.rg.value; const b = +E.rg.value; const W = Math.min(320, v.videoWidth); const H = Math.round((W * v.videoHeight) / v.videoWidth); const fr = []; v.pause();
            for (let t = a; t < b; t += 0.1) { v.currentTime = t + 0.001; await new Promise((res) => { v.onseeked = res; setTimeout(res, 1500); }); fr.push(grabFrame(v, v.videoWidth, v.videoHeight, W, H)); progress(fr.length); }
            URL.revokeObjectURL(u);
            return encodeGif(fr, W, H, 10);
        });
    }

    /* ------------------------------------------------------------- voice */
    bindVoice() {
        const r = this.refs;
        this.speeds = [1, 1.5, 2]; this.speedIdx = Math.max(0, this.speeds.indexOf(ls.get(`cc_speed_${this.o.convoId}`, 1)));
        this.on(r.micBtn, 'click', () => this.startVoice());
        this.on(r.primerNo, 'click', () => this.closePanels());
        this.on(r.primerYes, 'click', () => { this.closePanels(); this.beginRecording(); });
        this.on(r.vPause, 'click', () => this.voice && (this.voice.state === 'active' ? this.pauseVoice() : this.resumeVoice()));
        this.on(r.vPlay, 'click', () => this.togglePreview());
        this.on(r.vSpeed, 'click', () => { this.speedIdx = (this.speedIdx + 1) % this.speeds.length; ls.set(`cc_speed_${this.o.convoId}`, this.speeds[this.speedIdx]); r.vSpeed.textContent = `${this.speeds[this.speedIdx]}x`; if (this.audio) this.audio.playbackRate = this.speeds[this.speedIdx]; });
        this.on(r.vTrash, 'click', () => { this.voice?.discard(); this.endVoiceUi(); });
        this.on(r.vSend, 'click', () => this.sendVoice());
    }

    startVoice() {
        this.closePanels();
        if (!voiceSupported()) { this.say(this.L.micUnsupported, this.L.dismiss, () => {}, 0); return; }
        if (this.o.micPrimer && !ls.get('cc_mic_ok', false)) { this.show('pPrimer'); this.refs.primerYes.focus(); return; }
        this.beginRecording();
    }

    async beginRecording() {
        const r = this.refs;
        this.voice = new VoiceCapture({
            lang: this.lang, captions: this.has('captions') || this.has('voice'),
            onLevel: (l) => this.liveBar(l),
            onTick: (sec, capped) => { r.vTimer.textContent = fmtTime(sec); if (capped) this.sendVoice(); },
            onCaption: (t) => { r.vCap.textContent = t === null ? this.L.noCaptions : (t || this.L.listening); },
        });
        try { await this.voice.start(); } catch (e) {
            this.voice = null;
            this.say(e && (e.name === 'NotAllowedError' || e.name === 'SecurityError') ? this.L.micDenied : this.L.micError, this.L.dismiss, () => {}, 0);
            return;
        }
        ls.set('cc_mic_ok', true);
        r.vBg.replaceChildren(); r.vFg.replaceChildren(); r.vProg.style.width = '0%'; r.vPlay.hidden = true; r.vTimer.textContent = '0:00'; r.vCap.textContent = this.L.listening;
        r.vSpeed.textContent = `${this.speeds[this.speedIdx]}x`; this.setPauseLabel('active');
        r.controls.hidden = true; r.voice.hidden = false; r.vPause.focus();
    }

    setPauseLabel(state) { const b = this.refs.vPause; b.innerHTML = (state === 'active' ? ICONS.pause : ICONS.mic); b.append(el('span', '', state === 'active' ? this.L.pause : this.L.resume)); }

    liveBar(level) {
        const bg = this.refs.vBg; const b = el('div', 'cc-wbar'); b.style.height = `${Math.max(3, level * 24)}px`; bg.append(b);
        while (bg.children.length > 40) bg.removeChild(bg.firstChild);
    }

    pauseVoice() {
        this.voice.pause(); this.setPauseLabel('paused'); this.refs.vPlay.hidden = false; this.refs.vPlay.innerHTML = ICONS.play;
        setTimeout(() => this.buildPreview(), 80);
    }

    buildPreview() {
        if (!this.voice) return;
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = this.voice.previewUrl();
        if (this.audio) { this.audio.pause(); this.audio = null; }
        const levels = this.voice.levels; const bg = this.refs.vBg; const fg = this.refs.vFg; bg.replaceChildren(); fg.replaceChildren();
        const n = 40; const size = Math.max(1, levels.length / n);
        for (let i = 0; i < Math.min(n, levels.length); i++) {
            const seg = levels.slice(Math.floor(i * size), Math.floor((i + 1) * size)); const l = seg.reduce((a, b) => a + b, 0) / (seg.length || 1);
            [bg, fg].forEach((p) => { const b = el('div', 'cc-wbar'); b.style.height = `${Math.max(3, l * 24)}px`; p.append(b); });
        }
        this.refs.vProg.style.width = '0%';
    }

    resumeVoice() {
        this.audio?.pause(); cancelAnimationFrame(this.playRaf); this.playing = false;
        this.voice.resume(); this.setPauseLabel('active'); this.refs.vPlay.hidden = true;
        this.refs.vBg.replaceChildren(); this.refs.vFg.replaceChildren(); this.refs.vProg.style.width = '0%';
    }

    togglePreview() {
        if (!this.previewUrl) return; const r = this.refs;
        if (!this.audio) {
            this.audio = new Audio(this.previewUrl); this.audio.playbackRate = this.speeds[this.speedIdx];
            this.audio.onended = () => { this.playing = false; r.vPlay.innerHTML = ICONS.play; cancelAnimationFrame(this.playRaf); r.vProg.style.width = '0%'; };
        }
        if (this.playing) { this.audio.pause(); this.playing = false; r.vPlay.innerHTML = ICONS.play; cancelAnimationFrame(this.playRaf); return; }
        this.audio.play().catch(() => {}); this.playing = true; r.vPlay.innerHTML = ICONS.pause;
        const step = () => { if (!this.playing) return; if (this.audio.duration) r.vProg.style.width = `${Math.min(100, (this.audio.currentTime / this.audio.duration) * 100)}%`; this.playRaf = requestAnimationFrame(step); };
        step();
    }

    endVoiceUi() {
        this.audio?.pause(); this.audio = null; this.playing = false; cancelAnimationFrame(this.playRaf);
        if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); this.previewUrl = null; }
        this.voice = null; this.refs.voice.hidden = true; this.refs.controls.hidden = false;
    }

    async sendVoice() {
        if (!this.voice) return;
        const v = this.voice; const res = await v.finish(); this.endVoiceUi();
        if (!res.blob.size) return;
        const note = { blob: res.blob, duration: Math.round(res.duration * 10) / 10, transcript: res.transcript, mime: res.mime, ext: res.ext };
        if (this.field) { this.voiceNote = note; this.renderAtts(); this.updateSend(); this.changed(); return; }
        const s = { text: '', atts: [], all: true, at: this.sched, voice: note };
        this.sched = 0; this.refs.schedBtn?.classList.remove('cc-active');
        const t = setTimeout(() => this.enqueue(s), this.o.undoMs || 0);
        this.say(this.L.sendingVoice, this.L.cancel, () => clearTimeout(t), this.o.undoMs || 1500);
    }

    /* -------------------------------------------------------- send flow */
    snapshot() { return { text: this.refs.text.value.trim(), atts: this.atts.slice(), all: this.albumAll, at: this.sched, voice: null }; }

    clearInputs() {
        const t = this.refs.text; t.value = ''; this.atts = []; this.sched = 0; this.refs.schedBtn?.classList.remove('cc-active');
        ls.del(this.draftKey); this.recentSel?.clear(); this.renderAtts(); this.autoGrow(); this.closePanels(); this.checkHints(); this.updateSend();
    }

    handleSend() {
        if (this.field || this.pending || !this.hasContent()) return;
        const snap = this.snapshot(); this.clearInputs();
        if (!this.o.undoMs) { this.enqueue(snap); return; }
        this.ring(true);
        this.pending = { snap, t: setTimeout(() => { this.ring(false); this.pending = null; this.updateSend(); this.enqueue(snap); }, this.o.undoMs) };
        this.updateSend();
    }

    ring(on) {
        const b = this.refs.sendBtn; b.classList.toggle('cc-undo', on); b.style.setProperty('--cc-undo', `${this.o.undoMs}ms`);
        b.innerHTML = on ? `<svg class="cc-ring" viewBox="0 0 36 36" aria-hidden="true"><circle cx="18" cy="18" r="16"/></svg><span class="cc-undo-x">${ICONS.x}</span>` : ICONS.send;
        b.setAttribute('aria-label', on ? this.L.undo : this.L.send);
    }

    undoSend() {
        if (!this.pending) return;
        clearTimeout(this.pending.t); const s = this.pending.snap; this.pending = null; this.ring(false);
        this.restoreSnap(s); this.say(this.L.cancelled);
    }

    restoreSnap(s) {
        if (this.refs.text.value.trim() === '' && !this.atts.length) { this.refs.text.value = s.text; this.atts = s.atts; }
        else { s.atts.forEach((a) => a.url && URL.revokeObjectURL(a.url)); }
        this.renderAtts(); this.autoGrow(); this.updateSend();
    }

    enqueue(s) {
        s.id = uid(); s.tries = 0; this.queue.push(s); this.persistQ();
        this.emit('queued', { id: s.id, convoId: this.o.convoId });
        if (s.at > Date.now()) this.say(this.t('scheduled', { when: this.when(s.at) }));
        else if (!navigator.onLine) this.say(this.L.offline, null, null, 0);
        this.tick();
    }

    persistQ() {
        if (!this.o.endpoint) return;
        kv.set(`queue:${this.o.convoId}`, this.queue.map((s) => ({ id: s.id, text: s.text, at: s.at, all: s.all, voice: s.voice, atts: s.atts.map((a) => ({ ...a, url: null })) })));
    }

    async restoreQueue() {
        if (this.field || !this.o.endpoint) return;
        const q = await kv.get(`queue:${this.o.convoId}`);
        (q || []).forEach((m) => { m.atts.forEach((a) => { if (a.file && (a.file.type.startsWith('image/') || a.file.type.startsWith('video/'))) a.url = URL.createObjectURL(a.file); }); this.queue.push(m); });
        if (this.queue.length) { this.say(this.t('restored', { n: this.queue.length })); this.tick(); }
    }

    payloadOf(s) {
        const files = []; const items = [];
        s.atts.forEach((a) => { if (a.kind === 'file') { files.push(a.file); items.push({ type: 'file', name: a.file.name, compressed: !!a.original }); } else items.push(this.itemOf(a)); });
        return { id: s.id, convoId: this.o.convoId, text: s.text, files, originals: s.atts.map((a) => a.original || null), items, voice: s.voice, scheduledAt: s.at || null, captionAppliesTo: files.length > 1 ? (s.all ? 'all' : 'first') : null };
    }

    async transmit(s) {
        const payload = this.payloadOf(s);
        const ev = this.emit('transmit', { payload, promise: null, wait(p) { this.promise = p; } }, true);
        if (ev.detail.promise) return ev.detail.promise;
        if (!this.o.endpoint) throw Object.assign(new Error('not connected'), { fatal: true });
        const meta = { clientId: s.id, chat: payload.convoId, text: payload.text, scheduledAt: payload.scheduledAt, captionAppliesTo: payload.captionAppliesTo, items: payload.items };
        if (s.voice) meta.voice = { duration: s.voice.duration, transcript: s.voice.transcript };
        const fd = new FormData(); fd.append('meta', JSON.stringify(meta));
        payload.files.forEach((f, i) => { fd.append(`files[${i}]`, f, f.name); if (payload.originals[i]) fd.append(`originals[${i}]`, payload.originals[i], payload.originals[i].name); });
        if (s.voice) fd.append('voice', s.voice.blob, `voice-note.${s.voice.ext}`);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const r = await fetch(this.o.endpoint, { method: 'POST', headers: { 'Idempotency-Key': s.id, ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}), ...this.o.headers }, body: fd, credentials: this.o.credentials });
        if (!r.ok) throw Object.assign(new Error(`server said ${r.status}`), { fatal: r.status >= 400 && r.status < 500 && r.status !== 408 && r.status !== 429 });
    }

    tick() {
        for (const s of this.queue.slice()) {
            if (s.busy || (s.retryAt || 0) > Date.now() || (s.at > Date.now() && !this.o.endpoint) || !navigator.onLine) continue;
            s.busy = true;
            this.transmit(s).then(() => this.sentOk(s)).catch((e) => this.sentFail(s, e));
        }
        if (this.queue.some((s) => !navigator.onLine && (s.at || 0) <= Date.now())) this.say(this.t('offlineCount', { n: this.queue.length }), null, null, 0);
    }

    sentOk(s) {
        this.queue.splice(this.queue.indexOf(s), 1); this.persistQ();
        const sent = ls.get(`cc_sent_${this.o.convoId}`, {}); s.atts.forEach((a) => { if (a.kind === 'file') sent[this.fkey(a.original || a.file)] = Date.now(); }); ls.set(`cc_sent_${this.o.convoId}`, sent);
        s.atts.forEach((a) => { if (a.url) URL.revokeObjectURL(a.url); if (a.originalUrl) URL.revokeObjectURL(a.originalUrl); });
        this.emit('sent', { id: s.id, convoId: this.o.convoId });
        this.say(s.at > Date.now() ? this.L.scheduledOk : this.L.sent, null, null, 2500);
    }

    sentFail(s, e) {
        s.busy = false; s.tries = (s.tries || 0) + 1; const msg = (e && e.message) || 'error';
        this.emit('error', { id: s.id, convoId: this.o.convoId, message: msg, fatal: !!(e && e.fatal) });
        if (e && e.fatal) { // retrying cannot help (validation/auth) — hand the message back to the user
            this.queue.splice(this.queue.indexOf(s), 1); this.persistQ();
            if (!s.voice) this.restoreSnap(s);
            this.say(this.t('failedFinal', { err: msg }), null, null, 8000); return;
        }
        if (s.tries >= 6) { this.say(this.t('failedFinal', { err: msg }), this.L.retry, () => { s.tries = 0; s.retryAt = 0; this.tick(); }, 0); return; }
        s.retryAt = Date.now() + Math.min(30000, 2000 * 2 ** s.tries);
        this.say(this.t('failed', { err: msg }), null, null, 5000);
    }

    /* ----------------------------------------------------- host-facing API */
    /** Clear everything (text, attachments, recorded note) — hosts call this after they have taken the content. */
    reset() {
        this.voice?.discard(); if (!this.refs.voice.hidden) this.endVoiceUi();
        this.voiceNote = null; this.clearInputs(); this.changed();
    }

    setDisabled(on) { this.refs.text.disabled = on; this.root.classList.toggle('cc-disabled', on); }
    getState() { return { text: this.refs.text.value, files: this.atts.filter((a) => a.kind === 'file').map((a) => a.file), voice: this.voiceNote }; }
}
