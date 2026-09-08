{{-- Swappable LOGIN section — "paperwhite" style family (Theme Batch 1,
     2026-09-07). Persona: "Ultra-light, high-whitespace, ink-black type on
     paper — minimal, editorial, zero noise." The biggest structural
     departure of the batch: no media panel at all, a single centred column
     with generous margins and a thin rule under the heading — a magazine
     mast-head feel rather than a travel-brand visual. $panel/$useMedia/
     $useWebgl are unused here by design. Inherits $heading, $subheading,
     $slot from login/default.blade.php's contract. --}}
<div class="relative flex min-h-screen flex-col overflow-hidden bg-[#F8F9FA] dark:bg-navy">
    <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
    <div class="relative z-10 flex flex-1 items-center justify-center px-6 py-16">
        <div class="w-full max-w-md">
            <div class="mb-10 flex items-center justify-between">
                <a href="{{ route('home') }}"><x-brand-logo variant="family" size="md" fallback-icon="signal" /></a>
                <x-theme-toggle />
            </div>

            <div class="mb-8 border-b border-slate-200 pb-6 dark:border-white/10">
                @if ($heading)
                    <h1 class="font-display text-3xl font-bold text-slate-900 dark:text-white">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
                @endif
            </div>

            {{ $slot }}
        </div>
    </div>

    <x-site-footer variant="slim" />
</div>
