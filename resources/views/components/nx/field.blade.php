{{-- Selector card (Country / Service). `leading` slot = flag or service icon, `label` the small caption, `value` the bold line.
     Attributes (wire:click ...) land on the button. --}}
@props(['label' => null, 'value' => null, 'hint' => null, 'valueFirst' => false])
<button type="button" {{ $attributes->merge(['class' => 'ns-field ns-ring']) }}>
    @isset($leading)<span class="ns-flag-slot">{{ $leading }}</span>@endisset
    <span class="ns-text">
        @if ($valueFirst)
            <b>{{ $value }}</b>@if ($hint)<small>{{ $hint }}</small>@endif
        @else
            @if ($label)<small>{{ $label }}</small>@endif<b>{{ $value }}</b>
        @endif
    </span>
    <x-nx.icon name="chevron-right" />
</button>
