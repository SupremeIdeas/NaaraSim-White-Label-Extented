{{-- Swappable LOGIN section — "aries-contrast" style family (Theme Batch 1,
     2026-09-07). Persona: "High-contrast black, white and gold, sharp
     corners — live-odds, luxury-travel energy." Structurally different from
     the default two-column media-panel layout: a single centred column, a
     sharp-cornered (no rounding) gold-bordered card, and a gold divider rule
     under the heading — the whole page ignores $panel/$useMedia/$useWebgl
     entirely (no media panel exists in this persona). Inherits the same
     variables as login/default.blade.php: $heading, $subheading, $slot. --}}
<div class="relative flex min-h-screen flex-col overflow-hidden bg-white dark:bg-black">
    <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
    <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm border border-accent bg-white p-8 dark:bg-black">
            <div class="mb-8 flex items-center justify-between">
                <a href="{{ route('home') }}"><x-brand-logo variant="family" size="md" fallback-icon="signal" /></a>
                <x-theme-toggle />
            </div>

            <div class="mb-6 border-b border-accent pb-4">
                @if ($heading)
                    <h1 class="font-display text-2xl font-bold uppercase tracking-[0.15em] text-slate-900 dark:text-white">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
                @endif
            </div>

            {{ $slot }}

            <p class="mt-8 text-center text-[10px] font-semibold uppercase tracking-[0.3em] text-slate-400 dark:text-slate-600">Supreme Ideas Agency</p>
        </div>
    </div>
    <x-site-footer variant="slim" />
</div>
