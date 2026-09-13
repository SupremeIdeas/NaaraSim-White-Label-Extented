{{-- Customer chrome (blueprint Sections 4 & 12): a premium, responsive app
     shell — an Apple-inspired side menu on desktop and a bottom navigation
     with a centre "More" button on mobile. Dark-mode variants throughout. --}}
@props(['preloaderType' => 'dashboard'])
@php
    $u = auth()->user();

    // Core end-user destinations (bottom bar on mobile, top of the sidebar).
    // Bar layout: eSIMs · Numbers · [More] · Gifts · Wallet. Home lives on the
    // clickable logo + the top of the More sheet, freeing a slot for Naara Gift
    // right beside Wallet.
    $primary = [
        ['route' => 'catalogue', 'label' => 'eSIMs', 'icon' => 'globe'],
        ['route' => 'numbers', 'label' => 'Numbers', 'icon' => 'hash'],
    ];
    // Naara Gift lives in the bar whenever the feature isn't switched off. It
    // shows a "Soon" badge until the API keys flip it live (the store page itself
    // renders a Coming-Soon state until then) — no manual editing needed.
    // Batch 8: on a white-label fork whose license locks gift cards, don't
    // surface the nav link (the page itself 404s). Inert on the master.
    if (\App\Support\FeatureFlags::adminEnabled('naara_gift') && ! \App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_GIFT_CARDS)) {
        $primary[] = ['route' => 'gift-cards', 'label' => 'Gifts', 'icon' => 'gift',
            'badge' => \App\Support\FeatureFlags::configured('naara_gift') ? null : 'Soon'];
    }
    $primary[] = ['route' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet'];

    $more = [
        // The most-reached number tools live up top of More (owner request).
        ['route' => 'numbers.contacts', 'label' => 'Contacts', 'icon' => 'users'],
        ['route' => 'support', 'label' => 'Help & Support', 'icon' => 'message-circle'],
        ['route' => 'rewards', 'label' => 'Rewards', 'icon' => 'gift'],
        ['route' => 'journey', 'label' => 'My Journey', 'icon' => 'globe'],
        ['route' => 'receipts', 'label' => 'Receipts', 'icon' => 'file-text'],
        ['route' => 'brand.get-listed', 'label' => 'List my brand', 'icon' => 'star'],
        ['route' => 'referrals', 'label' => 'Referrals', 'icon' => 'users'],
        ['route' => 'data-estimator', 'label' => 'Estimator', 'icon' => 'signal'],
        ['route' => 'profile', 'label' => 'Profile', 'icon' => 'id-card'],
        ['route' => 'account', 'label' => 'Account', 'icon' => 'settings'],
        ['route' => 'security', 'label' => 'Security', 'icon' => 'shield'],
    ];

    // Batch 8: drop the "List my brand" entry on a fork whose license locks
    // Brand Hunt (its page 404s). Inert on the master.
    if (\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT)) {
        $more = array_values(array_filter($more, fn ($item) => ($item['route'] ?? null) !== 'brand.get-listed'));
    }

    // The in-browser dialer (Internet calls) only when voice is live — its page
    // 404s until Twilio is active, so we don't surface a dead link.
    if (\App\Support\ProviderStatus::isActive('twilio')) {
        array_splice($more, 1, 0, [['route' => 'numbers.dialer', 'label' => 'Internet calls', 'icon' => 'phone']]);
    }

    // Developer portal — only surfaced when the operator has enabled the API.
    if (\App\Models\Setting::getValue('developer_api.enabled', false)) {
        array_splice($more, 4, 0, [['route' => 'developer', 'label' => 'Developer API', 'icon' => 'key']]);
    }

    // Active merchants get a link to their reseller storefront (ROADMAP §3.5).
    if ($u && $u->merchantAccount && $u->merchantAccount->isActive()) {
        array_unshift($more, ['route' => 'merchant.dashboard', 'label' => 'My Storefront', 'icon' => 'package']);
        // V2 merchants also get client management + invoicing.
        if ($u->merchantAccount->isV2()) {
            array_unshift($more, ['route' => 'merchant.invoices', 'label' => 'Invoices', 'icon' => 'file-text']);
            array_unshift($more, ['route' => 'merchant.clients', 'label' => 'Clients', 'icon' => 'users']);
        }
    }

    // Partners get a link to their profit-share earnings.
    if ($u && $u->partnerAccount) {
        array_unshift($more, ['route' => 'partner.earnings', 'label' => 'Partner earnings', 'icon' => 'wallet']);
    }

    // Non-merchants get an easy, always-visible route to the merchant program.
    if ($u && ($u->merchantAccount === null || $u->merchantAccount->status === \App\Models\Merchant::REJECTED) && \App\Support\MerchantSettings::enabled()) {
        $more[] = ['route' => 'merchant.apply', 'label' => 'Become a Merchant', 'icon' => 'package'];
    }

    // Staff/admins use the same end-user app and can jump to their panel.
    if ($u && $u->hasAnyRole(['super_admin', 'admin', 'staff'])) {
        $more[] = ['route' => 'admin.dashboard', 'label' => 'Admin', 'icon' => 'id-card'];
    }

    // Admin-assignable "Download the app" side-menu slot (App Export §1).
    if (\App\Support\AppExport::placementActive('customer_menu')) {
        $more[] = ['route' => 'download', 'label' => \App\Support\AppExport::placementLabel('customer_menu'), 'icon' => 'download'];
    }

    // In-app guide + agreement (auto-selects the user's audience).
    $more[] = ['route' => 'guide', 'label' => 'Guide & policy', 'icon' => 'help-circle'];

    // Home moved off the bottom bar → the clickable logo goes home, and it sits
    // at the very top of the More sheet so it's never lost.
    array_unshift($more, ['route' => 'dashboard', 'label' => 'Home', 'icon' => 'signal']);
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
         NaaraSim Wizard launcher so the two floating actions never overlap.
         Hidden while the Convai widget is active, to avoid two support launchers. --}}
    @if (\App\Support\Niche\SupportLinks::hasWhatsapp() && ! \App\Support\ConvaiWidget::shownOn('customer'))
        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener"
           aria-label="Chat with support on WhatsApp"
           class="fixed bottom-40 right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition hover:bg-primary-dark lg:bottom-24">
            <x-icon name="message-circle" class="h-6 w-6" />
        </a>
    @endif

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
