{{-- Dashed empty state: title + one line + optional action in the slot. Every empty state exists in every skin through this. --}}
@props(['title' => null, 'text' => null])
<div {{ $attributes->merge(['class' => 'ns-empty']) }}>
    @if ($title)<b style="display:block;font-size:17px;color:rgb(var(--nx-text))">{{ $title }}</b>@endif
    @if ($text)<span class="ns-sub" style="display:block;margin-top:4px">{{ $text }}</span>@endif
    {{ $slot }}
</div>
