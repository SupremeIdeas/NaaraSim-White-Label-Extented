<div class="mx-auto max-w-2xl space-y-6" x-data="{ codes: false }">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Security</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Protect your account — password, two-factor, email and sessions.</p>
    </div>

    @if ($flash)
        <div class="flex items-center gap-2 rounded-lg p-3 text-sm
            {{ $flashType === 'success'
                ? 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300'
                : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' }}">
            <x-icon name="{{ $flashType === 'success' ? 'badge-check' : 'info' }}" class="h-4 w-4" /> {{ $flash }}
        </div>
    @endif

    {{-- Password ------------------------------------------------------- --}}
    <section class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">Change password</h2>
        <form wire:submit="updatePassword" class="space-y-3">
            <x-ui.password wire:model="current_password" placeholder="Current password" autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100" />
            @error('current_password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.password wire:model="password" placeholder="New password" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100" />
                <x-ui.password wire:model="password_confirmation" placeholder="Confirm new password" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100" />
            </div>
            @error('password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="shield-check" class="h-4 w-4" /> Update password
            </button>
        </form>
    </section>

    {{-- Two-factor ------------------------------------------------------ --}}
    <section class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Two-factor authentication</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Add a one-time code from an authenticator app on top of your password.</p>

        @if ($confirmed)
            <div class="flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-400">
                <x-icon name="badge-check" class="h-5 w-5" /> Two-factor is active.
            </div>
            @if (! empty($recoveryCodes))
                <button type="button" @click="codes = !codes" class="mt-3 text-xs font-medium text-primary hover:underline">Show / hide recovery codes</button>
                <div x-show="codes" x-cloak class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-4 font-mono text-xs text-slate-700 dark:bg-[#243352] dark:text-slate-200">
                    @foreach ($recoveryCodes as $rc)<span>{{ $rc }}</span>@endforeach
                </div>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                <button wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">
                    <x-icon name="refresh" class="h-4 w-4" /> New recovery codes
                </button>
                <button wire:click="disable2fa" wire:confirm="Turn off two-factor authentication?" wire:loading.attr="disabled" wire:target="disable2fa"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40">
                    <x-icon name="x" class="h-4 w-4" /> Disable
                </button>
            </div>
        @elseif ($showing2faSetup && $qr)
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="shrink-0 rounded-lg bg-white p-3 shadow-sm">{!! $qr !!}</div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-600 dark:text-slate-300">Scan with an authenticator app, or enter this key:</p>
                    <code class="mt-2 block break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:bg-[#243352] dark:text-slate-200">{{ $secret }}</code>
                    <form wire:submit="confirm2fa" class="mt-3 flex gap-2">
                        <input type="text" wire:model="code" inputmode="numeric" placeholder="123456"
                               class="w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        <button type="submit" wire:loading.attr="disabled" wire:target="confirm2fa"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Confirm</button>
                    </form>
                    @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @else
            <button wire:click="enable2fa" wire:loading.attr="disabled" wire:target="enable2fa"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="shield" class="h-4 w-4" /> Turn on two-factor
            </button>
        @endif
    </section>

    {{-- Email ---------------------------------------------------------- --}}
    <section class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Email address</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            @if ($user->email_verified_at)
                <span class="text-green-600 dark:text-green-400">Verified.</span>
            @else
                <span class="text-amber-600 dark:text-amber-400">Not verified.</span>
            @endif
            Changing it requires confirming the new address.
        </p>
        <form wire:submit="updateEmail" class="space-y-3">
            <input type="email" wire:model="new_email" placeholder="you@example.com"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
            @error('new_email') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <x-ui.password wire:model="email_password" placeholder="Your current password" autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100" />
            @error('email_password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button type="submit" wire:loading.attr="disabled" wire:target="updateEmail"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="mail" class="h-4 w-4" /> Update email
            </button>
        </form>
    </section>

    {{-- Connected accounts --------------------------------------------- --}}
    <section class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">Connected accounts</h2>
        <div class="flex items-center justify-between gap-3">
            <span class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.76h3.56c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.56-2.76c-.98.66-2.23 1.05-3.72 1.05-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>
                Google
            </span>
            @if ($googleLinked)
                <button wire:click="unlinkGoogle" wire:confirm="Unlink your Google account? You can still sign in with your email and password."
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300 dark:hover:bg-[#243352]">Unlink</button>
            @elseif (\App\Support\SocialLogin::googleEnabled())
                <a href="{{ route('social.redirect', 'google') }}"
                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-primary hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:hover:bg-[#243352]">Link</a>
            @else
                <span class="text-xs text-slate-400">Not available</span>
            @endif
        </div>
    </section>

    {{-- Sessions ------------------------------------------------------- --}}
    @if ($sessions->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Active sessions</h2>
                <button wire:click="signOutOtherSessions" wire:loading.attr="disabled" wire:target="signOutOtherSessions"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-60 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300 dark:hover:bg-[#243352]">
                    Sign out other sessions
                </button>
            </div>
            <ul class="space-y-2">
                @foreach ($sessions as $s)
                    <li class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-[#243352]">
                        <span class="min-w-0 truncate text-slate-600 dark:text-slate-300">{{ $s['ip'] ?: 'unknown IP' }} — {{ \Illuminate\Support\Str::limit($s['agent'], 48) }}</span>
                        <span class="shrink-0 {{ $s['current'] ? 'font-semibold text-green-600 dark:text-green-400' : 'text-slate-400' }}">
                            {{ $s['current'] ? 'This device' : $s['last'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
