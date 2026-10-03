{{-- Account & privacy on the skin system (S3 Batch 2). Every action, confirm and Livewire target is unchanged. --}}
<x-nx.page class="ns-narrow">
    <h1 class="ns-h1" style="margin-top:6px">{{ __('account.title') }}</h1>
    <p class="ns-sub">{{ __('account.subtitle') }}</p>

    {{-- Promo banner zone (Module 31, "account" placement) and the admin-assignable "Download the app" slot (App Export §1) stay as the
         admin configured them. --}}
    <x-banner-zone placement="account" style="margin-top:14px" />
    <x-app-download-cta placement="account_settings" variant="banner" style="margin-top:14px" />

    {{-- Your skin, accent and mode — a per-account setting that only changes YOUR dashboard. --}}
    <x-nx.note variant="link" icon="grid" tag="a" :href="route('account.appearance')" wire:navigate style="margin-top:14px">
        <b>{{ __('account.skin_link_title') }}</b>{{ __('account.skin_link') }}
    </x-nx.note>

    {{-- nx:allow:start Day/night scene (Module 32 pick, rebuilt on brand): fixed brand artwork that drives the REAL theme; its sky colours are intentionally outside the skin tokens. --}}
    {{-- Appearance (Module 32 pick — witer33 sun/moon scene, rebuilt on brand):
         a day/night scene that drives the REAL theme (localStorage + .dark). --}}
    <section class="nx-theme-scene" style="margin-top:14px" x-data="{ dark: document.documentElement.classList.contains('dark') }"
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
                    {{ __('account.appearance') }}
                </h2>
                <p class="mt-1 text-xs" :class="dark ? 'text-slate-300' : 'text-slate-600'">
                    <span x-show="!dark">{{ __('account.appearance_bright') }}</span>
                    <span x-show="dark" x-cloak>{{ __('account.appearance_dark') }}</span>
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
    {{-- nx:allow:end --}}

    {{-- Bottom navigation style (owner request) — Floating (rounded pill lifted off the edge) or Docked (flush). Client-side preference, applied live. --}}
    <div class="ns-note ns-ring lg:hidden" x-data="{ floating: localStorage.getItem('nx_nav_floating') !== '0' }" style="align-items:center">
        <x-nx.icon name="grid" />
        <span style="flex:1;min-width:0">
            <b style="display:block;color:rgb(var(--nx-text));font-weight:600">{{ __('account.nav_style') }}</b>
            <span x-show="floating">{{ __('account.nav_style_floating') }}</span>
            <span x-show="!floating" x-cloak>{{ __('account.nav_style_docked') }}</span>
            <small class="ns-small" style="display:block;margin-top:4px">{{ __('account.nav_style_tip') }}</small>
        </span>
        <button type="button" role="switch" :aria-checked="floating.toString()" aria-label="Toggle bottom menu style"
                @click="floating = !floating; localStorage.setItem('nx_nav_floating', floating ? '1' : '0'); $dispatch('nx-nav-style', { floating })"
                class="ns-toggle" :class="floating ? 'is-on' : ''"><i></i></button>
    </div>

    @if ($status)
        <x-nx.note icon="check" role="status">{{ $status }}</x-nx.note>
    @endif

    @if (session('paused'))
        <x-nx.note icon="info" variant="warn" role="status">{{ session('paused') }}</x-nx.note>
    @endif

    {{-- Account status: pause / resume --}}
    <x-nx.step icon="info" style="margin-top:26px" :title="__('account.status_heading')" />
    @if ($user->isDeactivated())
        <p class="ns-sub" style="font-size:14px">{!! __('account.status_paused', ['paused' => '<b style="color:rgb(var(--nx-warn))">'.__('account.paused').'</b>']) !!}</p>
        <x-nx.cta icon="check" loading="reactivate" wire:click="reactivate" style="margin-top:14px">
            <span wire:loading.remove wire:target="reactivate">{{ __('account.reactivate') }}</span>
            <span wire:loading wire:target="reactivate" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> {{ __('account.reactivating') }}</span>
        </x-nx.cta>
    @else
        <p class="ns-sub" style="font-size:14px">{!! __('account.status_active', ['active' => '<b style="color:rgb(var(--nx-ok))">'.__('account.active').'</b>']) !!}</p>
        <button type="button" class="ns-btn" style="margin-top:14px" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate" wire:confirm="{{ __('account.pause_confirm') }}">
            <span wire:loading.remove wire:target="deactivate" style="display:inline-flex;align-items:center;gap:8px"><x-nx.icon name="info" /> {{ __('account.pause_account') }}</span>
            <span wire:loading.inline-flex wire:target="deactivate" style="gap:8px;align-items:center"><x-ui.spinner class="h-4 w-4" /> {{ __('account.pausing') }}</span>
        </button>
    @endif

    {{-- Data export --}}
    <x-nx.step icon="download" style="margin-top:30px" :title="__('account.export_heading')" />
    <p class="ns-sub" style="font-size:14px">{{ __('account.export_body') }}</p>
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:14px">
        <button type="button" class="ns-btn" style="margin:0" wire:click="requestExport" wire:loading.attr="disabled" wire:target="requestExport">
            <span wire:loading.remove wire:target="requestExport" style="display:inline-flex;align-items:center;gap:8px"><x-nx.icon name="download" /> {{ __('account.prepare_export') }}</span>
            <span wire:loading.inline-flex wire:target="requestExport" style="gap:8px;align-items:center"><x-ui.spinner class="h-4 w-4" /> {{ __('account.requesting') }}</span>
        </button>
        @if ($user->data_export_ready_at)
            <a href="{{ route('account.export.download') }}" class="ns-btn ns-btn--solid" style="margin:0"><x-nx.icon name="download" /> {{ __('account.download_ready', ['when' => $user->data_export_ready_at->diffForHumans()]) }}</a>
        @endif
    </div>

    {{-- Deletion --}}
    <div class="ns-note ns-ring ns-danger" style="display:block;margin-top:30px">
        <b style="display:flex;align-items:center;gap:8px;color:rgb(var(--nx-bad));font-weight:600;font-size:16px"><x-nx.icon name="info" /> {{ __('account.delete_heading') }}</b>
        @if ($user->hasPendingDeletion())
            <p style="margin:8px 0 0">{!! __('account.deletion_pending', ['status' => '<b style="color:rgb(var(--nx-bad))">'.__('account.awaiting_review').'</b>']) !!}</p>
            <button type="button" class="ns-btn" style="margin-top:14px" wire:click="cancelDeletion" wire:loading.attr="disabled" wire:target="cancelDeletion"><x-nx.icon name="x" /> {{ __('account.cancel_deletion') }}</button>
        @else
            <p style="margin:8px 0 0">{{ __('account.delete_body') }}</p>
            <button type="button" class="ns-btn" style="margin-top:14px;color:rgb(var(--nx-bad))" wire:click="requestDeletion" wire:loading.attr="disabled" wire:target="requestDeletion" wire:confirm="{{ __('account.deletion_confirm') }}"><x-nx.icon name="x" /> {{ __('account.request_deletion') }}</button>
        @endif
    </div>
</x-nx.page>
