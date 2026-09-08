{{-- Testimonials (CMS: home.testimonials) — the TEAL scene + star ratings
     reusing the Module 20 star-rating component. --}}
<section class="bg-[#07403f] px-4 py-24 text-teal-50" data-bg="teal">
    <div class="mx-auto max-w-6xl">
        <div class="text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $s['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-4xl">{{ $s['headline'] }}</h2>
        </div>
        <div class="mt-12 grid gap-5 md:grid-cols-3">
            @foreach ([1, 2, 3] as $n)
                <figure data-reveal style="--reveal-delay: {{ ($n - 1) * 0.09 }}s"
                        class="rounded-2xl border border-white/15 bg-white/5 p-6 backdrop-blur">
                    <x-ui.star-rating :value="5" />
                    <blockquote class="mt-4 text-sm leading-relaxed opacity-95">&ldquo;{{ $s["t{$n}_quote"] }}&rdquo;</blockquote>
                    <figcaption class="mt-5">
                        <p class="font-semibold">{{ $s["t{$n}_name"] }}</p>
                        <p class="text-xs text-accent">{{ $s["t{$n}_route"] }}</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
