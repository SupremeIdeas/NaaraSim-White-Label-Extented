// Liquid-fill upload progress (Engineering v3, Phase 1A). One module for every upload surface:
//   * Livewire uploads are picked up automatically from the `livewire-upload-*` events that bubble from the file input, so no
//     component has to change;
//   * XHR / chunked uploaders call `window.nxUpload.track({...})` and drive the same card.
// States: preparing -> uploading -> processing -> done | failed | cancelled. The card is `role=progressbar`, announces 25/50/75/100%
// through a polite live region, and swaps the wave for a plain bar under `prefers-reduced-motion` (see nx-upload.css).
const items = new Map();       // key -> item
let host = null, live = null;

const reduced = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const bytes = (n) => (n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB');

function ensureHost() {
    if (host && document.body.contains(host)) return host;
    host = document.createElement('div');
    host.className = 'nxu-host';
    host.setAttribute('data-nx-upload-host', '');
    live = document.createElement('div');
    live.className = 'nxu-sr';
    live.setAttribute('aria-live', 'polite');
    live.setAttribute('role', 'status');
    host.appendChild(live);
    document.body.appendChild(host);
    return host;
}

const LABEL = { preparing: 'Preparing', uploading: 'Uploading', processing: 'Processing', done: 'Done', failed: 'Upload failed', cancelled: 'Cancelled' };

function render(it) {
    if (!it.el) {
        it.el = document.createElement('div');
        it.el.className = 'nxu-card';
        it.el.innerHTML = `
          <div class="nxu-vessel" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="Upload progress">
            <svg viewBox="0 0 64 64" aria-hidden="true">
              <defs><clipPath id="nxu-c-${it.id}"><circle cx="32" cy="32" r="28"/></clipPath></defs>
              <circle class="nxu-ring" cx="32" cy="32" r="30"/>
              <g clip-path="url(#nxu-c-${it.id})">
                <g class="nxu-liquid" data-liquid>
                  <path class="nxu-wave nxu-wave--b" d="M-64 6 q16 -6 32 0 t32 0 t32 0 t32 0 t32 0 t32 0 V80 H-64Z"/>
                  <path class="nxu-wave nxu-wave--a" d="M-64 6 q16 6 32 0 t32 0 t32 0 t32 0 t32 0 t32 0 V80 H-64Z"/>
                </g>
              </g>
            </svg>
            <span class="nxu-pct" data-pct>0%</span>
          </div>
          <div class="nxu-meta">
            <p class="nxu-name" data-name></p>
            <p class="nxu-state" data-state></p>
            <div class="nxu-bar" aria-hidden="true"><span data-bar></span></div>
          </div>
          <div class="nxu-actions" data-actions></div>`;
        ensureHost().appendChild(it.el);
        requestAnimationFrame(() => it.el.classList.add('is-in'));
    }
    const pct = Math.max(0, Math.min(100, Math.round(it.pct)));
    const done = it.state === 'done', bad = it.state === 'failed' || it.state === 'cancelled';
    it.el.dataset.state = it.state;
    const v = it.el.querySelector('.nxu-vessel');
    v.setAttribute('aria-valuenow', String(pct));
    v.setAttribute('aria-valuetext', `${LABEL[it.state]} ${pct}%`);
    it.el.querySelector('[data-pct]').textContent = done ? '✓' : (it.state === 'processing' ? '…' : pct + '%');
    it.el.querySelector('[data-liquid]').style.transform = `translateY(${64 - (pct / 100) * 58}px)`;
    it.el.querySelector('[data-bar]').style.width = pct + '%';
    it.el.querySelector('[data-name]').innerHTML = esc(it.name || 'File') + (it.size ? ` <span>${bytes(it.size)}</span>` : '');
    it.el.querySelector('[data-state]').textContent = it.message || LABEL[it.state] + (it.state === 'uploading' && it.eta ? ` · ${Math.max(1, Math.round(it.eta))}s left` : '');
    const a = it.el.querySelector('[data-actions]');
    a.innerHTML = '';
    if (!done && !bad && it.cancel) a.appendChild(button('Cancel', () => it.cancel()));
    if (it.state === 'failed' && it.retry) a.appendChild(button('Retry', () => it.retry()));
    // announce at 25/50/75/100
    const mark = pct >= 100 ? 100 : Math.floor(pct / 25) * 25;
    if (mark > 0 && mark !== it.announced && it.state === 'uploading') { it.announced = mark; live.textContent = `${it.name || 'Upload'} ${mark} percent`; }
    if (done && !it.said) { it.said = true; live.textContent = `${it.name || 'Upload'} finished`; }
    if (bad && !it.said) { it.said = true; live.textContent = `${it.name || 'Upload'} ${LABEL[it.state].toLowerCase()}`; }
}

function button(label, fn) {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'nxu-btn'; b.textContent = label; b.addEventListener('click', fn);
    return b;
}

function remove(it, delay) {
    setTimeout(() => {
        if (!it.el) return;
        it.el.classList.remove('is-in');
        setTimeout(() => { it.el && it.el.remove(); items.delete(it.key); }, 260);
    }, delay);
}

function update(it, patch) {
    Object.assign(it, patch);
    render(it);
    if (it.state === 'done') remove(it, 1600);
    if (it.state === 'cancelled') remove(it, 900);
    if (it.state === 'failed') remove(it, 9000);
}

let seq = 0;
function start(key, init) {
    let it = items.get(key);
    if (it) { it.el && it.el.remove(); }
    it = { key, id: ++seq, state: 'preparing', pct: 0, name: '', size: 0, announced: 0, said: false, started: Date.now(), ...init };
    items.set(key, it);
    render(it);
    return it;
}

// ---- public API for XHR / chunked uploaders -------------------------------------------------------------------------------------------
// const t = nxUpload.track({ name, size, cancel, retry }); t.progress(0..100, loadedBytes); t.processing(); t.done(); t.fail('why'); t.cancelled();
function track(opts = {}) {
    const key = opts.key || 'x' + (++seq);
    const it = start(key, { name: opts.name, size: opts.size, cancel: opts.cancel, retry: opts.retry });
    return {
        progress(pct, loaded) {
            const sec = (Date.now() - it.started) / 1000;
            const eta = loaded && it.size && sec > 1 ? (it.size - loaded) / (loaded / sec) : 0;
            update(it, { state: 'uploading', pct, eta });
        },
        processing(message) { update(it, { state: 'processing', pct: 100, message: message || 'Processing on the server' }); },
        done() { update(it, { state: 'done', pct: 100, message: '' }); },
        fail(message) { update(it, { state: 'failed', message: message || '' }); },
        cancelled() { update(it, { state: 'cancelled', message: '' }); },
    };
}

// ---- Livewire (automatic) -------------------------------------------------------------------------------------------------------------
function livewireKey(input) {
    return 'lw:' + (input.getAttribute('wire:model') || input.getAttribute('name') || input.id || Math.random());
}

function cancelLivewire(input) {
    const root = input.closest('[wire\\:id]');
    const prop = input.getAttribute('wire:model') || input.getAttribute('wire:model.live');
    try { window.Livewire.find(root.getAttribute('wire:id')).cancelUpload(prop); } catch (e) { input.dispatchEvent(new CustomEvent('livewire-upload-cancel', { bubbles: true })); }
}

function fileInfo(input) {
    const f = input.files && input.files[0];
    return { name: f ? (input.files.length > 1 ? `${input.files.length} files` : f.name) : 'File', size: f ? [...input.files].reduce((n, x) => n + x.size, 0) : 0 };
}

export function registerNxUpload() {
    if (window.nxUpload) return;
    window.nxUpload = { track };
    const on = (name, fn) => document.addEventListener(name, (e) => { const t = e.target; if (t instanceof HTMLInputElement && t.type === 'file') fn(e, t); });
    on('livewire-upload-start', (e, input) => {
        if (input.hasAttribute('data-nx-no-progress')) return;
        const info = fileInfo(input);
        const it = start(livewireKey(input), { ...info, state: 'uploading', cancel: () => cancelLivewire(input) });
        it.state = 'uploading'; render(it);
    });
    on('livewire-upload-progress', (e, input) => {
        const it = items.get(livewireKey(input)); if (!it) return;
        const pct = (e.detail && e.detail.progress) || 0;
        const sec = (Date.now() - it.started) / 1000;
        update(it, { state: 'uploading', pct, eta: it.size && pct > 3 && sec > 1 ? (sec / (pct / 100)) - sec : 0 });
    });
    on('livewire-upload-finish', (e, input) => { const it = items.get(livewireKey(input)); if (it) update(it, { state: 'done', pct: 100 }); });
    on('livewire-upload-error', (e, input) => {
        const it = items.get(livewireKey(input)); if (!it) return;
        update(it, { state: 'failed', message: 'The upload failed. Check your connection and try again.', retry: () => { it.el && it.el.remove(); items.delete(it.key); input.click(); } });
    });
    on('livewire-upload-cancel', (e, input) => { const it = items.get(livewireKey(input)); if (it) update(it, { state: 'cancelled' }); });
}
