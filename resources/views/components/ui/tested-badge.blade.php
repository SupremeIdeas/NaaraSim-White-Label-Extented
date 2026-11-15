{{-- "Recommended" and "Tested live" pills for a provider that was exercised with real keys (config/tested_providers.php). Solid pills per the PILL RULE; the what-and-when sits in the title and, with `detail`, as a line of text. --}}
@props(['provider', 'detail' => false])
@php($t = \App\Support\TestedProviders::for($provider))
@if ($t)
    <span class="inline-flex flex-wrap items-center gap-1 align-middle" data-tested-provider="{{ $provider }}" data-tested-level="{{ $t['level'] }}" title="Tested {{ $t['on'] }}: {{ $t['tested'] }} Not yet: {{ $t['not_yet'] }}@if (! empty($t['retest'])) Latest re-test: {{ $t['retest'] }}@endif">
        @if ($t['level'] === 'proven')<span class="ns-pill ns-pill--gold gap-1"><svg class="h-3.5 w-3.5" aria-hidden="true"><use href="#i-sh-ok"></use></svg> Recommended</span>@endif
        <span class="ns-pill gap-1"><svg class="h-3.5 w-3.5" aria-hidden="true"><use href="#i-sh-pulse"></use></svg> Tested live</span>
    </span>
    @if ($detail)
        <span class="mt-1 block text-xs text-[rgb(var(--nx-text-2))]" data-tested-detail>Tested {{ $t['on'] }}: {{ $t['tested'] }} <span class="opacity-80">Not yet: {{ $t['not_yet'] }}</span>@if (! empty($t['retest']))<span class="block font-semibold" data-tested-retest>Latest re-test: {{ $t['retest'] }}</span>@endif</span>
    @endif
@endif
