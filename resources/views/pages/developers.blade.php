<x-layouts.marketing title="Developer API — NaaraSim" description="Resell NaaraSim eSIM data and numbers from your own app via a simple REST API.">
    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-4 pt-14 pb-8">
        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="key" class="h-3.5 w-3.5" /> Developer API · v1
        </div>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100">
            Build on NaaraSim
        </h1>
        <p class="mt-3 max-w-2xl text-base text-slate-600 dark:text-slate-300">
            Resell eSIM data and numbers from your own app with a simple REST API — prepaid,
            wholesale pricing, and a QR / activation code you relay to your customers.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="nx-btn nx-btn--primary !px-5">Go to my dashboard</a>
            @else
                <a href="{{ route('register') }}" class="nx-btn nx-btn--primary !px-5">Create an account</a>
            @endauth
            <a href="#authentication" class="nx-btn nx-btn--ghost !px-5">Jump to auth</a>
        </div>
    </section>

    {{-- Rendered reference (single source of truth: docs/DEVELOPER-API.md) --}}
    <section class="mx-auto max-w-4xl px-4 pb-24">
        <article class="api-docs rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9 dark:border-[#22314e] dark:bg-[#101d33]">
            {!! $html !!}
        </article>
    </section>

    @push('head')
        <style>
            .api-docs { color: rgb(51 65 85); line-height: 1.7; font-size: .95rem; }
            :root[data-theme="dark"] .api-docs, .api-docs :where(*) { scroll-margin-top: 6rem; }
            @media (prefers-color-scheme: dark) { .api-docs { color: rgb(203 213 225); } }
            :root[data-theme="dark"] .api-docs { color: rgb(203 213 225); }
            :root[data-theme="light"] .api-docs { color: rgb(51 65 85); }

            .api-docs h1 { font-size: 1.7rem; font-weight: 800; margin: 0 0 .5rem; color: rgb(15 23 42); }
            .api-docs h2 { font-size: 1.3rem; font-weight: 700; margin: 2rem 0 .75rem; padding-top: 1.25rem; border-top: 1px solid rgb(226 232 240); color: rgb(15 23 42); }
            .api-docs h3 { font-size: 1.05rem; font-weight: 700; margin: 1.5rem 0 .5rem; color: rgb(15 23 42); }
            @media (prefers-color-scheme: dark) {
                .api-docs h1, .api-docs h2, .api-docs h3 { color: rgb(241 245 249); }
                .api-docs h2 { border-color: rgb(34 49 78); }
            }
            :root[data-theme="dark"] .api-docs :where(h1,h2,h3) { color: rgb(241 245 249); }
            :root[data-theme="dark"] .api-docs h2 { border-color: rgb(34 49 78); }
            :root[data-theme="light"] .api-docs :where(h1,h2,h3) { color: rgb(15 23 42); }
            :root[data-theme="light"] .api-docs h2 { border-color: rgb(226 232 240); }

            .api-docs p, .api-docs ul, .api-docs ol { margin: .75rem 0; }
            .api-docs ul { list-style: disc; padding-left: 1.4rem; }
            .api-docs ol { list-style: decimal; padding-left: 1.4rem; }
            .api-docs li { margin: .25rem 0; }
            .api-docs a { color: rgb(10 110 110); font-weight: 600; text-decoration: none; }
            .api-docs a:hover { text-decoration: underline; }
            :root[data-theme="dark"] .api-docs a { color: rgb(45 212 191); }

            .api-docs code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85em;
                background: rgb(241 245 249); padding: .12em .4em; border-radius: .35rem; }
            .api-docs pre { background: rgb(13 27 42); color: rgb(226 232 240); padding: 1rem 1.1rem;
                border-radius: .75rem; overflow-x: auto; margin: 1rem 0; font-size: .85rem; }
            .api-docs pre code { background: transparent; padding: 0; color: inherit; }
            @media (prefers-color-scheme: dark) { .api-docs code { background: rgb(30 41 59); color: rgb(226 232 240); } }
            :root[data-theme="dark"] .api-docs code { background: rgb(30 41 59); color: rgb(226 232 240); }
            :root[data-theme="light"] .api-docs code { background: rgb(241 245 249); color: rgb(51 65 85); }

            .api-docs table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .85rem; display: block; overflow-x: auto; }
            .api-docs th, .api-docs td { border: 1px solid rgb(226 232 240); padding: .5rem .7rem; text-align: left; vertical-align: top; }
            .api-docs th { background: rgb(248 250 252); font-weight: 700; }
            @media (prefers-color-scheme: dark) {
                .api-docs th, .api-docs td { border-color: rgb(34 49 78); }
                .api-docs th { background: rgb(24 39 66); }
            }
            :root[data-theme="dark"] .api-docs :where(th,td) { border-color: rgb(34 49 78); }
            :root[data-theme="dark"] .api-docs th { background: rgb(24 39 66); }

            .api-docs blockquote { border-left: 3px solid rgb(212 160 23); background: rgb(212 160 23 / .08);
                padding: .6rem .9rem; margin: 1rem 0; border-radius: 0 .5rem .5rem 0; font-size: .9rem; }
            .api-docs hr { border: 0; border-top: 1px solid rgb(226 232 240); margin: 2rem 0; }
            :root[data-theme="dark"] .api-docs hr { border-color: rgb(34 49 78); }
        </style>
    @endpush
</x-layouts.marketing>
