@php($c = \App\Support\SiteContent::page('sign-in')['login'] ?? [])
<x-layouts.auth title="Sign in — NaaraSim" :heading="$c['heading'] ?? 'Welcome back'" :subheading="$c['subheading'] ?? ''">
    <form method="POST" action="/login"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2A2E34] dark:bg-[#17191C]">
        @csrf
        @if ($errors->any())
            <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
            </div>
        @endif
        <x-auth.social-buttons />
        <div x-data="{ ok: false, busy: false, msg: '' }" x-init="window.nxPasskey && window.nxPasskey.platformReady().then(v => ok = v)" x-show="ok" x-cloak style="display:none" data-passkey-login>
            <button type="button" :disabled="busy" @click="busy = true; msg = ''; window.nxPasskey.login(document.querySelector('[name=remember]')?.checked).then(r => { if (r.ok) { location.href = r.redirect } else { busy = false; msg = r.error === 'cancelled' ? '' : 'That did not work. Use your password instead.' } })" class="flex w-full items-center justify-center gap-2 rounded-lg border border-primary px-4 py-2.5 font-semibold text-primary hover:bg-primary/5 disabled:opacity-60">
                <x-icon name="shield-check" class="h-5 w-5" /> <span x-text="busy ? 'Waiting for your fingerprint...' : 'Sign in with fingerprint or face'"></span>
            </button>
            <p x-show="msg" x-text="msg" class="mt-2 text-center text-xs text-red-600 dark:text-red-300" role="alert"></p>
            <p class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400">Or use your email and password below.</p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2A2E34] dark:bg-[#373a3f] dark:text-slate-100">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
            <x-ui.password name="password" required
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2A2E34] dark:bg-[#373a3f] dark:text-slate-100" />
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="remember" class="rounded text-primary"> Remember me
            </label>
            <a href="/forgot-password" class="text-sm font-medium text-primary hover:underline">Forgot password?</a>
        </div>
        <x-turnstile />
        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
            <x-icon name="shield-check" class="h-5 w-5" /> {{ $c['cta'] ?? 'Sign in' }}
        </button>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            {{ $c['switch_text'] ?? '' }} <a href="/register" class="font-semibold text-primary hover:underline">{{ $c['switch_link'] ?? '' }}</a>
        </p>
    </form>
    <x-trust.reviews placement="auth.badge" class="mt-4 text-center" />
</x-layouts.auth>
