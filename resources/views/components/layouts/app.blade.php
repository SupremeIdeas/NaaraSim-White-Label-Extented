@props(['pageType' => 'default', 'skin' => false])
@php
    // Per-account appearance (skins x accent x mode x dials). ONLY the signed-in dashboard (customer + admin layouts set
    // $bodyClass) carries it; marketing, auth, emails and PDFs always render the platform look. Attributes are rendered
    // server-side on every response so a wire:navigate morph of <html> never drops them (Prompt 20 §31).
    $nx = (isset($bodyClass) || ($skin && auth()->check())) ? \App\Support\Appearance\AppearanceResolver::for(auth()->user()) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" @if ($nx) {!! \App\Support\Appearance\AppearanceResolver::htmlAttributes($nx) !!} @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'NaaraSim') }}</title>
    <meta property="og:title" content="{{ $title ?? config('app.name', 'NaaraSim') }}">
    @if (! empty($description ?? null))
        <meta name="description" content="{{ $description }}">
        <meta property="og:description" content="{{ $description }}">
    @endif
    {{-- Every shared link gets a real preview image — not just the handful of
         pages that pass their own $ogImage — so a chat app never falls back to
         the bare favicon. Falls back to the admin-set site-wide default
         (App\Support\LinkPreviewSettings), independent of whether a
         description happens to be set. --}}
    <meta property="og:image" content="{{ $ogImage ?? \App\Support\LinkPreviewSettings::resolve('default') }}">

    {{-- Favicon / app icon (Module 26): admin-uploaded if set, else the default. --}}
    @php($favicon = \App\Support\BrandSettings::favicon())
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
    @endif

    {{-- Installable-app (PWA) hooks (App Export §1). The manifest is dynamic
         (admin-editable name/icon/colours). theme-color paints the mobile
         browser chrome + native WebView status bar. --}}
    <link rel="manifest" href="{{ route('manifest') }}">
    {{-- Browser chrome / native status-bar colour, most-specific wins:
         (1) the header editor's own colour override when an admin has set one
         (a themed header then reads as one continuous surface to the page
         edge), else (2) the active Theme Preset's primary colour so the
         chrome repaints with whichever theme is active, else (3) the
         admin-configured App Export PWA colour on the default theme. --}}
    <meta name="theme-color" content="{{ \App\Support\ThemePreset::headerColorHex() ?? \App\Support\ThemePreset::browserThemeColor() ?? \App\Support\AppExport::get('theme_color', '#0A6E6E') }}">

    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Support\AppExport::get('short_name', 'NaaraSim') }}">

    {{-- Preload the body font (compressed WOFF2) only. The display font loads via
         @font-face with font-display:swap — never preload the uncompressed TTF
         (it's a heavy download that blocks the critical path for no benefit). --}}
    <link rel="preload" href="/fonts/didact-gothic.woff2" as="font" type="font/woff2" crossorigin>

    {{-- Admin font system (Branding page): loads a Google Font's stylesheet only
         when one is actually selected for the title or body font. CSP is widened
         for exactly these two Google origins by SecurityHeaders::policyWithGoogleFonts()
         when this is active. --}}
    @php($googleFontsHref = \App\Support\BrandSettings::googleFontsHref())
    @if ($googleFontsHref)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $googleFontsHref }}">
    @endif

    {{-- Pre-paint theme script: sets the `dark` class BEFORE first paint so
         there is no flash of the wrong theme (blueprint Section 4.2 / 24.3).
         The user's choice lives in localStorage and MUST outlive navigation:
         Livewire `wire:navigate` morphs a fresh, server-rendered <html> (which
         has no `dark` class) into the page, so we re-apply the stored theme on
         every `livewire:navigated` too — otherwise dark mode would silently drop
         back to light the moment you open another page. --}}
    <script>
        (function () {
            // Reveal-on-scroll styles only apply when JS runs (no-JS visitors
            // and crawlers see everything immediately — Module 27).
            document.documentElement.classList.add('js-enabled');
            window.applyStoredTheme = function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var wantsDark = stored
                        ? stored === 'dark'
                        : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    // A signed-in member's saved mode (their account's appearance) wins over this browser's memory.
                    var saved = document.documentElement.getAttribute('data-nx-mode');
                    if (saved === 'dark' || saved === 'light') {
                        wantsDark = saved === 'dark';
                    } else if (saved === 'system') {
                        wantsDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    }
                    document.documentElement.classList.toggle('dark', wantsDark);
                } catch (e) { /* localStorage unavailable — default to light */ }
            };
            window.applyStoredTheme();
            // Re-assert the choice after each SPA navigation (see comment above).
            document.addEventListener('livewire:navigated', window.applyStoredTheme);
            // The header day/night toggles dispatch `theme-changed`; for a signed-in member, remember it on their ACCOUNT
            // so it follows them across browsers (best effort, never blocks the toggle).
            window.addEventListener('theme-changed', function (e) {
                try {
                    var h = document.documentElement;
                    if (h.getAttribute('data-nx-user') !== '1' || !e.detail) { return; }
                    var mode = e.detail.dark ? 'dark' : 'light';
                    h.setAttribute('data-nx-mode', mode);
                    var t = document.querySelector('meta[name=csrf-token]');
                    fetch(@json(route('account.appearance.mode')), {
                        method: 'POST', credentials: 'same-origin', keepalive: true,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t ? t.content : '' },
                        body: JSON.stringify({ mode: mode })
                    });
                } catch (err) { /* offline or blocked: the local choice still applies */ }
            });
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- Runtime brand palette override (Module 26): recolours everything with no
         rebuild. Emitted only when the admin has customised a colour/radius. --}}
    @php($brandCss = \App\Support\BrandSettings::themeCss())
    @if ($brandCss)<style id="brand-vars">{!! $brandCss !!}</style>@endif
    {{-- Dashboard background / Platform Theme override (empty on the default
         treatment and outside the dashboard, where $bodyClass isn't set). --}}
    @isset($bodyClass)
        @php($platformCss = \App\Support\PlatformTheme::styleCss())
        @if ($platformCss)<style id="platform-theme-vars">{!! $platformCss !!}</style>@endif
    @endisset
    {{-- Active theme preset (Theme Batch 1): whitelisted :root overrides only.
         Empty string for naara-official (the built-in look already ships in
         app.css), so the default renders with zero injected CSS. Applies to
         BOTH the dashboard and the marketing site (which extends this layout). --}}
    @php($themeCss = \App\Support\ThemePreset::styleCss())
    @if ($themeCss)<style id="theme-preset-vars">{!! $themeCss !!}</style>@endif
    {{-- Header editor (owner request, 2026-09-07): a SEPARATE emitter from
         styleCss() above, since that one is deliberately empty for
         naara-official — the header editor must still work on it. Scoped
         to exclude /adminmaster's own chrome inside the CSS itself (see
         ThemePreset::headerStyleCss()), since app-shell.blade.php's header
         is shared by both the customer and admin layouts. --}}
    <style id="theme-header-vars">{!! \App\Support\ThemePreset::headerStyleCss() !!}</style>
    {{-- Admin-tunable card glassmorphism intensity (owner request): controls
         how strong .nx-glass-tile looks WHEREVER it's already applied. Empty
         on an untouched install — the shipped CSS var() fallbacks already
         match the default. --}}
    @php($glassCss = \App\Support\GlassmorphismSettings::styleCss())
    @if ($glassCss)<style id="glass-vars">{!! $glassCss !!}</style>@endif
    {{-- Site-wide font override (admin Branding page): swaps --font-display /
         --font-sans for a Google Font or an uploaded custom font. Emitted LAST
         so it wins over any theme-preset font variable — this is the single
         authoritative source for the platform's rendered font, regardless of
         which of the 40 themes is active. Empty on an unconfigured install, so
         the shipped Naara fonts (Supreme Display / Didact Gothic) are untouched. --}}
    @php($fontCss = \App\Support\BrandSettings::fontCss())
    @if ($fontCss)<style id="brand-font-vars">{!! $fontCss !!}</style>@endif
    {{-- Member's custom accent colour (both mode variants; the `.dark` class picks one). Empty unless they chose Custom. --}}
    @if ($nx && ! empty($nx['tod']))
        {{-- Golden Hour (§45): correct the server's default time of day from the device clock before first paint. --}}
        <script>(function(){var h=new Date().getHours();document.documentElement.setAttribute('data-nx-tod',h>=5&&h<8?'dawn':h>=8&&h<17?'day':h>=17&&h<20?'dusk':'night');})();</script>
    @endif
    @if ($nx && ($nxCss = \App\Support\Appearance\AppearanceResolver::customAccentCss($nx)))<style id="nx-accent-vars">{!! $nxCss !!}</style>@endif
    @stack('head')
    @include('partials.tracking')
</head>
{{--
    is-admin-surface (header editor, 2026-09-07): app-shell.blade.php's
    swappable header is @include()'d identically for both the customer
    layout and the admin panel layout — there is no separate admin header
    component. This class is how ThemePreset::headerStyleCss() keeps a
    theme's header colour/radius/blur customization from leaking into
    /adminmaster's own chrome (see the `body:not(.is-admin-surface)` scope
    in that method).
--}}
<body class="min-h-screen text-[#0F172A] antialiased dark:text-slate-100 {{ \App\Support\ThemePreset::bodyClass() }} {{ $bodyClass ?? 'bg-[#F8F9FA] dark:bg-navy' }} {{ request()->is(config('admin.path'), config('admin.path').'/*') ? 'is-admin-surface' : '' }}">
    @include('partials.icon-sprite')
    @include('partials.service-icon-sprite')
    @include('partials.nx-icon-sprite')
    <x-brand-preloader :page-type="$pageType" />
    <x-splash />
    <x-ui.toast-stack />
    {{ $slot ?? '' }}
    @yield('content')
    @livewireScripts
</body>
</html>
