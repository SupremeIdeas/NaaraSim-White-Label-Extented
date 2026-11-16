{{-- App lock screen (fingerprint / face / screen lock, or password). Skin tokens only, dark mode via the tokens, SVG icons only. --}}
<x-layouts.app :title="'Welcome back'">
<main class="al" data-app-lock x-data="{ pw: false, busy: false, msg: '', password: '',
        done(r) { if (r.ok) { location.href = r.redirect || '/' } else { this.busy = false; this.msg = r.error === 'cancelled' ? '' : (r.error || 'That did not work. Use your password instead.') } },
        async fp() { this.busy = true; this.msg = ''; this.done(await window.nxPasskey.confirm()) },
        async submit() { this.busy = true; this.msg = ''; const r = await window.nxPasskey.post('/app-lock/password', { password: this.password }); this.done(r.ok ? { ok: true, redirect: r.body?.redirect } : { ok: false, error: r.body?.errors?.password?.[0] || 'That did not work.' }) },
        async secure() { if (!confirm('This signs you out on every device and removes your fingerprint devices. You will need your password to sign in again. Continue?')) return; const r = await window.nxPasskey.post('/app-lock/secure', {}); location.href = r.body?.redirect || '/login' } }"
        x-init="window.nxPasskey && window.nxPasskey.platformReady().then(v => { if (!v) pw = true })">
    <header class="al__top">
        <span class="al__mark" aria-hidden="true"><x-nx.icon name="shield-check" /></span>
        <div class="al__lost"><span>Lost your phone?</span><button type="button" @click="secure()" class="al__link"><x-nx.icon name="lock" /> Lock your account</button></div>
    </header>

    <section class="al__body">
        <h1 class="al__h1">Welcome back,<br><span>{{ \Illuminate\Support\Str::of($user->name)->before(' ')->upper() }}</span></h1>

        <div class="al__card">
            <template x-if="!pw">
                <div class="al__fp">
                    <h2>Fingerprint unlock</h2>
                    <button type="button" class="al__btn" :disabled="busy" @click="fp()" aria-label="Unlock with fingerprint or face">
                        <svg viewBox="0 0 24 24" width="64" height="64" fill="none" aria-hidden="true" style="overflow:visible"><g transform="translate(1.200 1.200)" fill="rgb(var(--nx-gold, 212 160 23))" fill-opacity=".40" stroke="none"><path d="M8.500 12a3.500 3.500 0 0 1 7 0v1c0 3 .5 5 1.500 7H7.500c.7-1.500 1-3.200 1-5.500z"/></g><g stroke="rgb(var(--nx-teal-ink, 10 110 110))" stroke-width="1.600" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.500a8.500 8.500 0 0 0-8.500 8.500v1.500M20.500 12.500V12A8.500 8.500 0 0 0 12 3.500"/><path d="M7.500 20c.7-1.500 1-3.200 1-5.500V12a3.500 3.500 0 0 1 7 0v1c0 3 .5 5 1.500 7M12 12v3c0 2.500-.4 4.300-1.200 6M16 21c-.8-2-1-4-1-6"/></g></svg>
                    </button>
                    <p class="al__hint" x-text="busy ? 'Waiting for your fingerprint...' : 'Touch the sensor or look at your phone'"></p>
                </div>
            </template>
            <template x-if="pw">
                <form class="al__pw" @submit.prevent="submit()">
                    <h2>Enter your password</h2>
                    <label class="sr-only" for="al-pw">Password</label>
                    <input id="al-pw" type="password" x-model="password" autocomplete="current-password" required class="ns-input" autofocus>
                    <button type="submit" :disabled="busy" class="ns-cta ns-cta--pill" style="width:100%;justify-content:center">Unlock</button>
                </form>
            </template>
            <p class="al__err" x-show="msg" x-text="msg" role="alert"></p>
        </div>

        <button type="button" class="al__alt" @click="pw = !pw; msg = ''" x-text="pw ? 'Use fingerprint instead' : 'Use password instead'"></button>
        <form method="POST" action="{{ route('logout') }}" class="al__out">@csrf<button type="submit" class="al__link">Sign out</button></form>
    </section>
</main>
<script>/* While locked nothing else may float over (or leak behind) the lock screen. */(() => { const hide = () => document.querySelectorAll('body *').forEach((e) => { if (e.closest('[data-app-lock]') || e.tagName === 'SCRIPT' || e.tagName === 'STYLE') return; if (getComputedStyle(e).position === 'fixed') e.style.setProperty('display', 'none', 'important'); }); hide(); new MutationObserver(hide).observe(document.body, { childList: true, subtree: true }); })();</script>
<style>
.al { min-height: 100dvh; display: flex; flex-direction: column; padding: 20px 20px 28px; background: rgb(var(--nx-canvas)); color: rgb(var(--nx-text)); }
.al__top { display: flex; justify-content: space-between; align-items: flex-start; }
.al__mark { display: grid; place-items: center; width: 54px; height: 54px; border-radius: 18px; background: linear-gradient(180deg, rgb(var(--nx-cta-a)), rgb(var(--nx-cta-b))); color: #fff; font-size: 26px; }
.al__lost { text-align: end; font-size: 14px; color: rgb(var(--nx-text-2)); display: grid; gap: 2px; }
.al__link { display: inline-flex; gap: 6px; align-items: center; border: 0; background: none; padding: 4px 0; font-weight: 700; color: rgb(var(--nx-teal-ink)); cursor: pointer; }
.al__body { margin: auto 0 0; width: 100%; max-width: 440px; margin-inline: auto; }
.al__h1 { font-size: 30px; line-height: 1.15; font-weight: 800; letter-spacing: -.02em; margin: 0 0 20px; }
.al__card { border-radius: 26px; background: rgb(var(--nx-surface)); box-shadow: inset 0 0 0 1px rgb(var(--nx-line)), 0 18px 40px -26px rgb(0 0 0 / .45); padding: 26px 22px; text-align: center; }
.al__card h2 { font-size: 20px; font-weight: 800; margin: 0 0 22px; }
.al__btn { display: inline-grid; place-items: center; width: 92px; height: 92px; border-radius: 26px; border: 0; cursor: pointer; background: rgb(var(--nx-surface-3)); }
.al__btn:disabled { opacity: .6; } .al__btn:focus-visible { outline: 3px solid rgb(var(--nx-teal)); outline-offset: 3px; }
.al__hint { margin: 16px 0 0; font-size: 13px; color: rgb(var(--nx-text-2)); }
.al__pw { display: grid; gap: 12px; } .al__pw h2 { margin-bottom: 6px; }
.al__err { margin: 12px 0 0; font-size: 13px; font-weight: 600; color: rgb(var(--nx-danger, 190 40 40)); }
.al__alt { display: block; width: 100%; margin-top: 14px; min-height: 52px; border: 0; border-radius: 16px; cursor: pointer; font-weight: 700; font-size: 16px; background: rgb(var(--nx-surface-3)); color: rgb(var(--nx-teal-ink)); }
.al__out { text-align: center; margin-top: 10px; }
</style>
</x-layouts.app>
