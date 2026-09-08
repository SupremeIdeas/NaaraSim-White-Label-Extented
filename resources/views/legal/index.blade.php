<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — Legal'">
    <div class="mx-auto max-w-3xl px-4 py-16">
        <h1 class="font-display text-3xl font-bold text-slate-900 dark:text-white">Legal &amp; Policies</h1>
        <p class="mt-3 text-slate-600 dark:text-slate-300">The documents that govern your use of {{ \App\Support\BrandSettings::name() }}. Written to be clear and honest — reviewed for your protection.</p>

        <div class="mt-8 grid gap-3 sm:grid-cols-2">
            @foreach ($docs as $doc)
                <a href="{{ route('legal.show', $doc['slug']) }}"
                   class="group flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-primary/50 hover:shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <span class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                            <x-icon name="file-text" class="h-5 w-5" />
                        </span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $doc['title'] }}</span>
                    </span>
                    <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-primary" />
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.marketing>
