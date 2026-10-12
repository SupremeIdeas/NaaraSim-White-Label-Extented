/*
 * Cookie consent (generic platform feature). One small, dependency-free module:
 *  - the choice lives in a first-party cookie `naara_consent` = v=<policy>&t=<unix>&f=0|1&a=0|1&m=0|1&id=<random>
 *  - nothing optional runs until the visitor says yes: scripts are shipped as <script type="text/plain" data-consent="analytics|marketing|functional">
 *    and are only turned into real scripts here, after a yes (same for <iframe data-consent-src>)
 *  - a changed policy version, or a choice older than the renewal period, asks again
 *  - Global Privacy Control is honoured as "reject" when the admin leaves that on
 *  - every choice is logged to the server as proof (the server never decides what loads)
 * Public API: window.NaaraConsent { get(), has(cat), open(), set(choices, action) }; event `naara:consent` on document.
 */
const CATS = ['functional', 'analytics', 'marketing'];
const ANALYTICS_COOKIES = [/^_ga/, /^_gid$/, /^_gat/];
const MARKETING_COOKIES = [/^_fbp$/, /^_fbc$/];

function root() { return document.querySelector('[data-cookie-root]'); }
function cfg() {
    const r = root();
    return { name: r?.dataset.cookieName || 'naara_consent', version: parseInt(r?.dataset.version || '1', 10), renewDays: parseInt(r?.dataset.renewDays || '180', 10), gpc: r?.dataset.gpc === '1', endpoint: r?.dataset.endpoint || '/cookie-consent' };
}

function readCookie(name) {
    const m = document.cookie.split('; ').find((c) => c.startsWith(name + '='));
    return m ? decodeURIComponent(m.slice(name.length + 1)) : null;
}

function parse(raw) {
    if (!raw) return null;
    const p = new URLSearchParams(raw);
    const v = parseInt(p.get('v') || '0', 10);
    const t = parseInt(p.get('t') || '0', 10);
    if (!v || !t) return null;
    return { v, t, id: p.get('id') || '', choices: { functional: p.get('f') === '1', analytics: p.get('a') === '1', marketing: p.get('m') === '1' } };
}

function writeCookie(c, choices, id, t) {
    const raw = `v=${c.version}&t=${t}&f=${choices.functional ? 1 : 0}&a=${choices.analytics ? 1 : 0}&m=${choices.marketing ? 1 : 0}&id=${id}`;
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${c.name}=${encodeURIComponent(raw)}; Max-Age=${c.renewDays * 86400}; Path=/; SameSite=Lax${secure}`;
}

function newId() {
    const a = new Uint8Array(12);
    (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.from(a, (b) => b.toString(16).padStart(2, '0')).join('');
}

/** valid = a choice for THIS policy version that has not expired; otherwise the visitor is asked. */
function state() {
    const c = cfg();
    const cur = parse(readCookie(c.name));
    if (!cur) return { status: 'none', current: null };
    const expired = Date.now() / 1000 - cur.t > c.renewDays * 86400;
    if (cur.v !== c.version || expired) return { status: 'stale', current: cur };
    return { status: 'valid', current: cur };
}

function consentModeUpdate(choices) {
    if (typeof window.gtag !== 'function') return;
    window.gtag('consent', 'update', {
        analytics_storage: choices.analytics ? 'granted' : 'denied',
        ad_storage: choices.marketing ? 'granted' : 'denied',
        ad_user_data: choices.marketing ? 'granted' : 'denied',
        ad_personalization: choices.marketing ? 'granted' : 'denied',
    });
}

/** Turn the blocked scripts and frames of every allowed category into live ones, once. */
function activate(choices) {
    CATS.forEach((cat) => {
        if (!choices[cat]) return;
        document.querySelectorAll(`script[type="text/plain"][data-consent="${cat}"]:not([data-consent-done])`).forEach((old) => {
            const s = document.createElement('script');
            [...old.attributes].forEach((a) => {
                if (['type', 'data-consent', 'data-src'].includes(a.name)) return;
                s.setAttribute(a.name, a.value);
            });
            if (old.dataset.src) s.src = old.dataset.src; else s.text = old.text;
            old.setAttribute('data-consent-done', '1');
            old.parentNode.insertBefore(s, old.nextSibling);
        });
        document.querySelectorAll(`iframe[data-consent-src][data-consent="${cat}"]:not([data-consent-done])`).forEach((f) => {
            f.src = f.dataset.consentSrc;
            f.setAttribute('data-consent-done', '1');
        });
    });
    document.querySelectorAll('[data-consent-placeholder]').forEach((el) => { el.hidden = !!choices[el.dataset.consentPlaceholder]; });
}

function expireCookies(patterns) {
    const host = location.hostname;
    const parts = host.split('.');
    const domains = [null, host, '.' + host];
    for (let i = 1; i < parts.length - 1; i++) domains.push('.' + parts.slice(i).join('.'));
    document.cookie.split('; ').forEach((kv) => {
        const n = kv.split('=')[0];
        if (!patterns.some((re) => re.test(n))) return;
        domains.forEach((d) => { document.cookie = `${n}=; Max-Age=0; Path=/; SameSite=Lax${d ? '; Domain=' + d : ''}`; });
    });
}

function log(action, choices, id) {
    try {
        const c = cfg();
        const body = JSON.stringify({ id, action, functional: choices.functional, analytics: choices.analytics, marketing: choices.marketing, path: location.pathname });
        fetch(c.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
    } catch (e) { /* proof is best effort; the choice itself is already saved in the browser */ }
}

function set(choices, action) {
    const c = cfg();
    const prev = state().current;
    const clean = { functional: !!choices.functional, analytics: !!choices.analytics, marketing: !!choices.marketing };
    const id = prev?.id || newId();
    writeCookie(c, clean, id, Math.floor(Date.now() / 1000));
    const lost = prev && ((prev.choices.analytics && !clean.analytics) || (prev.choices.marketing && !clean.marketing) || (prev.choices.functional && !clean.functional));
    if (!clean.analytics) expireCookies(ANALYTICS_COOKIES);
    if (!clean.marketing) expireCookies(MARKETING_COOKIES);
    consentModeUpdate(clean);
    activate(clean);
    log(action, clean, id);
    document.dispatchEvent(new CustomEvent('naara:consent', { detail: { choices: clean, action } }));
    hide();
    refreshReopen();
    // Something that was already running cannot be switched off from here, so a withdrawal reloads once to stop it cleanly.
    if (lost && action !== 'gpc') location.reload();
}

function show(panel) {
    const r = root();
    if (!r) return;
    r.hidden = false;
    document.documentElement.classList.toggle('ck-open', r.classList.contains('ck--modal'));
    setPanel(!!panel);
    const cur = state().current;
    r.querySelectorAll('[data-cookie-cat]').forEach((i) => { i.checked = !!cur?.choices[i.dataset.cookieCat]; });
    (r.querySelector(panel ? '[data-cookie-save]' : '[data-cookie-accept]'))?.focus({ preventScroll: true });
}

function hide() {
    const r = root();
    if (r) r.hidden = true;
    document.documentElement.classList.remove('ck-open');
}

function setPanel(open) {
    const r = root();
    if (!r) return;
    r.querySelector('[data-cookie-main]').hidden = open;
    r.querySelector('[data-cookie-panel]').hidden = !open;
    r.querySelector('[data-cookie-customise]')?.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function refreshReopen() {
    const b = document.querySelector('.ck-reopen');
    if (b) b.hidden = !root() || state().status !== 'valid';
}

function init() {
    const r = root();
    if (!r) { activate(state().status === 'valid' ? state().current.choices : {}); syncPrefs(); return; }
    const c = cfg();
    const st = state();
    if (st.status === 'valid') {
        activate(st.current.choices);
        consentModeUpdate(st.current.choices);
        hide();
    } else if (c.gpc && navigator.globalPrivacyControl === true && st.status === 'none') {
        set({ functional: false, analytics: false, marketing: false }, 'gpc');   // the browser already said no: honour it without asking
    } else {
        show(false);
    }
    refreshReopen();
    syncPrefs();
}

/** The preferences page: show the current choice, tick the switches to match it, and drive the same set() as the banner. */
function syncPrefs() {
    const page = document.querySelector('[data-pref-root]');
    if (!page) return;
    const st = state();
    const cur = st.current;
    page.querySelectorAll('[data-pref-cat]').forEach((i) => { i.checked = !!(st.status === 'valid' && cur?.choices[i.dataset.prefCat]); });
    const title = page.querySelector('[data-pref-status-title]');
    const text = page.querySelector('[data-pref-status-text]');
    if (!title || !text) return;
    if (st.status === 'valid') {
        const on = CATS.filter((c) => cur.choices[c]);
        title.textContent = on.length ? 'You allow: ' + on.join(', ') : 'You have rejected all optional cookies';
        text.textContent = 'Saved on ' + new Date(cur.t * 1000).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) + '. You can change it below at any time.';
    } else {
        title.textContent = 'You have not chosen yet';
        text.textContent = 'Until you choose, only the strictly necessary cookies are used.';
    }
}

document.addEventListener('click', (e) => {
    const p = e.target.closest('[data-pref-save],[data-pref-accept],[data-pref-reject]');
    if (p) {
        const page = document.querySelector('[data-pref-root]');
        if (p.matches('[data-pref-accept]')) set({ functional: true, analytics: true, marketing: true }, 'accept_all');
        else if (p.matches('[data-pref-reject]')) set({ functional: false, analytics: false, marketing: false }, state().current ? 'withdraw' : 'reject_all');
        else {
            const ch = { functional: false, analytics: false, marketing: false };
            page.querySelectorAll('[data-pref-cat]').forEach((i) => { ch[i.dataset.prefCat] = i.checked; });
            set(ch, 'custom');
        }
        syncPrefs();
        return;
    }
    const t = e.target.closest('[data-cookie-accept],[data-cookie-reject],[data-cookie-customise],[data-cookie-save],[data-cookie-back],[data-cookie-settings],[data-cookie-close-scrim]');
    if (!t) return;
    if (t.matches('[data-cookie-settings]') && !root()) return;   // no banner on this install: the link just goes to the page
    const r = root();
    if (t.matches('[data-cookie-accept]')) { set({ functional: true, analytics: true, marketing: true }, 'accept_all'); }
    else if (t.matches('[data-cookie-reject]')) { set({ functional: false, analytics: false, marketing: false }, state().current ? 'withdraw' : 'reject_all'); }
    else if (t.matches('[data-cookie-customise]')) { setPanel(true); r.querySelector('[data-cookie-save]')?.focus({ preventScroll: true }); }
    else if (t.matches('[data-cookie-back]')) { setPanel(false); }
    else if (t.matches('[data-cookie-save]')) {
        const ch = { functional: false, analytics: false, marketing: false };
        r.querySelectorAll('[data-cookie-cat]').forEach((i) => { ch[i.dataset.cookieCat] = i.checked; });
        set(ch, 'custom');
    } else if (t.matches('[data-cookie-settings]')) { e.preventDefault(); show(true); }
});

document.addEventListener('keydown', (e) => {
    const r = root();
    if (e.key === 'Escape' && r && !r.hidden && state().status === 'valid') hide();   // a decided visitor can dismiss the re-opened panel; an undecided one is not nagged away without a choice
});

window.NaaraConsent = {
    get: () => state().current?.choices || { functional: false, analytics: false, marketing: false },
    has: (cat) => !!(state().status === 'valid' && state().current.choices[cat]),
    open: () => show(true),
    set,
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
document.addEventListener('livewire:navigated', init);
