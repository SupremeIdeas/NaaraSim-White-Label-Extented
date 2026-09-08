{{-- "Popular Destinations" photo-card row (owner request). One shared layout —
     not a per-theme variant — so every theme's eSIM Popular tab shows it
     identically, the same way Dashboard's "Explore" rows are shared everywhere.

     A destination is "popular" purely because it has an admin-featured plan
     (the same is_featured flag that already drives the Popular tab's plan
     list — no new curation surface). Its photo is the SAME per-country image
     an admin already sets in Admin → eSIM Control Center
     (EsimCountryImage.detail_image_path); nothing new to upload here. A
     country with no photo set still shows a clean flag+price card — the photo
     is decoration, never a requirement to appear.

     Expects: $popularDestinations (list, see EsimCatalogue::popularDestinations()), $fmt. --}}
@if (count($popularDestinations))
    <div class="mb-6">
        <div class="mb-2 flex items-baseline justify-between">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Popular Destinations</p>
            <p class="text-xs text-slate-400">{{ count($popularDestinations) }} {{ \Illuminate\Support\Str::plural('country', count($popularDestinations)) }}</p>
        </div>
        <div class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" style="scrollbar-width: none;">
            @foreach ($popularDestinations as $d)
                @php($teaser = $d['from_usd'] !== null ? $fmt((float) $d['from_usd']) : null)
                @php($dataLabel = $d['data_mb'] ? (($d['data_mb'] >= 1024) ? round($d['data_mb'] / 1024, 1).'GB' : $d['data_mb'].'MB') : null)
                <button type="button" wire:click="openCountry('{{ $d['code'] }}')" wire:key="popular-dest-{{ $d['code'] }}"
                        class="group relative h-40 w-32 shrink-0 overflow-hidden rounded-2xl border border-slate-200 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 dark:border-[var(--brand-card-border-dark)] sm:h-44 sm:w-36">
                    @if ($d['photo'])
                        <img src="{{ $d['photo'] }}" alt="{{ $d['name'] }}" loading="lazy"
                             class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent"></div>
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-primary/20 to-primary/5 dark:from-primary/30 dark:to-transparent"></div>
                    @endif

                    <span class="absolute right-2 top-2 rounded-full bg-black/40 p-1 backdrop-blur-sm">
                        <x-country-flag :country="$d['code']" class="h-4 w-6 rounded-sm shadow" />
                    </span>

                    <div class="absolute inset-x-0 bottom-0 p-2.5">
                        <p @class([
                            'truncate text-sm font-bold drop-shadow',
                            'text-white' => $d['photo'],
                            'text-slate-900 dark:text-slate-100' => ! $d['photo'],
                        ])>{{ $d['name'] }}</p>
                        <p @class([
                            'truncate text-[11px]',
                            'text-slate-200' => $d['photo'],
                            'text-slate-500 dark:text-slate-400' => ! $d['photo'],
                        ])>
                            @if ($teaser)from {{ $teaser['usd'] }}@endif
                            @if ($dataLabel) · {{ $dataLabel }} @endif
                        </p>
                        <span @class([
                            'mt-1 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                            'bg-white/20 text-white' => $d['photo'],
                            'bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300' => ! $d['photo'],
                        ])>
                            <x-icon name="zap" class="h-2.5 w-2.5" /> Instant eSIM
                        </span>
                    </div>
                </button>
            @endforeach
        </div>
    </div>
@endif
