{{-- FAQ (CMS: home.faq) — accessible accordion, no external JS. --}}
<section class="mx-auto max-w-3xl px-4 py-20" data-bg="light">
    <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>

    <div class="mt-10 space-y-3">
        {{-- Renders every populated Q&A (admin can add up to 12); empty slots skipped. --}}
        @foreach (range(1, 12) as $n)
            @continue(empty($s["q{$n}"] ?? null))
            <details data-reveal style="--reveal-delay: {{ ($n - 1) * 0.05 }}s"
                     class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[var(--brand-card-border-dark)] dark:bg-[#16233d]">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-900 dark:text-white">
                    {{ $s["q{$n}"] }}
                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" />
                </summary>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["a{$n}"] }}</p>
            </details>
        @endforeach
    </div>

    <p data-reveal class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ $s['cta'] }} <a href="{{ route('contact') }}" class="font-semibold text-primary hover:underline">Contact us</a>
    </p>
</section>
