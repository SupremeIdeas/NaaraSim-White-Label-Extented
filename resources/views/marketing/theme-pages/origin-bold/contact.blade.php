{{-- Per-theme custom Contact page — "origin-bold" (Theme visual rebuild,
     owner request 2026-09-07). Structurally distinct from neon-vertex's
     floating-stat-cards hero and midnight-signal's colour-panel-plus-photo
     split: origin-bold echoes the LOGIN SCREEN's own reversed-split idea —
     the content/form column LEADS at 60% width, a solid colour-block
     channels panel TRAILS at 40% (the exact inverse ratio of the About
     page's founder split, where the colour panel leads narrow and the
     plain panel trails wide). Channels render as big SQUARE icon-buttons
     inside the colour block, not small icon circles in a sidebar list.
     The real `<livewire:contact-form />` component is reused as-is — a
     theme reskins the surrounding chrome, never the functional form. --}}
<section class="bg-white dark:bg-navy">
    <div class="flex flex-col lg:flex-row">
        {{-- Form column — leads at 60%, matching the login screen's own
             lead-panel proportion. `min-w-0` keeps long copy from forcing
             this column past its intended width. --}}
        <div class="flex min-w-0 flex-1 flex-col justify-center px-4 py-16 sm:px-8 sm:py-20 lg:w-3/5 lg:py-24">
            <div class="mx-auto w-full max-w-lg">
                <div class="border-l-4 border-primary pl-4">
                    <h1 class="font-display text-4xl font-black uppercase leading-[0.95] text-navy dark:text-white sm:text-5xl">
                        {{ $content['headline'] }}
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                        {{ $content['subtext'] }}
                    </p>
                </div>

                <div class="mt-9 border-4 border-navy p-6 dark:border-white/20 sm:p-8">
                    <livewire:contact-form />
                </div>
            </div>
        </div>

        {{-- Colour-block channels panel — trails at 40%, oversized square
             icon-buttons instead of small icon circles in a sidebar. --}}
        <div class="relative flex min-w-0 items-center justify-center overflow-hidden border-t-4 border-navy bg-navy px-4 py-14 dark:border-white/20 lg:w-2/5 lg:border-l-4 lg:border-t-0">
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden opacity-[0.08]" aria-hidden="true">
                <span class="select-none font-display text-[9rem] font-black leading-none text-white">24/7</span>
            </div>

            <div class="relative w-full max-w-sm">
                <p class="mb-5 text-center text-xs font-black uppercase tracking-widest text-primary">Other ways to reach us</p>
                <div class="grid grid-cols-2 gap-4">
                    <a href="{{ auth()->check() ? route('support') : route('login') }}"
                       class="group flex flex-col items-center justify-center gap-2.5 border-4 border-white/20 bg-white/5 p-6 text-center transition hover:border-primary hover:bg-primary">
                        <x-icon name="message-circle" class="h-7 w-7 text-white" />
                        <span class="text-xs font-black uppercase tracking-wide text-white">Live chat</span>
                    </a>
                    <a href="mailto:{{ config('naara.support.email') }}"
                       class="group flex flex-col items-center justify-center gap-2.5 border-4 border-white/20 bg-white/5 p-6 text-center transition hover:border-primary hover:bg-primary">
                        <x-icon name="mail" class="h-7 w-7 text-white" />
                        <span class="text-xs font-black uppercase tracking-wide text-white">Email</span>
                    </a>
                    @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener"
                           class="group flex flex-col items-center justify-center gap-2.5 border-4 border-white/20 bg-white/5 p-6 text-center transition hover:border-primary hover:bg-primary">
                            <x-icon name="phone" class="h-7 w-7 text-white" />
                            <span class="text-xs font-black uppercase tracking-wide text-white">WhatsApp</span>
                        </a>
                    @endif
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="group flex flex-col items-center justify-center gap-2.5 border-4 border-white/20 bg-white/5 p-6 text-center transition hover:border-primary hover:bg-primary">
                        <x-icon name="help-circle" class="h-7 w-7 text-white" />
                        <span class="text-xs font-black uppercase tracking-wide text-white">Help centre</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
