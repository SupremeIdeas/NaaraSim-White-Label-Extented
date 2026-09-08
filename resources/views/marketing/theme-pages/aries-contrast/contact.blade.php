{{-- Per-theme custom Contact page — "aries-contrast" (Theme visual rebuild,
     Batch 2, 2026-09-07). Persona: solid black/white, zero rounding, gold
     rule as the only decoration. Structurally different from neon-vertex's
     floating-stat-card form panel and midnight-signal's gradient split
     panel: a HARD SPLIT two-column layout with a solid gold vertical rule
     as the literal divider between the real `<livewire:contact-form />`
     and the channels list — channels render as a plain list separated by
     thin gold horizontal rules, never cards. The divider becomes a
     horizontal rule instead once the columns stack on mobile, since a
     vertical rule can't survive a single-column layout. A small
     gold-framed photo labels the channels column "Live on duty" — real,
     locally-committed WebP, never an empty slot. --}}
<section class="border-b-2 border-accent bg-white dark:bg-black">
    <div class="mx-auto max-w-2xl px-4 py-16 text-center sm:py-20">
        <h1 class="font-display text-4xl font-black uppercase leading-[0.95] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="bg-white py-16 dark:bg-black sm:py-20">
    <div class="mx-auto grid max-w-5xl divide-y-2 divide-accent border-2 border-slate-900 sm:grid-cols-[1fr_320px] sm:divide-x-2 sm:divide-y-0 dark:border-white">
        <div class="p-6 sm:p-10">
            <p class="mb-5 text-xs font-bold uppercase tracking-[0.2em] text-accent">Send it in</p>
            <livewire:contact-form />
        </div>

        <div class="p-6 sm:p-8">
            <div class="relative border-2 border-accent">
                <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}" alt="" class="h-28 w-full object-cover">
                <span class="absolute bottom-0 left-0 bg-black px-2 py-1 text-[9px] font-bold uppercase tracking-widest text-white">Live on duty</span>
            </div>

            <p class="mb-1 mt-6 text-xs font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-white">Other channels</p>
            <div class="divide-y divide-accent/40">
                <div class="flex items-center gap-3 py-4">
                    <x-icon name="message-circle" class="h-4 w-4 shrink-0 text-accent" />
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Live chat</p>
                        <a href="{{ auth()->check() ? route('support') : route('login') }}" class="text-xs font-semibold text-slate-500 hover:text-accent dark:text-slate-400">Usually online now</a>
                    </div>
                </div>
                <div class="flex items-center gap-3 py-4">
                    <x-icon name="mail" class="h-4 w-4 shrink-0 text-accent" />
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Email</p>
                        <a href="mailto:{{ config('naara.support.email') }}" class="text-xs font-semibold text-slate-500 hover:text-accent dark:text-slate-400">{{ config('naara.support.email') }}</a>
                    </div>
                </div>
                @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                    <div class="flex items-center gap-3 py-4">
                        <x-icon name="phone" class="h-4 w-4 shrink-0 text-accent" />
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">WhatsApp</p>
                            <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="text-xs font-semibold text-slate-500 hover:text-accent dark:text-slate-400">Chat directly</a>
                        </div>
                    </div>
                @endif
                <div class="flex items-center gap-3 py-4">
                    <x-icon name="help-circle" class="h-4 w-4 shrink-0 text-accent" />
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Help centre</p>
                        <a href="{{ route('how-it-works') }}" wire:navigate class="text-xs font-semibold text-slate-500 hover:text-accent dark:text-slate-400">Read the process</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
