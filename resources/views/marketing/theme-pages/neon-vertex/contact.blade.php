{{-- Per-theme custom Contact page — "neon-vertex" (Theme visual rebuild,
     owner request 2026-09-07). REWRITTEN (owner feedback: stop recolouring
     the same form+sidebar skeleton on every theme). Structure here traces
     to Airlume.ai's hero — floating stat cards flanking the headline, a
     floating "reply time" badge overlapping the form card's corner — plus
     a horizontal trust-pill strip instead of a plain paragraph. The real
     `<livewire:contact-form />` component is reused as-is — a theme
     reskins the surrounding chrome, never the functional form. Channels
     render as a horizontal scroll strip (ClassiAds' listing-rail pattern)
     rather than a vertical sidebar stack. --}}
<section class="relative overflow-hidden bg-white pb-6 dark:bg-navy">
    <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-gradient-to-br from-primary/25 via-accent/20 to-transparent blur-3xl" aria-hidden="true"></div>

    {{-- Floating stat cards flank the headline OUTSIDE its own text column
         (a wider positioning wrapper than the narrower headline container),
         so they never sit on top of the text itself — desktop only, they'd
         collide with the text on a narrow viewport regardless. --}}
    <div class="relative mx-auto hidden max-w-5xl px-4 lg:block">
        <div class="pointer-events-none absolute left-4 top-36 -rotate-6 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-left shadow-lg dark:border-white/10 dark:bg-[#12172a]">
            <p class="text-[10px] uppercase tracking-wide text-slate-400">Avg. reply</p>
            <p class="font-display text-sm font-bold text-slate-900 dark:text-white">&lt; 1 hour</p>
        </div>
        <div class="pointer-events-none absolute right-4 top-44 rotate-6 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-left shadow-lg dark:border-white/10 dark:bg-[#12172a]">
            <p class="text-[10px] uppercase tracking-wide text-slate-400">Coverage</p>
            <p class="font-display text-sm font-bold text-slate-900 dark:text-white">190+ countries</p>
        </div>
    </div>

    <div class="relative mx-auto max-w-2xl px-4 pb-6 pt-20 text-center sm:pt-24">
        <h1 class="mx-auto font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>

    {{-- Trust strip — horizontal on every viewport, wraps on very small screens. --}}
    <div class="relative mx-auto flex max-w-2xl flex-wrap justify-center gap-3 px-4 pb-10 text-xs font-semibold text-slate-500 dark:text-slate-400">
        @foreach ([['icon' => 'globe', 'label' => '190+ countries'], ['icon' => 'clock', 'label' => '24/7 support'], ['icon' => 'shield-check', 'label' => 'Bank-grade security']] as $pill)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 dark:border-white/10">
                <x-icon :name="$pill['icon']" class="h-3.5 w-3.5 text-primary" /> {{ $pill['label'] }}
            </span>
        @endforeach
    </div>
</section>

<section class="relative mx-auto max-w-3xl px-4 pb-24">
    <div class="relative rounded-[2.5rem] border border-slate-200 bg-white p-6 sm:p-8 dark:border-white/10 dark:bg-[#12172a]">
        <span class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-primary to-accent px-3 py-1 text-[11px] font-semibold text-white sm:absolute sm:-top-4 sm:right-8">
            <x-icon name="zap" class="h-3 w-3" /> Usually replies within the hour
        </span>
        <h2 class="mb-5 mt-3 font-display text-xl font-bold text-slate-900 dark:text-white sm:mt-0">Send us a message</h2>
        <livewire:contact-form />
    </div>
</section>

{{-- Channels — horizontal snap-scroll rail. --}}
<section class="pb-24">
    <h2 class="px-4 text-center text-sm font-semibold uppercase tracking-widest text-slate-400">Other ways to reach us</h2>
    <div class="mt-6 flex snap-x snap-mandatory justify-center gap-4 overflow-x-auto px-4 pb-4">
        <div class="w-64 shrink-0 snap-start rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white"><x-icon name="message-circle" class="h-4 w-4" /></span>
            <h3 class="mt-3 font-bold text-slate-900 dark:text-white">Live chat</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Usually online — get a real answer in minutes.</p>
            <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Open the chat</a>
        </div>

        <div class="w-64 shrink-0 snap-start rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white"><x-icon name="mail" class="h-4 w-4" /></span>
            <h3 class="mt-3 font-bold text-slate-900 dark:text-white">Email</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">For anything that needs a paper trail.</p>
            <a href="mailto:{{ config('naara.support.email') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">{{ config('naara.support.email') }}</a>
        </div>

        @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
            <div class="w-64 shrink-0 snap-start rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white"><x-icon name="phone" class="h-4 w-4" /></span>
                <h3 class="mt-3 font-bold text-slate-900 dark:text-white">WhatsApp</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Message us directly for quick questions.</p>
                <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Chat on WhatsApp</a>
            </div>
        @endif
    </div>
</section>

@include('marketing._reused-sections')
