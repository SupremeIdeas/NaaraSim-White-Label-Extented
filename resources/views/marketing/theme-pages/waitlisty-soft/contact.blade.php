{{-- Per-theme custom Contact page — "waitlisty-soft" ("Horizon", Theme
     visual rebuild). Structurally distinct from midnight-signal's split
     colour-panel-plus-photo-card layout: reuses this persona's own
     "gradient band + overlapping circular photo badge + big rounded card"
     motif already established on its login screen — a deliberate, coherent
     signature for THIS theme (not a copy of another theme's page, since the
     "no shared skeleton" rule is about different themes reusing the same
     page composition, not a theme reusing its own established language
     across its own screens). The real `<livewire:contact-form />` sits
     inside the rounded card; a horizontal row of pill-shaped channel links
     follows below, in place of a vertical sidebar. --}}
<section class="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-accent px-4 pb-20 pt-16 sm:pt-20">
    <span class="pointer-events-none absolute -left-16 top-0 h-56 w-56 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></span>
    <span class="pointer-events-none absolute -right-10 bottom-0 h-48 w-48 rounded-[40%_60%_65%_35%/45%_40%_60%_55%] bg-accent-dark/25 blur-2xl" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-xl text-center">
        <h1 class="font-display text-3xl font-bold leading-tight text-white sm:text-4xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-white/85 sm:text-base">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

{{-- Circular photo badge overlapping the seam. --}}
<div class="relative z-20 mx-auto -mt-11 flex justify-center px-4" aria-hidden="true">
    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full ring-4 ring-[#FBF7FF] shadow-lg dark:ring-navy">
        <img src="{{ asset('images/themes/shared/team-coworking.webp') }}" alt="" class="h-full w-full object-cover">
    </div>
</div>

<section class="relative z-10 -mt-4 bg-[#FBF7FF] px-4 pb-6 pt-0 dark:bg-navy">
    <div class="mx-auto max-w-xl rounded-[2rem] bg-white p-6 pt-9 shadow-[0_20px_50px_-24px_rgba(109,63,160,0.5)] dark:bg-[#1c1329] sm:p-8 sm:pt-10">
        <h2 class="mb-4 text-center font-display text-lg font-bold text-slate-900 dark:text-white">Send us a note</h2>
        <livewire:contact-form />
    </div>
</section>

{{-- Channels — horizontal pill row instead of a vertical sidebar. --}}
<section class="bg-[#FBF7FF] px-4 py-14 dark:bg-navy">
    <div class="mx-auto flex max-w-2xl flex-wrap justify-center gap-4">
        <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm dark:bg-[#1c1329]">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="message-circle" class="h-4 w-4" /></span>
            <a href="{{ auth()->check() ? route('support') : route('login') }}" class="text-xs font-semibold text-slate-800 hover:text-primary dark:text-white">Live chat</a>
        </div>
        <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm dark:bg-[#1c1329]">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="mail" class="h-4 w-4" /></span>
            <a href="mailto:{{ config('naara.support.email') }}" class="text-xs font-semibold text-slate-800 hover:text-primary dark:text-white">Email</a>
        </div>
        @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
            <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm dark:bg-[#1c1329]">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="phone" class="h-4 w-4" /></span>
                <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="text-xs font-semibold text-slate-800 hover:text-primary dark:text-white">WhatsApp</a>
            </div>
        @endif
        <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm dark:bg-[#1c1329]">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="help-circle" class="h-4 w-4" /></span>
            <a href="{{ route('how-it-works') }}" wire:navigate class="text-xs font-semibold text-slate-800 hover:text-primary dark:text-white">Help centre</a>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
