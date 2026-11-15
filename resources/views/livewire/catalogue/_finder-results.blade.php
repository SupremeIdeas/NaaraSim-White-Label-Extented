{{-- Plan Finder results block. Not applied yet: just the hint (the caller then shows the normal list). Applied: header + sort + chips + the ranked
     plan list (same rows, plus badges and a "why" line) + the "nothing fits" helper. Expects: $fx, $fmt. --}}
@php($c = $fx['criteria'])
@if (! $fx['applied'])
    <div class="ns-find__hint">{{ __('esim.finder_hint') }}</div>
@else
    <div class="ns-find__results" wire:key="esim-finder-results">
        @if ($fx['fit'] === 0)
            <div class="ns-find__none">
                <b>{{ __('esim.finder_none_title') }}</b>
                @if ($fx['relax'])
                    <p>{{ __('esim.finder_none_body') }}</p>
                    <div class="ns-seg ns-seg--wrap">
                        @foreach ($fx['relax'] as $r)
                            <button type="button" wire:click="relaxFinder('{{ $r['key'] }}')" wire:loading.attr="disabled" wire:target="relaxFinder">
                                {{ __('esim.finder_relax_'.$r['key'], ['count' => $r['count'], 'max' => isset($r['max']) ? number_format($r['max']) : '']) }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <p>{{ __('esim.finder_none_reset') }}</p>
                @endif
            </div>
        @else
            <div class="ns-nrow">
                <span class="ns-lbl" style="color:rgb(var(--nx-text));font-weight:600">{{ $c['sort'] === 'best' ? __('esim.finder_results_best') : __('esim.finder_results_all') }}</span>
                <span class="ns-sub" style="margin:0;font-size:13px">{{ __('esim.finder_fit_of', ['fit' => number_format($fx['fit']), 'total' => number_format($fx['total'])]) }}</span>
            </div>
            <div class="ns-find__bar">
                <label class="ns-find__sort"><span>{{ __('esim.finder_sort') }}</span>
                    <select wire:change="setFinderSort($event.target.value)" aria-label="{{ __('esim.finder_sort') }}">
                        @foreach (\App\Support\EsimPlanFinder::SORTS as $s)
                            <option value="{{ $s }}" @selected($c['sort'] === $s)>{{ __('esim.finder_sort_'.$s) }}</option>
                        @endforeach
                    </select></label>
                <button type="button" class="ns-find__link" wire:click="clearFinderResults">{{ __('esim.finder_show_all') }}</button>
            </div>
        @endif
        @if ($fx['fit'] > 0)
            @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt, 'finderMeta' => $fx['meta']])
        @endif
    </div>
@endif
