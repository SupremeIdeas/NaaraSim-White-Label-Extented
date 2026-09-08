{{-- Homepage story section (BUILD-3 §9) — admin-editable vision/mission,
     scroll-revealed, then one deliberate transition line that leads into the
     stacking-containers section so the scroll reads as one narrative. Renders
     only when the admin enabled it. Uses the existing [data-reveal] infra
     (JS-gated + reduced-motion-safe), so no-JS users still see the content. --}}
@if (\App\Support\HomeStory::isVisible())
    <section class="relative overflow-hidden bg-white py-20 sm:py-28 dark:bg-[#0D1B2A]">
        {{-- Soft brand aura, matching the site's glow treatment. --}}
        <div class="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full bg-primary/10 blur-3xl dark:bg-teal-500/10" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-24 bottom-0 h-72 w-72 rounded-full bg-accent/10 blur-3xl dark:bg-accent/10" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-3xl px-4 text-center">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-primary dark:text-teal-300">
                {{ \App\Support\HomeStory::eyebrow() }}
            </p>
            <h2 data-reveal style="--reveal-delay: 80ms"
                class="mt-4 font-display text-3xl font-bold leading-tight text-slate-900 sm:text-4xl dark:text-white">
                {{ \App\Support\HomeStory::heading() }}
            </h2>
            <div class="mt-6 space-y-4">
                @foreach (\App\Support\HomeStory::paragraphs() as $i => $para)
                    <p data-reveal style="--reveal-delay: {{ 160 + $i * 80 }}ms"
                       class="text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">{{ $para }}</p>
                @endforeach
            </div>
        </div>

        {{-- Deliberate transition into the stacking section. --}}
        <div data-reveal style="--reveal-delay: 320ms" class="relative mx-auto mt-14 flex max-w-3xl flex-col items-center px-4 text-center">
            <p class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ \App\Support\HomeStory::transition() }}</p>
            <span class="mt-4 flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-dark text-white shadow-lg shadow-primary/30 motion-safe:animate-bounce">
                <x-icon name="chevron-right" class="h-5 w-5 rotate-90" />
            </span>
        </div>
    </section>
@endif
