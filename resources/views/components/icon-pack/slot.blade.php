{{-- A menu icon from the icon pack set. Falls back to the generic sprite icon when the item has no pack icon, so a missing file never leaves a hole. --}}
@props(['route' => null, 'surface' => null, 'fallback' => 'grid', 'nx' => false, 'class' => '', 'size' => null])
@php $mi = $route ? \App\Support\IconPacks\SlotIcons::resolve($route, $surface) : null; @endphp
@if ($mi)
    <span {{ $attributes->merge(['class' => 'mi '.($mi['custom'] ? '' : 'mi--duo ').$class]) }} @if ($size) style="--mi-size:{{ (int) $size }}px" @endif aria-hidden="true">
        <img class="mi__l" src="{{ $mi['light'] }}" alt="" width="64" height="64" decoding="async" draggable="false">
        @unless ($mi['custom'])<img class="mi__d" src="{{ $mi['dark'] }}" alt="" width="64" height="64" decoding="async" draggable="false">@endunless
    </span>
@elseif ($nx)
    <x-nx.icon :name="$fallback" {{ $attributes->only('class') }} />
@else
    <x-icon :name="$fallback" :class="$class ?: 'h-5 w-5'" />
@endif
