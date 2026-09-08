<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — Contact'">
    {{-- Per-theme custom Contact page (owner request, 2026-09-07): a theme
         with its own hand-built layout takes over completely, before the
         Section Builder / SiteContent flow below ever runs. --}}
    @php($themeContactStyle = \App\Support\ThemePreset::sectionStyle('contact_page'))
    @if ($themeContactStyle !== 'default' && \App\Support\ThemePageLibrary::has('contact_page', $themeContactStyle))
        @include(\App\Support\ThemePageLibrary::bladeFor('contact_page', $themeContactStyle), ['content' => \App\Support\ThemePreset::pageContent('contact_page')])
    @else
    {{-- Section Builder output wins when published; else the existing content (BUILD-6 §B). --}}
    @php($builtSections = \App\Support\PageSections::live('contact'))
    @if (! empty($builtSections))
        @include('partials.sections.render', ['sections' => $builtSections])
    @else
    @php($hero = $sections['hero'] ?? null)
    @if ($hero)
        <section class="mx-auto max-w-3xl px-4 pb-10 pt-16 text-center">
            <h1 data-reveal class="text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $hero['headline'] }}</h1>
            <p data-reveal class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $hero['subtext'] }}</p>
            <p data-reveal class="mt-5 inline-block rounded-full bg-primary/10 px-4 py-1.5 text-xs font-semibold text-primary dark:bg-primary/20 dark:text-teal-300">{{ $hero['promise'] }}</p>
        </section>
    @endif

    <section class="mx-auto grid max-w-5xl gap-8 px-4 pb-24 lg:grid-cols-[1fr_320px]">
        @php($form = $sections['form'] ?? null)
        <div data-reveal class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-[var(--brand-card-border-dark)] dark:bg-[#16233d]">
            <h2 class="mb-5 text-xl font-bold text-slate-900 dark:text-white">{{ $form['headline'] ?? 'Send Us a Message' }}</h2>
            <livewire:contact-form />
        </div>

        @php($channels = $sections['channels'] ?? null)
        @if ($channels)
            <aside class="space-y-4">
                <h2 data-reveal class="text-sm font-semibold uppercase tracking-widest text-slate-400">{{ $channels['headline'] }}</h2>

                <div data-reveal class="nx-card">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="message-circle" class="h-4 w-4" /></span>
                    <h3 class="mt-3 font-bold text-slate-900 dark:text-white">{{ $channels['chat_title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $channels['chat_text'] }}</p>
                    <a href="{{ auth()->check() ? route('support') : route('login') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Open the chat</a>
                </div>

                <div data-reveal class="nx-card">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="mail" class="h-4 w-4" /></span>
                    <h3 class="mt-3 font-bold text-slate-900 dark:text-white">{{ $channels['email_title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $channels['email_text'] }}</p>
                    <a href="mailto:{{ config('naara.support.email') }}" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">{{ config('naara.support.email') }}</a>
                </div>

                @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                    <div data-reveal class="nx-card">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="phone" class="h-4 w-4" /></span>
                        <h3 class="mt-3 font-bold text-slate-900 dark:text-white">WhatsApp</h3>
                        <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-300">Message us directly for quick questions.</p>
                        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Chat on WhatsApp</a>
                    </div>
                @endif

                <p data-reveal class="px-1 text-xs leading-relaxed text-slate-400">{{ $channels['partners'] }}</p>
            </aside>
        @endif
    </section>
    @include('marketing._reused-sections')
    @endif
    @endif
</x-layouts.marketing>
