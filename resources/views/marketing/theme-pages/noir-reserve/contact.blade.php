{{-- Per-theme custom Contact page — "noir-reserve" (Theme visual rebuild,
     brand-new persona, 2026-09-07). Structurally distinct from every
     sibling: neon-vertex's floating-stat-card hero with a snap-scroll
     channel rail, midnight-signal's gradient split panel, aries-contrast's
     hard-split two-column ledger with a solid gold rule divider. Here: the
     same asymmetric magazine-margin scaffold used on every other
     noir-reserve page — an empty quiet gutter, channels LISTED SIMPLY
     beside the form (a plain hairline-divided list, never a scroll rail or
     cards), and the real `<livewire:contact-form />` sitting inside a
     quiet GLASS CARD (translucent, hairline-bordered) rather than a solid
     panel — the form itself is never faked or replaced. --}}
<section class="relative overflow-hidden bg-[#F7F1EA] dark:bg-navy">
    <div class="mx-auto max-w-6xl px-6 pb-14 pt-20 sm:pt-24 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="hidden lg:col-span-1 lg:block" aria-hidden="true">
                <div class="h-full border-r border-accent/20"></div>
            </div>
            <div class="lg:col-span-7 lg:col-start-2">
                <h1 class="max-w-lg font-display text-3xl font-semibold leading-[1.2] text-[#241D1A] sm:text-4xl dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-4 max-w-md text-base leading-relaxed text-stone-600 dark:text-slate-300">
                    {{ $content['subtext'] }}
                </p>
            </div>
        </div>
    </div>
</section>

<section class="relative -mt-8 overflow-hidden rounded-t-[30px] bg-white pb-24 pt-14 dark:bg-[#1C1512]">
    <div class="mx-auto max-w-6xl px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-8">
            <div class="hidden lg:col-span-1 lg:block" aria-hidden="true">
                <div class="h-full border-r border-accent/15"></div>
            </div>

            {{-- Channels — listed simply, a plain hairline-divided list
                 beside the form, never a scroll rail or cards. --}}
            <div class="lg:col-span-4 lg:col-start-2">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-accent-dark dark:text-accent">Other ways to reach us</p>
                <ul class="mt-5 divide-y divide-accent/15">
                    <li class="flex items-start gap-3 py-4 first:pt-0">
                        <x-icon name="message-circle" class="mt-0.5 h-4 w-4 shrink-0 text-accent-dark dark:text-accent" />
                        <div>
                            <p class="text-sm font-semibold text-[#241D1A] dark:text-white">Live chat</p>
                            <p class="mt-0.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">Usually online — a real answer in minutes.</p>
                            <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-1 inline-block text-sm font-semibold text-accent-dark hover:underline dark:text-accent">Open the chat</a>
                        </div>
                    </li>
                    <li class="flex items-start gap-3 py-4">
                        <x-icon name="mail" class="mt-0.5 h-4 w-4 shrink-0 text-accent-dark dark:text-accent" />
                        <div>
                            <p class="text-sm font-semibold text-[#241D1A] dark:text-white">Email</p>
                            <p class="mt-0.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">For anything that needs a paper trail.</p>
                            <a href="mailto:{{ config('naara.support.email') }}" class="mt-1 inline-block text-sm font-semibold text-accent-dark hover:underline dark:text-accent">{{ config('naara.support.email') }}</a>
                        </div>
                    </li>
                    @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                        <li class="flex items-start gap-3 py-4">
                            <x-icon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-accent-dark dark:text-accent" />
                            <div>
                                <p class="text-sm font-semibold text-[#241D1A] dark:text-white">WhatsApp</p>
                                <p class="mt-0.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">Message us directly for quick questions.</p>
                                <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-1 inline-block text-sm font-semibold text-accent-dark hover:underline dark:text-accent">Chat on WhatsApp</a>
                            </div>
                        </li>
                    @endif
                    <li class="flex items-start gap-3 py-4 last:pb-0">
                        <x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-accent-dark dark:text-accent" />
                        <div>
                            <p class="text-sm font-semibold text-[#241D1A] dark:text-white">Reply time</p>
                            <p class="mt-0.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">Considered replies, usually within the hour.</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- Quiet glass card holding the real contact form. --}}
            <div class="lg:col-span-6 lg:col-start-7">
                <div class="rounded-xl border border-accent/15 bg-[#F7F1EA]/70 p-6 shadow-sm backdrop-blur-md dark:bg-white/[0.04] sm:p-8">
                    <p class="mb-5 text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">Send a message</p>
                    <livewire:contact-form />
                </div>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
