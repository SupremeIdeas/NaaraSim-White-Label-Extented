{{-- Row inside an <x-nx.list>: tile icon, title (+ small subtitle), optional pill, chevron. href => link, otherwise a button. --}}
@props(['icon' => null, 'title' => null, 'text' => null, 'pill' => null, 'href' => null, 'active' => false, 'chevron' => true, 'pillVariant' => 'best'])
@php($el = $href ? 'a' : 'button')
<{{ $el }} @if ($href) href="{{ $href }}" wire:navigate @else type="button" @endif {{ $attributes->merge(['class' => 'ns-list__row'.($active ? ' is-on' : '')]) }} @if ($active) aria-current="true" @endif>
    @if ($icon)<span class="ns-tile"><x-nx.icon :name="$icon" /></span>@endif
    <span>{{ $title }}@if ($text)<small class="ns-small">{{ $text }}</small>@endif {{ $slot }}</span>
    @if ($pill)<x-nx.pill :variant="$pillVariant" style="margin-left:auto">{{ $pill }}</x-nx.pill>
    @elseif ($chevron)<x-nx.icon name="chevron-right" class="ns-chevron" />@endif
</{{ $el }}>
