{{-- The journey (CMS: home.how): three STACKING cards. Each is sticky, so the next one slides over it as you scroll. Every word, tag, chip and
     picture is editable in the Site Editor; a card with no uploaded picture shows a coded phone built from the brand's own name and colours. --}}
@php
    $brand = \App\Support\BrandSettings::name();
    $qr = function () {
        $cells = [];
        mt_srand(7);
        for ($y = 0; $y < 9; $y++) {
            for ($x = 0; $x < 9; $x++) {
                $finder = ($x < 3 && $y < 3) || ($x > 5 && $y < 3) || ($x < 3 && $y > 5);
                $cells[] = $finder ? ($x % 2 === 0 || $y % 2 === 0 || ($x === 1 && $y === 1) ? 'b' : 'o') : (mt_rand(0, 100) > 48 ? 'b' : 'o');
            }
        }

        return $cells;
    };
@endphp
<section class="mx-auto max-w-5xl px-4 py-20" data-bg="light">
    <div class="text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $s['headline'] }}</h2>
        <p data-reveal class="mx-auto mt-3 max-w-xl text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
    </div>

    <div class="nx-journey mt-14">
        @foreach ([1, 2, 3] as $n)
            @php($img = \App\Support\SiteContent::imageUrl($s["step_{$n}_image"] ?? ''))
            @php($chips = array_values(array_filter(array_map('trim', preg_split('/[·,|]/u', (string) ($s["step_{$n}_chips"] ?? ''))))))
            <article class="nx-journey__card" style="top: calc(5.25rem + {{ ($n - 1) * 1.1 }}rem)">
                <div>
                    <div class="flex items-center gap-4">
                        <span class="nx-journey__num" aria-hidden="true">0{{ $n }}</span>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">{{ $s["step_{$n}_tag"] ?? '' }}</p>
                    </div>
                    <h3 class="mt-3 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $s["step_{$n}_title"] }}</h3>
                    <p class="mt-3 max-w-md leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["step_{$n}_text"] }}</p>
                    @if ($chips)
                        <div class="nx-journey__chips">@foreach ($chips as $c)<span class="nx-journey__chip">{{ $c }}</span>@endforeach</div>
                    @endif
                </div>
                <div class="nx-journey__visual">
                    @if ($img)
                        <img src="{{ $img }}" alt="{{ $s["step_{$n}_title"] }}" loading="lazy" decoding="async">
                    @elseif ($n === 1)
                        <div class="nx-phone" aria-hidden="true"><span class="nx-phone__notch"></span><div class="nx-phone__screen">
                            <p class="nx-phone__title">Where to?</p><p class="nx-phone__big">Choose a plan</p>
                            @foreach ([['fr', 'France', '5 GB'], ['jp', 'Japan', '10 GB'], ['br', 'Brazil', '3 GB']] as $i => [$cc, $nm, $gb])
                                <div class="nx-phone__row {{ $i === 1 ? 'nx-phone__row--on' : '' }}"><span class="fi fi-{{ $cc }} h-3.5 w-5 rounded-[2px] bg-cover"></span><span class="flex-1">{{ $nm }}</span><span class="opacity-60">{{ $gb }}</span></div>
                            @endforeach
                            <div class="nx-phone__cta">Continue</div>
                        </div></div>
                    @elseif ($n === 2)
                        <div class="nx-phone" aria-hidden="true" style="transform: rotate(3deg)"><span class="nx-phone__notch"></span><div class="nx-phone__screen">
                            <p class="nx-phone__title">Your plan</p><p class="nx-phone__big">10 GB · 15 days</p>
                            <div class="nx-phone__row"><x-glyph name="globe" :size="16" /><span class="flex-1">Japan</span><span class="opacity-60">eSIM</span></div>
                            <div class="nx-phone__row"><x-glyph name="credit-card" :size="16" /><span class="flex-1">Card ending 4242</span></div>
                            <div class="nx-phone__row"><x-glyph name="wallet" :size="16" /><span class="flex-1">Wallet</span><span class="opacity-60">$38.00</span></div>
                            <div class="nx-phone__cta">Pay $14.50</div>
                        </div></div>
                    @else
                        <div class="nx-phone" aria-hidden="true"><span class="nx-phone__notch"></span><div class="nx-phone__screen">
                            <p class="nx-phone__title">{{ $brand }}</p><p class="nx-phone__big">Scan to install</p>
                            <div class="nx-phone__qr">@foreach ($qr() as $c)<i class="{{ $c === 'o' ? 'o' : '' }}"></i>@endforeach</div>
                            <div class="nx-phone__row"><span class="nx-phone__bars"><b style="height:35%"></b><b style="height:60%"></b><b style="height:85%"></b><b style="height:100%"></b></span><span class="flex-1">Connected</span><span class="opacity-60">5G</span></div>
                        </div></div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div data-reveal class="mt-12 text-center">
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary !px-8 !py-3">{{ $s['cta'] }}</a>
    </div>
</section>
