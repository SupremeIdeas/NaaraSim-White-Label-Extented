{{-- Per-theme custom Contact page — "fintra-clean" ("Ledger" persona, Theme
     visual rebuild, 2026-09-07). Structurally different from aries-
     contrast's hard-split two-column layout with a solid vertical rule
     divider: a STACKED single-column composition — the real
     `<livewire:contact-form />` lives inside its own bordered "inquiry
     ticket" card (a dashed ledger header strip with a ticket label and a
     live date stamp), then the channels render BELOW it as a numbered
     ledger table (CH-01/02/03/04 reference codes), never a side panel.
     A small committed photo (coworking-desk.webp, reused from the shared
     pool) sits above the channel rows as a "Support desk, live" caption
     banner. --}}
<section class="border-b border-slate-100 bg-white dark:border-white/5 dark:bg-navy">
    <div class="mx-auto max-w-2xl px-5 py-16 text-center sm:px-6 sm:py-20">
        <h1 class="font-display text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="bg-[#F8F9FA] py-16 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-2xl px-5 sm:px-6">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-navy">
            <div class="flex items-center justify-between border-b border-dashed border-slate-200 px-6 py-3 dark:border-white/10">
                <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">New inquiry ticket</span>
                <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">{{ now()->format('Y-m-d') }}</span>
            </div>
            <div class="p-6 sm:p-8">
                <livewire:contact-form />
            </div>
        </div>
    </div>
</section>

<section class="border-t border-slate-100 bg-white py-16 dark:border-white/5 dark:bg-navy sm:py-20">
    <div class="mx-auto max-w-2xl px-5 sm:px-6">
        {{-- Committed photo banner — reused from the shared pool. --}}
        <div class="relative mb-6 overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
            <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}" alt="" class="h-28 w-full object-cover sm:h-36">
            <span class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-md bg-navy/90 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">
                <x-icon name="check" class="h-2.5 w-2.5 text-accent" /> Support desk, live
            </span>
        </div>

        <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10">
            <h2 class="font-display text-lg font-bold text-slate-900 dark:text-white">Other channels</h2>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500">Live desk</span>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-white/10">
            <div class="flex items-center gap-4 py-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-white/5 dark:text-slate-200">
                    <x-icon name="message-circle" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Live chat</p>
                    <a href="{{ auth()->check() ? route('support') : route('login') }}" class="text-xs text-slate-500 hover:text-primary dark:text-slate-400">Usually online now</a>
                </div>
                <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-slate-300 dark:text-slate-600">CH-01</span>
            </div>
            <div class="flex items-center gap-4 py-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-white/5 dark:text-slate-200">
                    <x-icon name="mail" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Email</p>
                    <a href="mailto:{{ config('naara.support.email') }}" class="text-xs text-slate-500 hover:text-primary dark:text-slate-400">{{ config('naara.support.email') }}</a>
                </div>
                <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-slate-300 dark:text-slate-600">CH-02</span>
            </div>
            @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                <div class="flex items-center gap-4 py-4">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-white/5 dark:text-slate-200">
                        <x-icon name="phone" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">WhatsApp</p>
                        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="text-xs text-slate-500 hover:text-primary dark:text-slate-400">Chat directly</a>
                    </div>
                    <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-slate-300 dark:text-slate-600">CH-03</span>
                </div>
            @endif
            <div class="flex items-center gap-4 py-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-white/5 dark:text-slate-200">
                    <x-icon name="help-circle" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Help centre</p>
                    <a href="{{ route('how-it-works') }}" wire:navigate class="text-xs text-slate-500 hover:text-primary dark:text-slate-400">Read the process</a>
                </div>
                <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-slate-300 dark:text-slate-600">CH-04</span>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
