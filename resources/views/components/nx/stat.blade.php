{{-- Stat tile: small label, big tabular value. tone="bad" (danger) or "ok" recolours the value only. With a `value`, the slot renders as a sub-line under it. --}}
@props(['label' => null, 'value' => null, 'tone' => null, 'href' => null])
@php($el = $href ? 'a' : 'div')
<{{ $el }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'ns-card ns-stat ns-ring'.($tone ? ' ns-stat--'.$tone : '')]) }}>
    <small>{{ $label }}</small>
    <b>{{ $value ?? $slot }}</b>
    @if ($value !== null && ! $slot->isEmpty()){{ $slot }}@endif
</{{ $el }}>
