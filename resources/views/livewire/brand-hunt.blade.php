{{-- Owner request (2026-09-22): drop the per-brand scroll-tinted background
     (each card nudged the page background toward its own brand colour on
     intersect) — scrolling past several differently-coloured brands read as
     uneven, inconsistent bands rather than a deliberate design. The page
     just uses the dashboard's own plain background now, like every other
     page. --}}
<div>

    {{-- Hero — owner request (2026-09-22): drop the flat colour block (it
         read as sharp/hard-edged against the dashboard) in favour of the
         SAME transparent, no-card, no-border treatment as the dashboard
         home hero and the Naara Gift storefront hero (nx-home-hero) — text
         sits directly on the dashboard's own light/dark background, which
         is "nice enough" on its own.

         Layout is deliberately pre-shaped for a bleeding image later (owner:
         "we will add image by the side"), matching the Gift storefront
         hero's own proportions exactly (max-w-[70%]/[60%] — a feature-intro
         hero, not the top-level dashboard one) rather than guessing at a
         redesign once art exists: the copy column stays capped on every
         breakpoint, including mobile, and the stat chip moved below the
         description instead of sitting beside the title, clear of that
         top-right corner. A future image slots in exactly like
         `.nx-home-hero__media` in
         resources/views/livewire/partials/gift-cards/_hero.blade.php: an
         absolutely-positioned `<img>` with the same mask-fade, no other
         markup here needs to change. No asset exists yet, so nothing is
         wired to it today — this only reserves the shape. --}}
    <section class="nx-home-hero mb-2">
        <a href="{{ route('rewards') }}" wire:navigate class="relative z-10 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to Rewards
        </a>
        <div class="relative z-10 mt-4 max-w-[70%] sm:max-w-[60%]">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary dark:text-teal-300">Brand Partner Directory</p>
            <h1 class="mt-1.5 font-display text-3xl font-extrabold tracking-[-0.025em] text-slate-900 sm:text-4xl dark:text-white">
                The <span class="bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent">Hunt</span>
            </h1>
            <p class="mt-3.5 text-[15px] leading-relaxed text-slate-500 dark:text-slate-300 sm:text-base">Follow real brands, earn surprise NaaraCredits. Every follow is a one-time reward, server-confirmed the moment you claim it.</p>
        </div>
        <div class="relative z-10 mt-6">
            <div class="inline-flex items-center gap-3 rounded-2xl border border-primary/15 bg-primary/5 px-5 py-3 dark:border-white/10 dark:bg-white/5">
                <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">${{ number_format($dailyRemaining, 0) }}</p>
                <p class="max-w-[6rem] text-[11px] font-medium uppercase leading-tight tracking-wide text-slate-500 dark:text-slate-400">left to earn today</p>
            </div>
        </div>
    </section>

    <div class="mx-auto mt-6 max-w-5xl px-4 pb-10">
        @if ($flash)
            <div class="mb-5 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-200">{{ $flash }}</div>
        @endif

        {{-- Platform: follow NaaraSim's own handles — the "official" band. --}}
        @if ($platformHandles->isNotEmpty())
            <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                    <x-icon name="sparkles" class="h-4 w-4 text-accent-dark dark:text-accent" /> Follow {{ \App\Support\BrandSettings::name() }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($platformHandles as $h)
                        @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedHandles[$h->id]), 'action' => 'followHandle'])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Category filter --}}
        @if ($categories->isNotEmpty())
            <div class="mb-6 flex flex-wrap gap-2">
                <button wire:click="setCategory(null)" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition {{ ! $category ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' }}">All ({{ $totalBrandCount }})</button>
                @foreach ($categories as $cat)
                    <button wire:click="setCategory('{{ $cat }}')" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition {{ $category === $cat ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' }}">{{ $cat }}</button>
                @endforeach
            </div>
        @endif

        {{-- Featured band — admin-placed brands get a sponsored-style row up top,
             a real structural element (not just a badge), Trustpilot-style. --}}
        @if (! $category && $featuredBrands->isNotEmpty())
            <section class="mb-10">
                <h2 class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <x-icon name="badge-check" class="h-3.5 w-3.5 text-accent-dark dark:text-accent" /> Featured brands
                </h2>
                <div class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0">
                    @foreach ($featuredBrands as $brand)
                        <div class="w-[85%] shrink-0 snap-start sm:w-auto">
                            @include('livewire.partials.hunt-brand-card', ['brand' => $brand])
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Everything else, grouped by category — a real directory, not a flat
             scroll (BUILD-9 §8.1). When a category filter is active, the filtered
             brands (featured included) collapse into that one section. --}}
        @if ($category)
            @php($filtered = $brandsByCategory->get($category, collect())->concat($featuredBrands))
            @if ($filtered->isNotEmpty())
                <section class="mb-10">
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($filtered as $brand)
                            @include('livewire.partials.hunt-brand-card', ['brand' => $brand])
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            @forelse ($brandsByCategory as $cat => $catBrands)
                <section class="mb-10" wire:key="cat-{{ $cat }}">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $cat }}</h2>
                        <span class="text-xs text-slate-400">{{ $catBrands->count() }} {{ Str::plural('brand', $catBrands->count()) }}</span>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($catBrands as $brand)
                            @include('livewire.partials.hunt-brand-card', ['brand' => $brand])
                        @endforeach
                    </div>
                </section>
            @empty
                @if ($featuredBrands->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
                        @if ($platformHandles->isEmpty())No brands to follow yet — check back soon.@else More brands coming soon.@endif
                    </div>
                @endif
            @endforelse
        @endif

        {{-- Get-listed CTA (business pitch, distinct from the credit-hunter content). --}}
        <a href="{{ route('brand.get-listed') }}" wire:navigate
           class="mt-2 flex items-center justify-between gap-3 overflow-hidden rounded-2xl bg-[#0D1B2A] p-5 text-white">
            <div>
                <p class="text-sm font-bold">Grow your following. List your brand on {{ \App\Support\BrandSettings::name() }}.</p>
                <p class="mt-0.5 text-xs text-white/70">Real follows from people motivated to follow. See plans →</p>
            </div>
            <x-icon name="chevron-right" class="h-5 w-5 shrink-0" />
        </a>
    </div>
</div>
