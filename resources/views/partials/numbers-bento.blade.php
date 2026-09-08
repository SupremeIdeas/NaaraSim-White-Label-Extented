@php($__cards = \App\Support\NumbersBento::cards())
{{--
    Numbers bento grid (Numbers V6 §1) — compact, premium, theme-aware cards on
    a 6-column grid: Verify (4) + Rent (2) · Naara Line (6, full) · Internet
    Calls (3) + Call Forwarding (3) · Contact Management (6, full). Light cards
    on the light theme, dark-glass on dark. A brand gradient border/glow reveals
    on hover + focus (.nx-bento). Titles are normal-case: small "Naara" kicker
    over the emphasised word. Collapses to one column on mobile, order preserved.
--}}
<div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-6">
    @foreach ($__cards as $card)
        @php($isRoute = isset($card['link']['route']))
        @php($tag = $isRoute ? 'a' : 'button')
        @php($span = $card['span'])
        @php($wide = $span >= 3)
        @php($full = $span === 6)
        @php($spanClass = ['6' => 'md:col-span-6', '4' => 'md:col-span-4', '3' => 'md:col-span-3', '2' => 'md:col-span-2'][$span] ?? 'md:col-span-3')
        {{-- Mobile: full-width cards (Naara Line, Contacts) span both columns; the
             pair cards (Verify + Rent, Calls + Forwarding) sit two-up. --}}
        @php($mobileSpan = $full ? 'col-span-2' : 'col-span-1')
        @php($minH = $card['tall'] ? 'md:min-h-[188px]' : 'md:min-h-[150px]')
        @php([$w1, $w2] = array_pad(explode(' ', $card['title'], 2), 2, ''))

        <{{ $tag }}
            @if ($isRoute) href="{{ route($card['link']['route'], $card['link']['query'] ?? []) }}" wire:navigate
            @else type="button" wire:click="openModal('{{ $card['link']['modal'] }}')" @endif
            wire:key="bento-{{ $card['key'] }}"
            class="nx-bento group relative flex overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all duration-300 hover:-translate-y-0.5 dark:border-white/10 dark:bg-gradient-to-br dark:from-[#0C2434] dark:to-[#081521] dark:shadow-[0_12px_40px_-18px_rgba(0,0,0,0.7)] {{ $mobileSpan }} {{ $spanClass }} {{ $minH }}">

            {{-- Ambient brand glow (intensifies on hover) --}}
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl transition-opacity duration-300 group-hover:bg-primary/15 dark:bg-teal-500/10 dark:group-hover:bg-teal-500/20"></div>

            {{-- Badge — only rendered when the admin set one (BUILD-3 §6.11: show
                 only where meaningful). Each card/category gets a DISTINCT
                 gradient: by badge meaning where recognised, else a stable
                 per-card colour so no two adjacent cards look identical. --}}
            @if ($card['badge_label'])
                @php($__bl = strtolower(trim($card['badge_label'])))
                @php($__byMeaning = ['new' => 'from-emerald-500 to-teal-500', 'popular' => 'from-amber-500 to-orange-500', 'hot' => 'from-rose-500 to-red-500', 'soon' => 'from-slate-500 to-slate-600', 'save' => 'from-primary to-teal-500'])
                @php($__fallback = ['from-primary to-accent', 'from-fuchsia-500 to-purple-500', 'from-sky-500 to-indigo-500', 'from-cyan-500 to-blue-500'])
                @php($__grad = $__byMeaning[$__bl] ?? $__fallback[crc32($card['key']) % count($__fallback)])
                <span class="absolute right-2.5 top-2.5 z-20 rounded-full bg-gradient-to-r px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-normal text-white shadow-sm {{ $__grad }}">{{ $card['badge_label'] }}</span>
            @endif

            <div class="relative z-10 flex h-full w-full gap-3 {{ $wide ? 'items-center' : 'flex-col' }}">
                {{-- Text column --}}
                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="flex items-center gap-2.5 {{ $card['badge_label'] ? 'pr-20' : '' }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15 dark:bg-teal-500/15 dark:text-teal-300 dark:ring-teal-400/30">
                            <x-icon :name="$card['icon']" class="h-4 w-4" />
                        </span>
                        <h3 class="min-w-0 font-display leading-tight">
                            @if ($w2)
                                {{-- Kicker hidden on the tight two-up mobile cards so the badge never clips it. --}}
                                <span class="hidden text-[11px] font-semibold tracking-wide text-slate-400 sm:block dark:text-white/55">{{ $w1 }}</span>
                                <span class="block font-bold text-primary dark:text-teal-300 {{ $card['tall'] ? 'text-lg' : 'text-base' }}">{{ $w2 }}</span>
                            @else
                                <span class="block font-bold text-primary dark:text-teal-300 {{ $card['tall'] ? 'text-lg' : 'text-base' }}">{{ $w1 }}</span>
                            @endif
                        </h3>
                    </div>

                    <p class="mt-2 line-clamp-2 max-w-md text-xs leading-relaxed text-slate-500 dark:text-slate-300/90">{{ $card['subtitle'] }}</p>

                    @if (! empty($card['bullets']))
                        @if ($card['key'] === 'verify')
                            {{-- Keep it to two service pills + a "+more" so the card stays tidy. --}}
                            @php($pills = count($card['bullets']) > 3
                                ? array_merge(array_slice($card['bullets'], 0, 2), [end($card['bullets'])])
                                : $card['bullets'])
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach ($pills as $b)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:border-teal-400/25 dark:bg-teal-500/5 dark:text-slate-200">
                                        <x-icon name="badge-check" class="h-2.5 w-2.5 text-primary dark:text-teal-300" /> {{ $b }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <ul class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($card['bullets'] as $b)
                                    <li class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-200">
                                        <x-icon name="badge-check" class="h-3.5 w-3.5 shrink-0 text-primary dark:text-teal-300" /> {{ $b }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>

                {{-- Illustration (transparent PNG — works on light + dark) --}}
                @if ($wide)
                    <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                         class="hidden shrink-0 self-stretch object-contain object-right sm:block {{ $full ? 'sm:w-[28%] sm:max-w-[240px]' : 'sm:w-[36%] sm:max-w-[170px]' }}">
                @else
                    {{-- Hidden on mobile (keeps the two-up grid tight); shown from sm up. --}}
                    <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                         class="mt-2 hidden h-16 w-full shrink-0 object-contain object-center sm:block">
                @endif
            </div>
        </{{ $tag }}>
    @endforeach
</div>
