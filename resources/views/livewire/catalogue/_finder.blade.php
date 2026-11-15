{{-- eSIM Plan Finder + data estimator. Sits under the country / region / global header. Everything but "Sort best match" runs in the browser
     (Alpine `esimFinder`, resources/js/esim-finder.js) over a compact list of this context's plans; the button asks the server (applyFinder) for
     the real ranking. Read-only: it never changes a price, a plan or a provider, and never carries a cost. Expects: $fx (see Catalogue::finderFor),
     $finderKey (so a new country/region gets a fresh card). Skin-aware: nx tokens only, one root element. --}}
@php
    $f = $fx;
    $dayLabel = fn ($d) => $d === '30+' ? __('esim.finder_day_30plus') : ($d === 1 ? __('esim.finder_day_one') : __('esim.finder_day_many', ['n' => $d]));
    $usageIcons = ['light' => 'message-circle', 'medium' => 'play', 'heavy' => 'video'];
    $ranges = [[1, 5, __('esim.finder_price_under', ['n' => 5])], [5, 15, '$5–$15'], [15, 50, '$15–$50'], [50, 2000, __('esim.finder_price_over', ['n' => 50])]];
    $cfg = ['rows' => $f['live'], 'criteria' => $f['criteria'], 'profiles' => $f['profiles'], 'cover' => (float) config('esim_finder.min_cover'), 'over' => (float) config('esim_finder.max_overbuy'), 'lo' => (float) config('esim_finder.price_min'), 'hi' => (float) config('esim_finder.price_max')];
@endphp
<div class="ns-find" wire:key="esim-finder-{{ $finderKey }}" data-tour="esim.finder" x-data="esimFinder">
    <script type="application/json" x-ref="cfg">@json($cfg)</script>

    {{-- Compact summary that appears once the card has scrolled up past the header. Teleported to <body> because a transformed page
         ancestor would otherwise turn its `fixed` position into one relative to that ancestor. --}}
    <template x-teleport="body">
        <div class="ns-find__stick" x-show="stuck" x-cloak x-transition.opacity>
            <span><b x-text="(active === 1 ? @js(__('esim.finder_sticky_one')) : @js(__('esim.finder_sticky_many'))).replace(':count', active)"></b> · <b x-text="@js(__('esim.finder_sticky_fit')).replace(':fit', fit)"></b></span>
            <button type="button" class="ns-cta ns-cta--pill ns-cta--sm ns-cta--ghost" @click="toTop()">{{ __('esim.finder_edit') }}</button>
            <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" @click="go()">{{ __('esim.finder_go') }}</button>
        </div>
    </template>

    <div x-ref="card" class="ns-find__card">
        <div class="ns-find__head">
            <div style="min-width:0">
                <h3>{{ __('esim.finder_title') }}</h3>
                <p>{{ __('esim.finder_sub', ['count' => number_format($f['total'])]) }}</p>
            </div>
            <button type="button" class="ns-find__tg" @click="open = !open" :aria-expanded="open" aria-label="{{ __('esim.finder_toggle') }}"><span x-text="open ? '–' : '+'"></span></button>
        </div>

        <div class="ns-find__body" x-show="open" x-collapse.duration.200ms>
            {{-- Estimator: a premium button opens a guided panel (use, trip length, a clear result). --}}
            <div class="ns-find__sec ns-find__estrow">
                <div><b>{{ __('esim.finder_est_q') }}</b><small>{{ __('esim.finder_est_hint') }}</small></div>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--sm ns-cta--ghost" @click="est = !est" :aria-expanded="est">
                    <x-nx.icon name="estimator" style="font-size:22px" />
                    <span x-text="est ? @js(__('esim.finder_est_hide')) : @js(__('esim.finder_est_open'))"></span>
                </button>
            </div>

            <div x-show="est" x-cloak x-collapse.duration.220ms>
                <div class="ns-find__est">
                    <div class="ns-find__est-top"><h4>{{ __('esim.finder_est_title') }}</h4><p>{{ __('esim.finder_est_sub') }}</p></div>

                    <div class="ns-find__lb"><span>{{ __('esim.finder_est_usage_q') }}</span></div>
                    <div class="ns-find__rail ns-find__rail--cards" x-rail role="radiogroup" aria-label="{{ __('esim.finder_est_usage_q') }}">
                        @foreach ($f['profiles'] as $key => $p)
                            @php($tag = trim((string) (explode('—', $p['label'], 2)[1] ?? '')))
                            <button type="button" role="radio" class="ns-find__use" :class="{ 'is-on': c.usage === '{{ $key }}' }" :aria-checked="c.usage === '{{ $key }}'" @click="pickUsage('{{ $key }}')">
                                <x-nx.icon name="{{ $usageIcons[$key] ?? 'signal' }}" />
                                <b>{{ __('esim.finder_usage_'.$key) }}</b>
                                <small>{{ $tag }}</small>
                                <em x-text="@js(__('esim.finder_est_gb_day')).replace(':gb', perDay('{{ $key }}'))"></em>
                            </button>
                        @endforeach
                    </div>

                    <div class="ns-find__lb" style="margin-top:14px"><span>{{ __('esim.finder_est_days_q') }}</span><small><b x-text="@js(__('esim.finder_day_many')).replace(':n', c.trip)"></b></small></div>
                    <div class="ns-find__rail" x-rail role="group" aria-label="{{ __('esim.finder_est_days_q') }}">
                        <template x-for="d in tripChips" :key="d"><button type="button" class="ns-find__chip" :class="{ 'is-on': c.trip === d }" :aria-pressed="c.trip === d" @click="pickTrip(d)" x-text="d"></button></template>
                    </div>
                    <input type="range" min="1" max="60" step="1" x-model.number="c.trip" @input="manual()" class="ns-find__range" aria-label="{{ __('esim.finder_est_days_q') }}">

                    <div class="ns-find__result" aria-live="polite">
                        <small>{{ __('esim.finder_est_need') }}</small>
                        <b>≈ <span x-text="estGb"></span> GB</b>
                        <small x-text="@js(__('esim.finder_est_math')).replace(':gb', perDayGb).replace(':days', c.trip)"></small>
                        <p x-show="sizeNeeded" x-text="@js(__('esim.finder_est_size')).replace(':n', sizeNeeded)"></p>
                        <p x-show="!sizeNeeded">{{ __('esim.finder_est_big') }}</p>
                        <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost" @click="useEstimate()" wire:loading.attr="disabled" wire:target="applyFinder">
                            <x-nx.icon name="zap" /> {{ __('esim.finder_est_use') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Validity --}}
            <div class="ns-find__sec">
                <div class="ns-find__lb"><span>{{ __('esim.finder_days_q') }}</span><small>{{ __('esim.finder_days_hint') }}</small></div>
                <div class="ns-find__rail" x-rail role="group" aria-label="{{ __('esim.finder_days_q') }}">
                    @foreach (\App\Support\EsimPlanFinder::DAY_CHOICES as $d)
                        <button type="button" class="ns-find__chip" @click="toggleDay(@js($d))" :class="{ 'is-on': c.days.includes(@js($d)) }" :aria-pressed="c.days.includes(@js($d))">{{ $dayLabel($d) }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Data --}}
            <div class="ns-find__sec">
                <div class="ns-find__lb"><span>{{ __('esim.finder_data_q') }}</span><small>{{ __('esim.finder_data_hint') }}</small></div>
                <div class="ns-find__rail" x-rail role="group" aria-label="{{ __('esim.finder_data_q') }}">
                    @foreach (\App\Support\EsimPlanFinder::NEED_CHOICES as $g)
                        <button type="button" class="ns-find__chip" @click="setNeed({{ $g }})" :class="{ 'is-on': c.need === {{ $g }} && !c.unlimited }" :aria-pressed="c.need === {{ $g }} && !c.unlimited">{{ $g }} GB</button>
                    @endforeach
                    <button type="button" class="ns-find__chip" @click="toggleUnlimited()" :class="{ 'is-on': c.unlimited }" :aria-pressed="c.unlimited">{{ __('esim.finder_unlimited') }}</button>
                </div>
            </div>

            {{-- Price --}}
            <div class="ns-find__sec">
                <div class="ns-find__lb"><span>{{ __('esim.finder_price_q') }}</span><small><span x-text="money(c.min)"></span> – <span x-text="money(c.max)"></span></small></div>
                <div class="ns-find__dual">
                    <div class="ns-find__track"></div><div class="ns-find__fill" :style="fillStyle"></div>
                    <input type="range" min="0" max="100" step="1" x-model.number="vMin" @input="slide('min')" aria-label="{{ __('esim.finder_price_q') }} min">
                    <input type="range" min="0" max="100" step="1" x-model.number="vMax" @input="slide('max')" aria-label="{{ __('esim.finder_price_q') }} max">
                </div>
                <div class="ns-find__pin">
                    <label><span>$</span><input type="text" inputmode="decimal" :value="c.min" @change="typePrice('min', $event.target.value)" aria-label="Min"></label>
                    <em>{{ __('esim.finder_price_to') }}</em>
                    <label><span>$</span><input type="text" inputmode="decimal" :value="c.max" @change="typePrice('max', $event.target.value)" aria-label="Max"></label>
                </div>
                <div class="ns-find__rail" x-rail role="group" style="margin-top:10px">
                    @foreach ($ranges as [$a, $b, $text])
                        <button type="button" class="ns-find__chip" @click="quickPrice({{ $a }}, {{ $b }})" :class="{ 'is-on': c.min === {{ $a }} && c.max === {{ $b }} }">{{ $text }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Live counter --}}
            <div class="ns-find__live" aria-live="polite">
                <span x-text="active ? @js(__('esim.finder_live')) : @js(__('esim.finder_live_all'))"></span>
                <b><span x-text="fit.toLocaleString()"></span> / <span x-text="total.toLocaleString()"></span></b>
            </div>

            @if ($f['criteria']['estimate'])
                <p class="ns-find__note">{{ __('esim.finder_used_est', ['usage' => $f['criteria']['usage'], 'days' => $f['criteria']['trip']]) }}</p>
            @endif

            <div class="ns-find__actions">
                <button type="button" class="ns-cta" @click="go()" wire:loading.attr="disabled" wire:target="applyFinder,resetFinder">
                    <span wire:loading.remove wire:target="applyFinder"><x-nx.icon name="search" /> {{ __('esim.finder_go') }}</span>
                    <span wire:loading wire:target="applyFinder">…</span>
                </button>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost" @click="reset()" wire:loading.attr="disabled" wire:target="applyFinder,resetFinder">{{ __('esim.finder_reset') }}</button>
            </div>
        </div>
    </div>
</div>
