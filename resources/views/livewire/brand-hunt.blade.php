<div x-data="{ bg: '' }" :style="bg ? `background-color:${bg}` : ''" style="transition: background-color .7s ease">

    {{-- Hero band — dark, distinct from the body below, closed with a rounded
         "sheet" seam per the section-divider rule (no flat colour boundary). --}}
    <div class="bg-gradient-to-br from-[#0D1B2A] to-[#0A6E6E] px-4 pb-10 pt-6 text-white">
        <div class="mx-auto max-w-5xl">
            <a href="{{ route('rewards') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-white/70 hover:text-white">
                <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to Rewards
            </a>
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Brand Partner Directory</p>
                    <h1 class="mt-1.5 text-3xl font-bold sm:text-4xl">The Hunt</h1>
                    <p class="mt-2 max-w-xl text-sm text-white/70">Follow real brands, earn surprise NaaraCredits. Every follow is a one-time reward, server-confirmed the moment you claim it.</p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-3 text-center backdrop-blur">
                    <p class="text-2xl font-bold tabular-nums">${{ number_format($dailyRemaining, 0) }}</p>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-white/60">left to earn today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto -mt-6 max-w-5xl rounded-t-[30px] bg-slate-50 px-4 pb-10 pt-6 dark:bg-[#0F1D33]">
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
