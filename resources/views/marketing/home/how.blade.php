{{-- How it works (CMS: home.how) — STACKING image+text step cards on scroll.
     Each card is sticky, so the next stacks over it. The step artwork is
     admin-swappable (Admin → Pages → Home → "Connected in Three Steps"). --}}
<section class="mx-auto max-w-5xl px-4 py-20" data-bg="light">
    <div class="text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
        <p data-reveal class="mx-auto mt-3 max-w-xl text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
    </div>

    <div class="mkt-stack mt-12">
        @foreach ([1, 2, 3] as $n)
            @php($img = \App\Support\SiteContent::imageUrl($s["step_{$n}_image"] ?? ''))
            <div class="mkt-stack__card overflow-hidden border border-slate-200 bg-white dark:border-[var(--brand-card-border-dark)] dark:bg-[#16233d]" style="top: calc(5.5rem + {{ ($n - 1) * 1.25 }}rem)">
                <div class="grid items-center gap-6 sm:gap-10 {{ $img ? 'md:grid-cols-2' : '' }} {{ $n % 2 === 0 ? 'md:[&>figure]:order-first' : '' }}">
                    <div>
                        <div class="flex items-start gap-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary font-display text-xl font-bold text-white shadow-lg shadow-primary/30">{{ $n }}</span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-primary/70 dark:text-teal-300/70">Step {{ $n }}</p>
                                <h3 class="mt-1 text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">{{ $s["step_{$n}_title"] }}</h3>
                            </div>
                        </div>
                        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["step_{$n}_text"] }}</p>
                    </div>

                    @if ($img)
                        <figure class="relative">
                            {{-- Soft teal/gold aura behind the transparent glass render. --}}
                            <div class="pointer-events-none absolute inset-0 -z-10 scale-90 rounded-[2.5rem] bg-gradient-to-br from-primary/20 via-accent/10 to-transparent blur-2xl dark:from-primary/30 dark:via-accent/20" aria-hidden="true"></div>
                            <img src="{{ $img }}" alt="{{ $s["step_{$n}_title"] }}" loading="lazy" decoding="async"
                                 class="mx-auto w-full max-w-sm drop-shadow-2xl" width="1512" height="1024" />
                        </figure>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div data-reveal class="mt-12 text-center">
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary !px-8 !py-3">{{ $s['cta'] }}</a>
    </div>
</section>
