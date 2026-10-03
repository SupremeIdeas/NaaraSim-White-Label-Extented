{{-- Helper note. variant: dash (dashed outline) | warn | link (tappable card, pass href or wire:click) | ring. --}}
@props(['icon' => 'info', 'variant' => null, 'href' => null, 'tag' => null])
@php
    $cls = 'ns-note'.($variant === 'dash' ? ' ns-note--dash' : '').($variant === 'warn' ? ' ns-note--warn' : '').($variant === 'link' ? ' ns-note--link ns-ring' : '').($variant === 'ring' ? ' ns-ring' : '');
    $el = $tag ?? ($href ? 'a' : 'div');
@endphp
<{{ $el }} @if ($href) href="{{ $href }}" @endif @if ($el === 'button') type="button" @endif {{ $attributes->merge(['class' => $cls]) }}>
    @if ($icon)<x-nx.icon :name="$icon" />@endif
    <span>{{ $slot }}</span>
    @if ($variant === 'link')<x-nx.icon name="chevron-right" class="ns-chevron" />@endif
</{{ $el }}>
