{{-- Per-theme custom Contact page — "solar-flare" (Theme Batch 2,
     2026-09-07). Structurally distinct from neon-vertex's floating-stat-
     cards-flanking-headline layout and midnight-signal's console form: a
     SCOREBOARD SPLIT — a dark ticker-style "average reply time" readout
     panel sits alongside the real `<livewire:contact-form />` component
     (never faked, reused as-is) inside one two-column console frame.
     Channels render as angular clip-path-cut cards below, echoing the
     landing page's feature strip. --}}
<section class="relative overflow-hidden bg-navy pb-6">
    <div class="pointer-events-none absolute inset-0 opacity-[0.10]" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 34px);"></div>
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-2xl px-4 pb-10 pt-20 text-center sm:pt-24">
        <span class="inline-flex items-center gap-1.5 border border-accent/60 bg-accent/15 px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-[0.15em] text-accent [clip-path:polygon(6%_0,100%_0,94%_100%,0_100%)]">
            <span class="relative flex h-1.5 w-1.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
            </span>
            On the line
        </span>
        <h1 class="mx-auto mt-5 font-display text-4xl font-black uppercase leading-[1.05] text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

{{-- Scoreboard split: readout panel + the real form, one console frame. --}}
<section class="relative mx-auto max-w-5xl px-4 pb-24">
    <div class="grid overflow-hidden border border-slate-200 shadow-xl dark:border-white/10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        {{-- Readout panel — a real photo behind the navy wash + stripes, not
             a flat colour block (owner rule: no empty/photo-less panel where
             one visually fits; picked over the landing page's photo so no
             two pages in this suite repeat the same shot). --}}
        <div class="relative flex flex-col justify-between gap-8 overflow-hidden bg-navy p-7 sm:p-9">
            <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover opacity-30">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/95 via-navy/85 to-navy/95"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.14]" aria-hidden="true"
                 style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 30px);"></div>
            <div class="relative">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-primary">Average reply time</p>
                <p class="mt-2 font-display text-5xl font-black leading-none text-white sm:text-6xl">&lt;1<span class="text-2xl sm:text-3xl">hr</span></p>
            </div>
            <div class="relative grid grid-cols-2 gap-4 border-t border-white/10 pt-6">
                <div class="border-l-2 border-primary pl-3">
                    <p class="font-display text-xl font-black text-white">190+</p>
                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Countries live</p>
                </div>
                <div class="border-l-2 border-accent pl-3">
                    <p class="font-display text-xl font-black text-white">24/7</p>
                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">On the clock</p>
                </div>
            </div>
        </div>

        {{-- The real form. --}}
        <div class="bg-white p-7 dark:bg-[#12172a] sm:p-9">
            <h2 class="mb-5 font-display text-xl font-bold text-slate-900 dark:text-white">Send us a message</h2>
            <livewire:contact-form />
        </div>
    </div>
</section>

{{-- Channels — angular clip-path-cut cards, echoing the landing feature strip. --}}
<section class="bg-[#F8F9FA] py-16 dark:bg-[#0c1220]">
    <div class="mx-auto max-w-5xl px-4">
        <h2 class="text-center text-sm font-extrabold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Other ways to reach us</h2>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="relative overflow-hidden border border-slate-200 bg-white p-6 pt-8 dark:border-white/10 dark:bg-[#12172a]">
                <span class="absolute left-0 top-0 h-1.5 w-full bg-gradient-to-r from-primary to-accent" aria-hidden="true"></span>
                <span class="flex h-10 w-10 items-center justify-center bg-gradient-to-br from-primary to-accent text-white [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]"><x-icon name="message-circle" class="h-5 w-5" /></span>
                <h3 class="mt-4 font-bold text-slate-900 dark:text-white">Live chat</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Usually online — get a real answer in minutes.</p>
                <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-3 inline-block text-sm font-bold text-primary-dark hover:underline dark:text-primary">Open the chat</a>
            </div>

            <div class="relative overflow-hidden border border-slate-200 bg-white p-6 pt-8 dark:border-white/10 dark:bg-[#12172a]">
                <span class="absolute left-0 top-0 h-1.5 w-full bg-gradient-to-r from-primary to-accent" aria-hidden="true"></span>
                <span class="flex h-10 w-10 items-center justify-center bg-gradient-to-br from-primary to-accent text-white [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]"><x-icon name="mail" class="h-5 w-5" /></span>
                <h3 class="mt-4 font-bold text-slate-900 dark:text-white">Email</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">For anything that needs a paper trail.</p>
                <a href="mailto:{{ config('naara.support.email') }}" class="mt-3 inline-block text-sm font-bold text-primary-dark hover:underline dark:text-primary">{{ config('naara.support.email') }}</a>
            </div>

            @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                <div class="relative overflow-hidden border border-slate-200 bg-white p-6 pt-8 dark:border-white/10 dark:bg-[#12172a]">
                    <span class="absolute left-0 top-0 h-1.5 w-full bg-gradient-to-r from-primary to-accent" aria-hidden="true"></span>
                    <span class="flex h-10 w-10 items-center justify-center bg-gradient-to-br from-primary to-accent text-white [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]"><x-icon name="phone" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-bold text-slate-900 dark:text-white">WhatsApp</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Message us directly for quick questions.</p>
                    <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-bold text-primary-dark hover:underline dark:text-primary">Chat on WhatsApp</a>
                </div>
            @endif
        </div>
    </div>
</section>

@include('marketing._reused-sections')
