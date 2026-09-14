{{-- Customer chrome (blueprint Sections 4 & 12): a premium, responsive app
     shell — an Apple-inspired side menu on desktop and a bottom navigation
     with a centre "More" button on mobile. Dark-mode variants throughout. --}}
@props(['preloaderType' => 'dashboard'])
@php
    $u = auth()->user();

    // Core end-user destinations (bottom bar on mobile, top of the sidebar).
    // Eligibility (role/feature-flag/entitlement gating) and the natural
    // primary/More split both live in App\Support\BottomNav; an admin can
    // reorder or replace what's shown via Admin\NavSettings (/adminmaster/bottom-nav)
    // without touching this eligibility logic at all.
    ['primary' => $primary, 'more' => $more] = \App\Support\BottomNav::resolveMain($u);
@endphp

<x-layouts.app :title="$title ?? config('app.name')" :page-type="$preloaderType" :body-class="\App\Support\PlatformTheme::bodyClass()">
    <x-app-shell :primary="$primary" :more="$more" :promo="true" brand-label="NaaraSim" brand-icon="signal" :brand-route="route('dashboard')">
        {{-- In-app notification bell (owner request). Two keyed instances so the
             mobile header and desktop top strip each get their own Livewire id. --}}
        {{-- Header action order (BLUEPRINT-batch1-sections §1): notification bell
             → theme toggle → hamburger. The toggle now lives inside the slot so it
             sits between the bell and the menu trigger. --}}
        <x-slot:headerActions>
            <livewire:notification-center :key="'nc-mobile'" />
            <x-theme-toggle />
            <x-global-sidebar />
        </x-slot:headerActions>
        <x-slot:headerActionsDesktop>
            <livewire:notification-center :key="'nc-desktop'" />
            <x-theme-toggle />
            <x-global-sidebar />
        </x-slot:headerActionsDesktop>

        {{-- Soft email-verification nudge (never blocks; only in 'soft' mode). --}}
        @include('partials.verify-email-banner')

        {{ $slot }}
    </x-app-shell>

    {{-- ElevenLabs Convai voice assistant (task #17) — when an admin enables it,
         it becomes the primary support launcher and the WhatsApp button below is
         hidden (WhatsApp remains the fallback whenever Convai is off). --}}
    <x-convai-widget context="customer" />

    {{-- Live-help: WhatsApp support (blueprint Section 32). Stacked above the
         My Journey launcher and the NaaraSim Wizard launcher below it, so none
         of the three floating actions ever overlap.
         Hidden while the Convai widget is active, to avoid two support launchers. --}}
    @if (\App\Support\Niche\SupportLinks::hasWhatsapp() && ! \App\Support\ConvaiWidget::shownOn('customer'))
        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener"
           aria-label="Chat with support on WhatsApp"
           class="fixed bottom-56 right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition hover:bg-primary-dark lg:bottom-40">
            <x-icon name="message-circle" class="h-6 w-6" />
        </a>
    @endif

    {{-- Floating "My Journey" launcher (owner request) — same size + minimize
         behavior as the Wizard, sat just in front of it (the slot it takes is
         directly above Wizard's own), for fast on-demand access to activity
         progress + rewards. Hidden on /journey itself (pointless there) and on
         support, matching the Wizard's own exclusions. --}}
    @unless (request()->routeIs('support') || request()->routeIs('journey'))
        @livewire('journey-launcher')
    @endunless

    {{-- NaaraSim Wizard — guided, buttons-only purchase widget (roadmap §3/§11).
         Only rendered for verified end-users (this layout is behind auth).
         NOT on the NaaraCare/support-chat route (BUILD-3 §2): the floating widget
         would overlap the Nia conversation — two chat-like UIs on one screen. --}}
    @unless (request()->routeIs('support'))
        @livewire('wizard')
    @endunless

    {{-- Self-hosted web-push opt-in (owner request) — closed-tab notifications. --}}
    @include('partials.push-optin')

    {{-- Login notice pop-up (admin-composed; shown once per its view cap). --}}
    @livewire('alert-popup')

    {{-- Merchant co-branding (ROADMAP §Layer 3.3): a subtle footer badge for
         customers who joined through a reseller — merchant mark + "Powered by
         NaaraSim". NaaraSim branding is never replaced, only accompanied. --}}
    @php($coBrand = \App\Support\MerchantBranding::forCustomer($u))
    @if ($coBrand)
        <div class="fixed bottom-24 left-4 z-30 hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs shadow-sm lg:flex dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            @if ($coBrand->logo_url)
                <img src="{{ $coBrand->logo_url }}" alt="{{ $coBrand->business_name }}" class="h-5 w-5 rounded object-contain">
            @else
                <span class="flex h-5 w-5 items-center justify-center rounded text-[9px] font-bold uppercase text-white" style="background-color: {{ $coBrand->brand_color ?: '#0A6E6E' }};">{{ \Illuminate\Support\Str::of($coBrand->business_name)->trim()->substr(0, 1) }}</span>
            @endif
            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $coBrand->business_name }}</span>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span class="text-slate-400 dark:text-slate-500">Powered by NaaraSim</span>
        </div>
    @endif
</x-layouts.app>
