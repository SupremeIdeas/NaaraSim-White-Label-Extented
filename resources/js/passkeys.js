/* Fingerprint / Face / screen-lock sign-in (WebAuthn passkeys) over Fortify's passkey routes. The device keeps the private key; we only ever hold the public key. */
const b64uToBuf = (s) => { s = s.replace(/-/g, '+').replace(/_/g, '/'); s += '='.repeat((4 - (s.length % 4)) % 4); const bin = atob(s); return Uint8Array.from(bin, (c) => c.charCodeAt(0)).buffer; };
const bufToB64u = (b) => btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const xsrf = () => { const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; };
const json = async (url, opt = {}) => {
    const r = await fetch(url, { credentials: 'same-origin', ...opt, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', ...(opt.headers || {}) } });
    let body = null; try { body = await r.json(); } catch (e) {}
    return { ok: r.ok, status: r.status, body };
};
const decodeCreate = (o) => ({ ...o, challenge: b64uToBuf(o.challenge), user: { ...o.user, id: b64uToBuf(o.user.id) }, excludeCredentials: (o.excludeCredentials || []).map((c) => ({ ...c, id: b64uToBuf(c.id) })) });
const decodeGet = (o) => ({ ...o, challenge: b64uToBuf(o.challenge), allowCredentials: (o.allowCredentials || []).map((c) => ({ ...c, id: b64uToBuf(c.id) })) });
const base = (c) => ({ id: c.id, rawId: bufToB64u(c.rawId), type: c.type, authenticatorAttachment: c.authenticatorAttachment || null, clientExtensionResults: c.getClientExtensionResults ? c.getClientExtensionResults() : {} });
const fail = (e) => (e && (e.name === 'NotAllowedError' || e.name === 'AbortError') ? 'cancelled' : (e && e.message) || 'failed');

window.nxPasskey = {
    supported: () => !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext),
    /** True when this device has a built-in biometric / screen-lock authenticator (shows the button only where it will work). */
    async platformReady() { try { return this.supported() && (await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()); } catch (e) { return false; } },
    async login(remember = false) {
        try {
            const o = await json('/passkeys/login/options'); if (!o.ok) return { ok: false, error: 'options' };
            const c = await navigator.credentials.get({ publicKey: decodeGet(o.body.options.publicKey || o.body.options) });
            const r = c.response;
            const res = await json('/passkeys/login', { method: 'POST', body: JSON.stringify({ remember, credential: { ...base(c), response: { clientDataJSON: bufToB64u(r.clientDataJSON), authenticatorData: bufToB64u(r.authenticatorData), signature: bufToB64u(r.signature), userHandle: r.userHandle ? bufToB64u(r.userHandle) : null } } }) });
            return res.ok ? { ok: true, redirect: res.body?.redirect || '/' } : { ok: false, error: res.body?.message || 'rejected' };
        } catch (e) { return { ok: false, error: fail(e) }; }
    },
    async register(name) {
        try {
            const o = await json('/user/passkeys/options');
            if (o.status === 423 || o.status === 401 || o.status === 403) return { ok: false, error: 'confirm' };
            if (!o.ok) return { ok: false, error: 'options' };
            const c = await navigator.credentials.create({ publicKey: decodeCreate(o.body.options.publicKey || o.body.options) });
            const r = c.response;
            const res = await json('/user/passkeys', { method: 'POST', body: JSON.stringify({ name, credential: { ...base(c), response: { clientDataJSON: bufToB64u(r.clientDataJSON), attestationObject: bufToB64u(r.attestationObject), transports: r.getTransports ? r.getTransports() : [] } } }) });
            return res.ok ? { ok: true } : { ok: false, error: res.status === 423 ? 'confirm' : (res.body?.message || 'rejected') };
        } catch (e) { return { ok: false, error: fail(e) }; }
    },
    post: (url, data) => json(url, { method: 'POST', body: JSON.stringify(data || {}) }),
    /** Re-confirm with the device lock for the app lock screen, then open the lock on the server. */
    async confirm() {
        try {
            const o = await json('/passkeys/confirm/options'); if (!o.ok) return { ok: false, error: 'Fingerprint is not set up on this account.' };
            const c = await navigator.credentials.get({ publicKey: decodeGet(o.body.options.publicKey || o.body.options) });
            const r = c.response;
            const res = await json('/passkeys/confirm', { method: 'POST', body: JSON.stringify({ credential: { ...base(c), response: { clientDataJSON: bufToB64u(r.clientDataJSON), authenticatorData: bufToB64u(r.authenticatorData), signature: bufToB64u(r.signature), userHandle: r.userHandle ? bufToB64u(r.userHandle) : null } } }) });
            if (!res.ok) return { ok: false, error: 'rejected' };
            const u = await json('/app-lock/passkey', { method: 'POST', body: '{}' });
            return u.ok ? { ok: true, redirect: u.body?.redirect || '/' } : { ok: false, error: u.body?.message || 'rejected' };
        } catch (e) { return { ok: false, error: fail(e) }; }
    },
    async remove(id) { const r = await json('/user/passkeys/' + id, { method: 'DELETE' }); return r.status === 423 ? { ok: false, error: 'confirm' } : { ok: r.ok }; },
};

/** Lock the app whenever it is left (the "Whenever I leave the app" choice); the server enforces the lock. */
window.nxAppLock = (mode) => {
    if (mode !== 0 || window.__nxAppLockOn) return; window.__nxAppLockOn = true;
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') fetch('/app-lock/lock', { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json' } }); });
};
