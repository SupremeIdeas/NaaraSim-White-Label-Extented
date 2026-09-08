{{-- Per-theme custom Contact page — "midnight-signal" (Theme visual
     rebuild, owner request 2026-09-07). REWRITTEN (owner feedback: stop
     recolouring the same form+sidebar skeleton on every theme). Structure
     traces to Cmouse's split hero — a solid colour panel holding the
     headline + the real form on one side, a photo card with a floating
     rating-style pill overlapping it on the other — plus a horizontal
     icon-service strip along the bottom (Cmouse's Hairdressing/Massage/
     Eye Care/Nail Beauty row) instead of a vertical sidebar stack. The
     real `<livewire:contact-form />` component sits inside a light inner
     card so its fields keep proper contrast on the gradient panel. Photo
     is a real, locally-committed WebP under public/images/themes/ (owner
     rule: no live external hotlinks, no empty placeholders) — a real
     support-team photo fits "real humans, real answers" better than a
     device shot here. --}}
<section class="bg-navy px-4 pb-4 pt-16 sm:pt-20">
    <div class="mx-auto grid max-w-5xl gap-6 overflow-hidden rounded-[2.5rem] lg:grid-cols-2">
        <div class="rounded-[2.5rem] bg-gradient-to-br from-primary-dark to-primary p-6 sm:p-10 lg:rounded-r-none">
            <h1 class="font-display text-3xl font-bold leading-tight text-white sm:text-4xl">
                {{ $content['headline'] }}
            </h1>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-white/90 sm:text-base">
                {{ $content['subtext'] }}
            </p>

            <div class="mt-8 rounded-[2rem] bg-white p-6 dark:bg-[#12172a]">
                <h2 class="mb-4 font-display text-lg font-bold text-slate-900 dark:text-white">Send a signal</h2>
                <livewire:contact-form />
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[2.5rem] lg:rounded-l-none">
            <img src="{{ asset('images/themes/shared/team-coworking.webp') }}"
                 alt="" class="h-64 w-full object-cover lg:h-full">
            <div class="absolute inset-0 bg-gradient-to-t from-navy/80 via-navy/10 to-transparent"></div>

            <div class="absolute left-6 top-6 flex items-center gap-2 rounded-full bg-navy/90 px-4 py-2 shadow-lg backdrop-blur">
                <x-icon name="star" class="h-3.5 w-3.5 text-primary" />
                <div>
                    <p class="text-[11px] font-semibold leading-none text-white">Usually replies within the hour</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Channels — horizontal icon-service strip along the bottom. --}}
<section class="bg-navy px-4 py-16">
    <div class="mx-auto flex max-w-4xl flex-wrap justify-center gap-6 sm:gap-10">
        <div class="flex flex-col items-center gap-2 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon name="message-circle" class="h-5 w-5" /></span>
            <a href="{{ auth()->check() ? route('support') : route('login') }}" class="text-xs font-semibold text-white hover:underline">Live chat</a>
        </div>
        <div class="flex flex-col items-center gap-2 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon name="mail" class="h-5 w-5" /></span>
            <a href="mailto:{{ config('naara.support.email') }}" class="text-xs font-semibold text-white hover:underline">Email</a>
        </div>
        @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
            <div class="flex flex-col items-center gap-2 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon name="phone" class="h-5 w-5" /></span>
                <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="text-xs font-semibold text-white hover:underline">WhatsApp</a>
            </div>
        @endif
        <div class="flex flex-col items-center gap-2 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon name="help-circle" class="h-5 w-5" /></span>
            <a href="{{ route('how-it-works') }}" wire:navigate class="text-xs font-semibold text-white hover:underline">Help centre</a>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
