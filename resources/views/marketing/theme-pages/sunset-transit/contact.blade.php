{{-- Per-theme custom Contact page — "sunset-transit" ("Boarding Pass",
     Theme Batch, 2026-09-07). Structurally distinct from solar-flare's
     side-by-side scoreboard-split (dark readout panel beside the form in
     one console frame): here a row of THREE split-flap readout tiles (the
     same departures-board motif as the landing page) sits ABOVE a single,
     centred BOARDING-PASS TICKET CARD holding the real
     `<livewire:contact-form />` (never faked, reused as-is) — framed with
     the theme's signature dashed border and die-cut notches. Channels
     render as ticket-stub cards below, echoing the landing feature strip. --}}
<section class="relative overflow-hidden bg-navy pb-10 pt-20 sm:pt-24">
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-2xl px-4 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-dashed border-accent/50 bg-accent/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-accent">
            <x-icon name="id-card" class="h-3 w-3" /> Gate desk
        </span>
        <h1 class="mx-auto mt-5 font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl lg:text-[2.65rem]">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

{{-- Cream sheet pulled up over the hero (rule 5(a) treatment, matching every
     other page in this suite). --}}
<section class="relative -mt-6 overflow-hidden rounded-t-[1.75rem] bg-[#FBF7F0] pb-20 pt-12 dark:bg-[#0F172A]">
    <div class="mx-auto max-w-4xl px-4">

        {{-- Split-flap readout row. --}}
        <div class="mx-auto grid max-w-xl grid-cols-3 gap-3 sm:gap-4">
            @foreach ([
                ['value' => '<1hr', 'label' => 'Average reply time'],
                ['value' => '190+', 'label' => 'Countries live'],
                ['value' => '24/7', 'label' => 'Desk always open'],
            ] as $flap)
                <div class="relative overflow-hidden rounded-lg bg-navy px-2 py-4 shadow-inner sm:px-3 sm:py-5">
                    <span class="pointer-events-none absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-black/40" aria-hidden="true"></span>
                    <p class="font-display text-xl font-black leading-none tracking-tight text-white sm:text-2xl" style="font-variant-numeric: tabular-nums;">{{ $flap['value'] }}</p>
                    <p class="mt-2 text-[9px] font-semibold uppercase leading-tight tracking-wider text-slate-400 sm:text-[10px]">{{ $flap['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- The real form, framed as one big ticket card. --}}
        <div class="relative mx-auto mt-10 max-w-xl overflow-hidden rounded-2xl border-2 border-dashed border-primary/25 bg-white p-7 shadow-xl dark:border-white/15 dark:bg-white/5 sm:p-9">
            <span class="pointer-events-none absolute -left-2.5 top-1/2 hidden h-5 w-5 -translate-y-1/2 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A] sm:block" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -right-2.5 top-1/2 hidden h-5 w-5 -translate-y-1/2 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A] sm:block" aria-hidden="true"></span>
            <h2 class="mb-5 font-display text-xl font-bold text-[#1B2A47] dark:text-white">Send a note to the gate desk</h2>
            <livewire:contact-form />
        </div>

        {{-- Channels — ticket-stub cards, dashed perimeter, die-cut notch. --}}
        <div class="mx-auto mt-14 max-w-3xl">
            <h2 class="text-center text-xs font-bold uppercase tracking-[0.2em] text-stone-500 dark:text-slate-400">Other ways to reach the desk</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary/20 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <span class="pointer-events-none absolute -left-2.5 top-6 h-5 w-5 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white"><x-icon name="message-circle" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-display text-base font-bold text-[#1B2A47] dark:text-white">Live chat</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">Usually online — get a real answer in minutes.</p>
                    <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-3 inline-block text-sm font-bold text-primary hover:underline dark:text-accent">Open the chat</a>
                </div>

                <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary/20 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <span class="pointer-events-none absolute -left-2.5 top-6 h-5 w-5 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white"><x-icon name="mail" class="h-5 w-5" /></span>
                    <h3 class="mt-4 font-display text-base font-bold text-[#1B2A47] dark:text-white">Email</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">For anything that needs a paper trail.</p>
                    <a href="mailto:{{ config('naara.support.email') }}" class="mt-3 inline-block text-sm font-bold text-primary hover:underline dark:text-accent">{{ config('naara.support.email') }}</a>
                </div>

                @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                    <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary/20 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                        <span class="pointer-events-none absolute -left-2.5 top-6 h-5 w-5 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white"><x-icon name="phone" class="h-5 w-5" /></span>
                        <h3 class="mt-4 font-display text-base font-bold text-[#1B2A47] dark:text-white">WhatsApp</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">Message us directly for quick questions.</p>
                        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-bold text-primary hover:underline dark:text-accent">Chat on WhatsApp</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
