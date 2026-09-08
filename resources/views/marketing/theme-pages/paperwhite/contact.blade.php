{{-- Per-theme custom Contact page — "paperwhite" (Theme visual rebuild,
     owner request 2026-09-07). Structurally the opposite of neon-vertex's
     floating-stat-card hero and midnight-signal's console framing: an
     extremely plain centred layout — the real `<livewire:contact-form />`
     inside a hairline-bordered box with no shadow, and the channel list
     rendered as a single inline text line separated by middot characters,
     like a masthead credits line, rather than a sidebar of cards. --}}
<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-xl px-6 pb-10 pt-24 text-center sm:pt-28">
        <h1 class="mx-auto max-w-md font-serif text-4xl italic leading-[1.15] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-sm text-base leading-relaxed text-slate-500 dark:text-slate-400">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="mx-auto max-w-lg px-6 pb-14">
    <div class="border border-slate-200 p-6 dark:border-white/10 sm:p-8">
        <h2 class="mb-6 font-serif text-lg italic text-slate-900 dark:text-white">Send a message</h2>
        <livewire:contact-form />
    </div>
</section>

{{-- Other channels — a single inline masthead credits line, not cards. --}}
<section class="border-t border-slate-200 px-6 py-10 text-center dark:border-white/10">
    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400 dark:text-slate-500">Other ways to reach us</p>
    <p class="mx-auto mt-4 flex max-w-xl flex-wrap items-center justify-center gap-x-2.5 gap-y-1.5 text-sm text-slate-600 dark:text-slate-300">
        <a href="{{ auth()->check() ? route('support') : route('login') }}" class="border-b border-slate-400 pb-0.5 font-medium transition hover:border-slate-900 dark:border-slate-500 dark:hover:border-white">Live chat</a>
        <span aria-hidden="true" class="text-slate-300 dark:text-slate-600">&middot;</span>
        <a href="mailto:{{ config('naara.support.email') }}" class="border-b border-slate-400 pb-0.5 font-medium transition hover:border-slate-900 dark:border-slate-500 dark:hover:border-white">{{ config('naara.support.email') }}</a>
        @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
            <span aria-hidden="true" class="text-slate-300 dark:text-slate-600">&middot;</span>
            <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="border-b border-slate-400 pb-0.5 font-medium transition hover:border-slate-900 dark:border-slate-500 dark:hover:border-white">WhatsApp</a>
        @endif
    </p>
</section>

@include('marketing._reused-sections')
