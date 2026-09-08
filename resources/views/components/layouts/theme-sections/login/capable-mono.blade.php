{{-- Swappable LOGIN section — "capable-mono" style family (Theme visual
     rebuild, brand-new persona, 2026-09-07). Persona: "Near-black monochrome
     with a single neon-lime accent — restrained by day, electric by night."
     Structurally distinct from every other login family so far: not
     default's 50/50 media split, not aries-contrast's plain bordered single
     column, not noir-reserve's asymmetric magazine-margin three-zone — this
     is a single centred TERMINAL-WINDOW card: a title-bar strip (three flat
     monochrome dots + a monospace path label, echoing a code editor/console
     chrome) sitting above the real form, with a single blinking lime cursor
     as the page's one deliberate accent. No media panel exists in this
     persona (ignores $panel/$useMedia/$useWebgl entirely, same convention
     as aries-contrast). Inherits the same variables as login/default.blade.php:
     $heading, $subheading, $slot. The decorative dot-grid backdrop is this
     theme's assigned login_bg effect. --}}
<div class="relative flex min-h-screen flex-col bg-[#F8F9FA] dark:bg-navy">
    <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />

    <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-primary">
            {{-- Terminal title-bar strip. --}}
            <div class="flex items-center justify-between border-b border-slate-200 bg-[#F1F2F3] px-4 py-2.5 dark:border-white/10 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5" aria-hidden="true">
                        <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                        <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                        <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                    </span>
                    <span class="font-mono text-[11px] text-slate-400 dark:text-white/35">~/naarasim/session</span>
                </div>
                <x-theme-toggle />
            </div>

            <div class="p-8">
                <a href="{{ route('home') }}" wire:navigate class="mb-8 inline-flex">
                    <x-brand-logo variant="family" size="md" fallback-icon="signal" />
                </a>

                <div class="mb-6">
                    @if ($heading)
                        <h1 class="flex items-center font-display text-2xl font-bold text-slate-900 dark:text-white">
                            {{ $heading }}
                            {{-- The single lime element on this screen: a blinking
                                 terminal cursor, not a decorative accent. --}}
                            <span class="ml-1 inline-block h-5 w-[3px] shrink-0 animate-pulse bg-accent" aria-hidden="true"></span>
                        </h1>
                    @endif
                    @if ($subheading)
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
                    @endif
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>

    <x-site-footer variant="slim" />
</div>
