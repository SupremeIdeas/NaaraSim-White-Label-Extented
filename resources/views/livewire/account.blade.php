<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Account &amp; privacy</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Manage your account status, download your data, or ask us to delete your account.</p>

    {{-- Promo banner zone (Module 31, "account" placement). --}}
    <x-banner-zone placement="account" class="mb-6" />

    {{-- Admin-assignable "Download the app" slot (App Export §1). --}}
    <x-app-download-cta placement="account_settings" variant="banner" class="mb-6" />

    {{-- Appearance (Module 32 pick — witer33 sun/moon scene, rebuilt on brand):
         a day/night scene that drives the REAL theme (localStorage + .dark). --}}
    <section class="nx-theme-scene mb-5" x-data="{ dark: document.documentElement.classList.contains('dark') }"
             :class="dark && 'is-night'">
        <div class="nx-theme-scene__sky" aria-hidden="true">
            <span class="nx-theme-scene__orb"></span>
            <span class="nx-theme-scene__cloud nx-theme-scene__cloud--1"></span>
            <span class="nx-theme-scene__cloud nx-theme-scene__cloud--2"></span>
            <span class="nx-theme-scene__star nx-theme-scene__star--1"></span>
            <span class="nx-theme-scene__star nx-theme-scene__star--2"></span>
            <span class="nx-theme-scene__star nx-theme-scene__star--3"></span>
        </div>
        <div class="relative flex items-center justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold" :class="dark ? 'text-white' : 'text-slate-800'">
                    <x-icon name="sun" class="h-4 w-4" x-show="!dark" />
                    <x-icon name="moon" class="h-4 w-4" x-show="dark" x-cloak />
                    Appearance
                </h2>
                <p class="mt-1 text-xs" :class="dark ? 'text-slate-300' : 'text-slate-600'">
                    <span x-show="!dark">Bright and clear — tap the switch for night mode.</span>
                    <span x-show="dark" x-cloak>Easy on the eyes — tap the switch for daylight.</span>
                </p>
            </div>
            <button type="button" role="switch" :aria-checked="dark.toString()" aria-label="Toggle dark mode"
                    class="nx-theme-scene__switch"
                    @click="dark = !dark;
                            localStorage.setItem('theme', dark ? 'dark' : 'light');
                            document.documentElement.classList.toggle('dark', dark);
                            $dispatch('theme-changed', { dark })">
                <span class="nx-theme-scene__knob">
                    <x-icon name="sun" class="h-3.5 w-3.5 text-accent-dark" x-show="!dark" />
                    <x-icon name="moon" class="h-3.5 w-3.5 text-slate-200" x-show="dark" x-cloak />
                </span>
            </button>
        </div>
    </section>

    {{-- Bottom navigation style (owner request) — Floating (rounded pill lifted
         off the edge) or Docked (flush). Client-side preference, applied live. --}}
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[var(--brand-card-border-dark)] dark:bg-[#1B2A44] lg:hidden"
             x-data="{ floating: localStorage.getItem('nx_nav_floating') !== '0' }">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="grid" class="h-4 w-4" gradient /> Bottom menu style
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    <span x-show="floating">Floating — a rounded bar lifted off the edge.</span>
                    <span x-show="!floating" x-cloak>Docked — flush to the bottom of the screen.</span>
                </p>
            </div>
            <button type="button" role="switch" :aria-checked="floating.toString()" aria-label="Toggle bottom menu style"
                    @click="floating = !floating;
                            localStorage.setItem('nx_nav_floating', floating ? '1' : '0');
                            $dispatch('nx-nav-style', { floating })"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                    :class="floating ? 'bg-primary' : 'bg-slate-300 dark:bg-[#2D4060]'">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                      :class="floating ? 'translate-x-5' : 'translate-x-0.5'"></span>
            </button>
        </div>
        <p class="mt-2 text-[11px] text-slate-400">Tip: you can also tap the little handle at the top of the bottom bar to switch anytime.</p>
    </section>

    @if ($status)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $status }}
        </div>
    @endif

    @if (session('paused'))
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <x-icon name="pause" class="h-4 w-4" /> {{ session('paused') }}
        </div>
    @endif

    {{-- Account status: pause / resume --}}
    <section class="mb-5 rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="pause" class="h-4 w-4 text-primary" /> Account status
        </h2>
        @if ($user->isDeactivated())
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Your account is <span class="font-semibold text-amber-600 dark:text-amber-400">paused</span>. Reactivate to buy eSIMs and numbers again.</p>
            <button type="button" wire:click="reactivate" wire:loading.attr="disabled" wire:target="reactivate"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="reactivate" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Reactivate my account</span>
                <span wire:loading wire:target="reactivate" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Reactivating…</span>
            </button>
        @else
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Your account is <span class="font-semibold text-green-600 dark:text-green-400">active</span>. Pausing hides your account and stops new purchases — nothing is deleted, and you can resume any time.</p>
            <button type="button" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate"
                    wire:confirm="Pause your account? You can reactivate any time by logging back in."
                    class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">
                <span wire:loading.remove wire:target="deactivate" class="inline-flex items-center gap-2"><x-icon name="pause" class="h-4 w-4" /> Pause my account</span>
                <span wire:loading wire:target="deactivate" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Pausing…</span>
            </button>
        @endif
    </section>

    {{-- Data export --}}
    <section class="mb-5 rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="download" class="h-4 w-4 text-primary" /> Download your data
        </h2>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Get a copy of the personal data we hold about you (profile, wallet, orders and numbers). We prepare it in the background.</p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" wire:click="requestExport" wire:loading.attr="disabled" wire:target="requestExport"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">
                <span wire:loading.remove wire:target="requestExport" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4" /> Prepare my export</span>
                <span wire:loading wire:target="requestExport" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Requesting…</span>
            </button>

            @if ($user->data_export_ready_at)
                <a href="{{ route('account.export.download') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    <x-icon name="download" class="h-4 w-4" /> Download ({{ $user->data_export_ready_at->diffForHumans() }})
                </a>
            @endif
        </div>
    </section>

    {{-- Deletion --}}
    <section class="rounded-2xl border border-red-200 nx-glass-tile p-6 dark:border-red-900/50">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-400">
            <x-icon name="trash" class="h-4 w-4" /> Delete my account
        </h2>
        @if ($user->hasPendingDeletion())
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Your deletion request is <span class="font-semibold text-red-600 dark:text-red-400">awaiting review</span>. A super admin must approve it before anything is erased. You can still cancel.</p>
            <button type="button" wire:click="cancelDeletion" wire:loading.attr="disabled" wire:target="cancelDeletion"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="x" class="h-4 w-4" /> Cancel deletion request
            </button>
        @else
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">This asks us to permanently delete your account and personal data. Deletion is reviewed and approved by a super admin — it is not immediate, and it cannot be undone once approved.</p>
            <button type="button" wire:click="requestDeletion" wire:loading.attr="disabled" wire:target="requestDeletion"
                    wire:confirm="Request account deletion? A super admin will review it before anything is erased."
                    class="mt-4 inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40">
                <x-icon name="trash" class="h-4 w-4" /> Request account deletion
            </button>
        @endif
    </section>
</div>
