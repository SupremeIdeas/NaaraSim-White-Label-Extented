{{-- Per-theme custom Contact page — "capable-mono" (Theme visual rebuild,
     owner request 2026-09-07). Structurally the opposite of paperwhite's
     plain hairline box and midnight-signal's console-framed hero: the real
     `<livewire:contact-form />` sits inside the same terminal-window card
     used across this theme's suite (login/landing/how-it-works), and the
     channel list renders as a vertical stack of command rows ("$ live-chat",
     "$ email ...") rather than a sidebar of cards or an inline credits line —
     keeping the persona's command-line motif consistent to the last page. --}}
<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-xl px-6 pb-10 pt-24 text-center sm:pt-28">
        <h1 class="mx-auto max-w-md font-display text-4xl font-bold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-sm text-base leading-relaxed text-slate-500 dark:text-white/45">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="mx-auto max-w-lg px-6 pb-8">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-primary">
        <div class="flex items-center gap-3 border-b border-slate-200 bg-[#F1F2F3] px-5 py-2.5 dark:border-white/10 dark:bg-white/[0.03]">
            <span class="flex items-center gap-1.5" aria-hidden="true">
                <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
            </span>
            <span class="font-mono text-[11px] text-slate-400 dark:text-white/35">~/naarasim/contact --send</span>
        </div>
        <div class="p-6 sm:p-8">
            <livewire:contact-form />
        </div>
    </div>
</section>

{{-- Other channels — command-row list, one row each, matching the
     terminal-window motif rather than a sidebar of cards. --}}
<section class="mx-auto max-w-lg px-6 pb-20">
    <p class="mb-3 flex items-center gap-2 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-white/30">
        <span class="h-1.5 w-1.5 shrink-0 rounded-sm bg-accent" aria-hidden="true"></span>
        Other channels
    </p>
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
        <a href="{{ auth()->check() ? route('support') : route('login') }}"
           class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 font-mono text-sm text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-white/70 dark:hover:bg-white/[0.03]">
            <x-icon name="terminal" class="h-4 w-4 shrink-0 text-slate-400 dark:text-white/30" />
            <span class="text-slate-400 dark:text-white/30">$</span> live-chat <span class="text-slate-400 dark:text-white/30">--open</span>
        </a>
        <a href="mailto:{{ config('naara.support.email') }}"
           class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 font-mono text-sm text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-white/70 dark:hover:bg-white/[0.03] {{ \App\Support\Niche\SupportLinks::hasWhatsapp() ? '' : 'last:border-b-0' }}">
            <x-icon name="terminal" class="h-4 w-4 shrink-0 text-slate-400 dark:text-white/30" />
            <span class="text-slate-400 dark:text-white/30">$</span> email <span class="text-slate-400 dark:text-white/30">{{ config('naara.support.email') }}</span>
        </a>
        @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
            <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener"
               class="flex items-center gap-3 px-5 py-4 font-mono text-sm text-slate-700 transition hover:bg-slate-50 dark:text-white/70 dark:hover:bg-white/[0.03]">
                <x-icon name="terminal" class="h-4 w-4 shrink-0 text-slate-400 dark:text-white/30" />
                <span class="text-slate-400 dark:text-white/30">$</span> whatsapp <span class="text-slate-400 dark:text-white/30">--open</span>
            </a>
        @endif
    </div>
</section>

@include('marketing._reused-sections')
