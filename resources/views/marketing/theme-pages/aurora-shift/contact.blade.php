{{-- Per-theme custom Contact page — "aurora-shift" (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" fintech-terminal energy.
     Structurally distinct from every sibling (neon-vertex's floating-stat
     hero + snap-scroll rail, midnight-signal's gradient split panel,
     aries-contrast's hard-split ledger, noir-reserve's magazine-margin
     hairline list): a dark hero band, then the real
     `<livewire:contact-form />` sitting inside its own TERMINAL-WINDOW card
     (app chrome title bar, matching the landing/login/about persona
     device) beside a column of channel "status rows" styled like a live
     systems-status readout (a small solid dot + label + latency-style
     caption per channel) rather than a plain icon list or scroll rail. The
     form itself is never faked or replaced. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0 opacity-[0.3]" aria-hidden="true"
         style="background-image: radial-gradient(rgb(var(--brand-accent) / 0.35) 1px, transparent 1px); background-size: 26px 26px;"></div>
    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/25 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-6 pb-16 pt-20 text-center sm:pt-24 lg:px-8">
        <h1 class="mx-auto max-w-2xl font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<div class="relative bg-[#F8F9FA] dark:bg-[#0c1220]">
    <svg class="absolute inset-x-0 bottom-full block h-8 w-full text-[#F8F9FA] dark:text-[#0c1220] sm:h-12" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
        <path fill="currentColor" d="M0,30 C240,58 480,2 720,26 C960,50 1200,6 1440,28 L1440,60 L0,60 Z" />
    </svg>

    <section class="px-6 pb-24 pt-10 sm:pt-14 lg:px-8">
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-12">
            {{-- Channel status rows — a live systems-status readout, not a
                 plain icon list or a scroll rail. --}}
            <div class="lg:col-span-4">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-accent-dark dark:text-accent">Channel status</p>
                <ul class="mt-5 space-y-3">
                    <li class="flex items-start gap-3 rounded-[1.5rem] border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#12172a]">
                        <span class="relative mt-1 flex h-2 w-2 shrink-0" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-accent"></span>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Live chat</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Online now — a real answer in minutes.</p>
                            <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-1 inline-block text-xs font-semibold text-accent-dark hover:underline dark:text-accent">Open the chat</a>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 rounded-[1.5rem] border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#12172a]">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Email</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">For anything that needs a paper trail.</p>
                            <a href="mailto:{{ config('naara.support.email') }}" class="mt-1 inline-block text-xs font-semibold text-accent-dark hover:underline dark:text-accent">{{ config('naara.support.email') }}</a>
                        </div>
                    </li>
                    @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                        <li class="flex items-start gap-3 rounded-[1.5rem] border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#12172a]">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">WhatsApp</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Message us directly for quick questions.</p>
                                <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-semibold text-accent-dark hover:underline dark:text-accent">Chat on WhatsApp</a>
                            </div>
                        </li>
                    @endif
                    <li class="flex items-start gap-3 rounded-[1.5rem] border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#12172a]">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-slate-300 dark:bg-slate-600" aria-hidden="true"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Reply time</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Median reply, usually within the hour.</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- The real contact form, inside its own terminal-window card. --}}
            <div class="lg:col-span-8">
                <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#12172a]">
                    <div class="flex items-center gap-1.5 border-b border-slate-200 px-4 py-2.5 dark:border-white/10">
                        <span class="h-2 w-2 rounded-full bg-action/70"></span>
                        <span class="h-2 w-2 rounded-full bg-accent/70"></span>
                        <span class="h-2 w-2 rounded-full bg-primary/70"></span>
                        <span class="ml-3 font-display text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">new_message.compose</span>
                    </div>
                    <div class="p-6 sm:p-8">
                        <livewire:contact-form />
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@include('marketing._reused-sections')
