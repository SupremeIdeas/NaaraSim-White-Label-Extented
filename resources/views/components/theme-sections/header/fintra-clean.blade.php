{{-- Swappable HEADER — "fintra-clean" style family ("Ledger" persona, Theme
     visual rebuild, 2026-09-07). Persona: "Fintech slate-blue dashboards
     with a champagne-gold action colour, dense tabular numbers — a clean
     accounting ledger / financial statement." The header reads as a
     statement letterhead: a plain slate-blue wordmark bar closed off by a
     LEDGER DOUBLE-RULE (a thin hairline immediately followed by a heavier
     gold rule) — the classic bookkeeping "underline the total twice"
     mark — instead of the soft fade (default), gold pulse rule
     (aries-contrast), colour-block border (origin-bold) or fading hairline
     (noir-reserve) every other header uses. A small "Reconciled" chip with
     a tabular-nums reference number sits beside the wordmark on wider
     phones as the persona's one piece of flavour text. Same inherited
     variables as header/default.blade.php: $brandRoute, $headerBrand,
     $brandIcon, $headerActions (optional slot). --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between bg-white px-4 pb-2.5 pt-3 lg:hidden dark:bg-navy">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
        <span class="hidden shrink-0 items-center gap-1 rounded-md border border-primary/15 bg-primary/5 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary sm:inline-flex dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
            <x-icon name="check" class="h-2.5 w-2.5" /> Reconciled
        </span>
    </a>
    <div class="flex shrink-0 items-center gap-1">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>

    {{-- Ledger double-rule: hairline then a heavier gold rule underneath. --}}
    <span class="pointer-events-none absolute inset-x-0 bottom-0 border-b border-slate-200 dark:border-white/10" aria-hidden="true"></span>
    <span class="pointer-events-none absolute inset-x-0 -bottom-[3px] h-[2px] bg-accent" aria-hidden="true"></span>
</header>
