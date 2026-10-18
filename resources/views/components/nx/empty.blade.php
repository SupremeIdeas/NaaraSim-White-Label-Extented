{{-- Dashed empty state: optional drawn illustration, title + one line + optional action in the slot. Every empty state exists in every skin through this. --}}
@props(['title' => null, 'text' => null, 'art' => null])
<div {{ $attributes->merge(['class' => 'ns-empty']) }}>
    @if ($art && is_file(public_path('images/illustrations/'.$art.'.svg')))<img src="{{ asset('images/illustrations/'.$art.'.svg') }}" alt="" width="240" height="180" loading="lazy" decoding="async" style="display:block;margin:0 auto 6px;height:132px;width:auto" data-empty-art>@endif
    @if ($title)<b style="display:block;font-size:17px;color:rgb(var(--nx-text))">{{ $title }}</b>@endif
    @if ($text)<span class="ns-sub" style="display:block;margin-top:4px">{{ $text }}</span>@endif
    {{ $slot }}
</div>
