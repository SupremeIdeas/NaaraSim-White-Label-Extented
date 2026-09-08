<x-layouts.marketing :title="$doc['title'].' — '.\App\Support\BrandSettings::name()">
    <article class="mx-auto max-w-3xl px-4 py-16">
        <a href="{{ route('legal') }}" class="mb-6 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> All policies
        </a>
        <h1 class="font-display text-3xl font-bold text-slate-900 dark:text-white">{{ $doc['title'] }}</h1>
        @if ($doc['updated'])
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Last updated {{ \Illuminate\Support\Carbon::parse($doc['updated'])->format('F j, Y') }}</p>
        @endif

        <x-prose :body="$doc['body']" class="mt-8" />

        <p class="mt-10 border-t border-slate-200 pt-6 text-xs text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:text-slate-500">
            {{ \App\Support\BrandSettings::name() }} is a product of Supreme Ideas Agency. These policies are provided in good faith and are not a substitute for legal advice.
        </p>
    </article>
</x-layouts.marketing>
